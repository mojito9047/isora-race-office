<?php

namespace Isora\Component\Isorairc\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Http\HttpFactory;

/**
 * Downloads the rating feeds (IRC and YTC), keeps the current ratings table
 * up to date, archives every download and freezes a snapshot per rating
 * system for each race once its rating deadline has passed.
 */
class RatingsHelper
{
    public const DEFAULT_IRC_URL = 'https://www.topyacht.com.au/rorc/data/ClubListing.csv';

    public const DEFAULT_YTC_URL = 'https://docs.google.com/spreadsheets/d/1Se2j64wyx_61Ux8GB08i4gK1M2TezDe8uhZQtZhhtkQ/edit';

    /** Fewer rows than this means a feed is broken; that import is abandoned. */
    public const MIN_ROWS = 100;

    /** Every rating column in the ratings and snapshot tables. */
    public const DB_COLUMNS = [
        'boat_name', 'sail_no', 'boat_type', 'club', 'tcc', 'non_spi_tcc', 'cert_no', 'issue_date',
        'cert_year', 'endorsed', 'secondary', 'crew', 'valid_code', 'provisional',
        'headsails', 'flying_headsails', 'spinnakers', 'sss_base',
    ];

    /**
     * The rating systems and how each feed maps onto the database.
     *
     * 'columns' is database column => CSV heading. 'tcc' holds the main rating
     * and 'non_spi_tcc' the non-spinnaker / white sail rating for every system.
     * 'key' lists the columns that identify the same boat across downloads.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function systems(bool $enabledOnly = true): array
    {
        $params = ComponentHelper::getParams('com_isorairc');

        $systems = [
            'irc' => [
                'label'         => 'IRC',
                'enabled'       => (bool) $params->get('irc_enabled', 1),
                'url'           => self::csvUrl(trim((string) $params->get('feed_url', '')) ?: self::DEFAULT_IRC_URL),
                'decimals'      => 3,
                'rating_label'  => 'TCC',
                'rating2_label' => 'Non-spinnaker TCC',
                'required'      => ['Boat Name', 'Sail No', 'TCC'],
                'key'           => ['sail_no', 'boat_name', 'secondary'],
                'columns'       => [
                    'boat_name'   => 'Boat Name',
                    'sail_no'     => 'Sail No',
                    'cert_no'     => 'Cert No',
                    'issue_date'  => 'Issue Date',
                    'cert_year'   => 'Cert Year',
                    'tcc'         => 'TCC',
                    'endorsed'    => 'Endorsed',
                    'secondary'   => 'Secondary',
                    'non_spi_tcc' => 'Non Spi TCC',
                    'crew'        => 'Crew',
                    'headsails'   => 'Headsails',
                    'flying_headsails' => 'Flying Headsails',
                    'spinnakers'  => 'Spinnakers',
                    'sss_base'    => 'SSS Base Value',
                    'valid_code'  => 'ValidCode',
                ],
            ],
            'ytc' => [
                'label'         => 'YTC',
                'enabled'       => (bool) $params->get('ytc_enabled', 1),
                'url'           => self::csvUrl(trim((string) $params->get('ytc_url', '')) ?: self::DEFAULT_YTC_URL),
                'decimals'      => 0,
                'rating_label'  => 'YTC',
                'rating2_label' => 'YTC white sail',
                'required'      => ['Boat Name', 'Sail Number', 'YTC'],
                'key'           => ['cert_no', 'sail_no', 'boat_name'],
                'columns'       => [
                    'boat_name'   => 'Boat Name',
                    'sail_no'     => 'Sail Number',
                    'boat_type'   => 'Boat Type',
                    'tcc'         => 'YTC',
                    'non_spi_tcc' => 'YTC White Sail',
                    'club'        => 'Club',
                    'provisional' => 'Provisional',
                    'issue_date'  => 'Issue Date',
                    'cert_no'     => 'EntryID',
                ],
            ],
        ];

        if ($enabledOnly) {
            $systems = array_filter($systems, static function ($system) {
                return $system['enabled'];
            });
        }

        return $systems;
    }

    /**
     * Accepts an ordinary Google Sheets link and turns it into its CSV export address.
     * Any other address is returned unchanged.
     */
    public static function csvUrl(string $url): string
    {
        if (
            strpos($url, '/export') === false
            && preg_match('#^https://docs\.google\.com/spreadsheets/d/([A-Za-z0-9_-]+)#', $url, $match)
        ) {
            $export = 'https://docs.google.com/spreadsheets/d/' . $match[1] . '/export?format=csv';

            if (preg_match('/[#&?]gid=(\d+)/', $url, $gid)) {
                $export .= '&gid=' . $gid[1];
            }

            return $export;
        }

        return $url;
    }

    /**
     * Import every enabled feed, then take any snapshots that are due.
     * One feed failing does not stop the other.
     *
     * @return array{imported: array<string,int>, errors: array<string,string>, snapshots: int}
     */
    public static function run(): array
    {
        $result = ['imported' => [], 'errors' => [], 'snapshots' => 0];

        foreach (self::systems() as $scheme => $system) {
            try {
                $result['imported'][$scheme] = self::import($scheme, $system);
            } catch (\Throwable $e) {
                $result['errors'][$scheme] = $e->getMessage();
            }
        }

        try {
            $result['snapshots'] = self::processSnapshots();
        } catch (\Throwable $e) {
            $result['errors']['snapshots'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * One line describing the result of run().
     */
    public static function summary(array $result): string
    {
        $systems = self::systems(false);
        $parts   = [];

        foreach ($result['imported'] as $scheme => $count) {
            $parts[] = $systems[$scheme]['label'] . ': ' . $count . ' boats imported';
        }

        foreach ($result['errors'] as $scheme => $message) {
            $parts[] = (isset($systems[$scheme]) ? $systems[$scheme]['label'] . ' import' : 'Race snapshots') . ' FAILED: ' . $message;
        }

        $parts[] = $result['snapshots'] . ' race snapshot(s) taken';

        return implode('; ', $parts) . '.';
    }

    /**
     * Download one feed, archive it and replace that system's current ratings.
     *
     * @return int Number of boats imported.
     */
    public static function import(string $scheme, array $system): int
    {
        $db = self::db();

        $response = HttpFactory::getHttp()->get(
            $system['url'],
            ['User-Agent' => 'Mozilla/5.0 (compatible; ratings-import/1.1)'],
            60
        );

        if ((int) $response->getStatusCode() !== 200) {
            throw new \RuntimeException('feed returned HTTP ' . (int) $response->getStatusCode());
        }

        $csv  = (string) $response->getBody();
        $rows = self::parse($csv, $system);

        if (\count($rows) < self::MIN_ROWS) {
            throw new \RuntimeException(
                'feed looks incomplete (' . \count($rows) . ' boats); import abandoned, existing ratings kept'
            );
        }

        // Archive the download (the body is stored only when it changed).
        $hash  = sha1($csv);
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'content_id', 'csv_hash']))
            ->from($db->quoteName('#__isorairc_imports'))
            ->where($db->quoteName('scheme') . ' = ' . $db->quote($scheme))
            ->order($db->quoteName('id') . ' DESC');
        $last = $db->setQuery($query, 0, 1)->loadObject();

        $record = (object) [
            'scheme'      => $scheme,
            'imported_at' => Factory::getDate()->toSql(),
            'row_count'   => \count($rows),
            'csv_hash'    => $hash,
        ];

        $modified = $response->getHeaderLine('Last-Modified');

        if ($modified !== '' && strtotime($modified) !== false) {
            $record->feed_modified = gmdate('Y-m-d H:i:s', strtotime($modified));
        }

        if ($last && $last->csv_hash === $hash && $last->content_id) {
            $record->content_id = (int) $last->content_id;
        } else {
            $record->csv_gz = base64_encode(gzencode($csv, 9));
        }

        // Log the download and replace this system's current ratings in one
        // transaction, so a failed import leaves no trace.
        $db->transactionStart();

        try {
            $db->insertObject('#__isorairc_imports', $record, 'id');

            if (empty($record->content_id)) {
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__isorairc_imports'))
                    ->set($db->quoteName('content_id') . ' = ' . (int) $record->id)
                    ->where($db->quoteName('id') . ' = ' . (int) $record->id);
                $db->setQuery($query)->execute();
            }

            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__isorairc_ratings'))
                ->where($db->quoteName('scheme') . ' = ' . $db->quote($scheme));
            $db->setQuery($query)->execute();
            self::insertRows('#__isorairc_ratings', $rows, ['scheme' => $scheme]);
            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();

            throw $e;
        }

        return \count($rows);
    }

    /**
     * Take the missing snapshots of every race whose deadline has passed,
     * one per enabled rating system.
     *
     * A snapshot is built from the first download made at or after the deadline.
     * A boat whose certificate in that download was issued after the deadline date
     * is given the certificate it had in the last download before the deadline,
     * or left out if it had none.
     *
     * @return int Number of snapshots taken.
     */
    public static function processSnapshots(): int
    {
        $db  = self::db();
        $now = Factory::getDate()->toSql();

        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'rating_deadline']))
            ->from($db->quoteName('#__isorairc_races'))
            ->where($db->quoteName('rating_deadline') . ' <= ' . $db->quote($now));
        $races = $db->setQuery($query)->loadObjectList();

        if (!$races) {
            return 0;
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName(['race_id', 'scheme']))
            ->from($db->quoteName('#__isorairc_race_snapshots'));
        $taken = [];

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $taken[$row->race_id . '|' . $row->scheme] = true;
        }

        $timezone = new \DateTimeZone(Factory::getApplication()->get('offset', 'UTC'));
        $done     = 0;

        foreach ($races as $race) {
            foreach (self::systems() as $scheme => $system) {
                if (isset($taken[$race->id . '|' . $scheme])) {
                    continue;
                }

                if (self::snapshotRace($race, $scheme, $system, $timezone, $now)) {
                    $done++;
                }
            }
        }

        return $done;
    }

    private static function snapshotRace(object $race, string $scheme, array $system, \DateTimeZone $timezone, string $now): bool
    {
        $db    = self::db();
        $after = self::findImport($scheme, $race->rating_deadline, true);

        if (!$after) {
            // No download since the deadline yet; the next import will pick it up.
            return false;
        }

        $before       = self::findImport($scheme, $race->rating_deadline, false);
        $deadlineDate = Factory::getDate($race->rating_deadline, 'UTC')->setTimezone($timezone)->format('Y-m-d', true);

        $rows     = self::parse(self::loadCsv((int) $after->content_id), $system);
        $previous = null;
        $final    = [];
        $reverted = 0;
        $excluded = 0;

        foreach ($rows as $row) {
            if ($row['issue_date'] !== '' && $row['issue_date'] > $deadlineDate) {
                if ($previous === null) {
                    $previous = [];

                    if ($before) {
                        foreach (self::parse(self::loadCsv((int) $before->content_id), $system) as $old) {
                            $previous[self::key($old, $system)] = $old;
                        }
                    }
                }

                $key = self::key($row, $system);

                if (isset($previous[$key])) {
                    $final[] = $previous[$key];
                    $reverted++;
                } else {
                    $excluded++;
                }

                continue;
            }

            $final[] = $row;
        }

        $note = 'Feed downloaded ' . $after->imported_at . ' UTC';

        if (!$before) {
            $note .= '; no download existed before the deadline';
        }

        if ($reverted || $excluded) {
            $note .= '; ' . $reverted . ' kept on pre-deadline rating, ' . $excluded . ' left out (first issued after deadline)';
        }

        $db->transactionStart();

        try {
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__isorairc_snapshot_ratings'))
                ->where($db->quoteName('race_id') . ' = ' . (int) $race->id)
                ->where($db->quoteName('scheme') . ' = ' . $db->quote($scheme));
            $db->setQuery($query)->execute();

            self::insertRows('#__isorairc_snapshot_ratings', $final, ['race_id' => (int) $race->id, 'scheme' => $scheme]);

            $status = (object) [
                'race_id'    => (int) $race->id,
                'scheme'     => $scheme,
                'taken'      => $now,
                'import_id'  => (int) $after->id,
                'boat_count' => \count($final),
                'note'       => $note,
            ];
            $db->insertObject('#__isorairc_race_snapshots', $status);

            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();

            throw $e;
        }

        return true;
    }

    /**
     * Discard the snapshots of the given races and take them again from the archive.
     *
     * @param int[] $raceIds
     *
     * @return int Number of snapshots taken.
     */
    public static function rebuild(array $raceIds): int
    {
        $raceIds = array_filter(array_map('intval', $raceIds));

        if (!$raceIds) {
            return 0;
        }

        self::deleteSnapshots($raceIds);

        return self::processSnapshots();
    }

    /**
     * Remove the snapshots (ratings and status) of the given races.
     *
     * @param int[] $raceIds
     */
    public static function deleteSnapshots(array $raceIds): void
    {
        $raceIds = array_filter(array_map('intval', $raceIds));

        if (!$raceIds) {
            return;
        }

        $db = self::db();

        foreach (['#__isorairc_snapshot_ratings', '#__isorairc_race_snapshots'] as $table) {
            $query = $db->getQuery(true)
                ->delete($db->quoteName($table))
                ->where($db->quoteName('race_id') . ' IN (' . implode(',', $raceIds) . ')');
            $db->setQuery($query)->execute();
        }
    }

    /**
     * Snapshot status rows, as [race id][scheme] => row.
     *
     * @param int[] $raceIds
     */
    public static function snapshotStatus(array $raceIds): array
    {
        $raceIds = array_filter(array_map('intval', $raceIds));
        $status  = [];

        if (!$raceIds) {
            return $status;
        }

        $db    = self::db();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__isorairc_race_snapshots'))
            ->where($db->quoteName('race_id') . ' IN (' . implode(',', $raceIds) . ')');

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $status[(int) $row->race_id][$row->scheme] = $row;
        }

        return $status;
    }

    /**
     * The most recent import log entry of a rating system, or null.
     */
    public static function lastImport(string $scheme): ?object
    {
        $db    = self::db();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'imported_at', 'feed_modified', 'row_count']))
            ->from($db->quoteName('#__isorairc_imports'))
            ->where($db->quoteName('scheme') . ' = ' . $db->quote($scheme))
            ->order($db->quoteName('id') . ' DESC');

        return $db->setQuery($query, 0, 1)->loadObject() ?: null;
    }

    /**
     * Format a stored rating the way its system publishes it.
     */
    public static function formatRating($value, array $system): string
    {
        return ($value === null || $value === '') ? '' : number_format((float) $value, $system['decimals'], '.', '');
    }

    /**
     * Parse a feed into rows keyed by database column. Values are strings;
     * '' means "no value" and is stored as NULL.
     *
     * @return array<int, array<string, string>>
     */
    public static function parse(string $csv, array $system): array
    {
        if (strncmp($csv, "\xEF\xBB\xBF", 3) === 0) {
            $csv = substr($csv, 3);
        }

        if (!mb_check_encoding($csv, 'UTF-8')) {
            $csv = mb_convert_encoding($csv, 'UTF-8', 'Windows-1252');
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);

        $header = fgetcsv($handle, 0, ',', '"', '\\');

        if (!$header) {
            fclose($handle);

            throw new \RuntimeException('feed is empty');
        }

        $index = array_flip(array_map('trim', $header));

        foreach ($system['required'] as $needed) {
            if (!isset($index[$needed])) {
                fclose($handle);

                throw new \RuntimeException('feed format has changed: column "' . $needed . '" is missing');
            }
        }

        $blank = array_fill_keys(self::DB_COLUMNS, '');
        $rows  = [];

        while (($line = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $row = $blank;

            foreach ($system['columns'] as $column => $heading) {
                if (isset($index[$heading], $line[$index[$heading]])) {
                    $row[$column] = mb_substr(trim((string) $line[$index[$heading]]), 0, 150);
                }
            }

            if ($row['boat_name'] === '' || !is_numeric($row['tcc'])) {
                continue;
            }

            $row['issue_date'] = self::isoDate($row['issue_date']);

            foreach (['non_spi_tcc', 'cert_year', 'crew'] as $numeric) {
                if (!is_numeric($row[$numeric])) {
                    $row[$numeric] = '';
                }
            }

            // Counts are whole numbers; anything else is stored as "no value".
            foreach (['headsails', 'flying_headsails', 'spinnakers', 'sss_base'] as $count) {
                $row[$count] = preg_match('/^\d{1,4}$/', $row[$count]) ? $row[$count] : '';
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Accepts dd/mm/yyyy (IRC feed) or yyyy-mm-dd (YTC sheet).
     */
    private static function isoDate(string $value): string
    {
        foreach (['!d/m/Y', '!Y-m-d'] as $format) {
            $date = \DateTime::createFromFormat($format, $value);

            if ($date && $date->format(substr($format, 1)) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return '';
    }

    /**
     * Identifies the same boat (and certificate slot) across downloads.
     */
    private static function key(array $row, array $system): string
    {
        $parts = [];

        foreach ($system['key'] as $column) {
            $parts[] = strtoupper(preg_replace('/\s+/', '', $row[$column]));
        }

        return implode('|', $parts);
    }

    /**
     * First import at or after a moment, or last import before it.
     */
    private static function findImport(string $scheme, string $moment, bool $after): ?object
    {
        $db    = self::db();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'imported_at', 'content_id']))
            ->from($db->quoteName('#__isorairc_imports'))
            ->where($db->quoteName('scheme') . ' = ' . $db->quote($scheme))
            ->where($db->quoteName('imported_at') . ($after ? ' >= ' : ' < ') . $db->quote($moment))
            ->order($db->quoteName('imported_at') . ($after ? ' ASC' : ' DESC') . ', ' . $db->quoteName('id') . ($after ? ' ASC' : ' DESC'));

        return $db->setQuery($query, 0, 1)->loadObject() ?: null;
    }

    private static function loadCsv(int $contentId): string
    {
        $db    = self::db();
        $query = $db->getQuery(true)
            ->select($db->quoteName('csv_gz'))
            ->from($db->quoteName('#__isorairc_imports'))
            ->where($db->quoteName('id') . ' = ' . $contentId);
        $stored = $db->setQuery($query)->loadResult();
        $csv    = $stored ? gzdecode(base64_decode($stored)) : false;

        if ($csv === false) {
            throw new \RuntimeException('Archived ratings download ' . $contentId . ' could not be read.');
        }

        return $csv;
    }

    private static function insertRows(string $table, array $rows, array $extra = []): void
    {
        $db      = self::db();
        $columns = array_merge(array_keys($extra), self::DB_COLUMNS);

        foreach (array_chunk($rows, 200) as $chunk) {
            $query = $db->getQuery(true)
                ->insert($db->quoteName($table))
                ->columns($db->quoteName($columns));

            foreach ($chunk as $row) {
                $values = [];

                foreach ($columns as $column) {
                    $value    = \array_key_exists($column, $extra) ? $extra[$column] : $row[$column];
                    $values[] = ($value === null || $value === '') ? 'NULL' : $db->quote((string) $value);
                }

                $query->values(implode(',', $values));
            }

            $db->setQuery($query)->execute();
        }
    }

    private static function db()
    {
        return Factory::getContainer()->get('DatabaseDriver');
    }
}
