<?php

namespace Isora\Component\Isorairc\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class RatingsModel extends BaseDatabaseModel
{
    public const LIMIT = 200;

    /**
     * Search one rating system by boat name or sail number, in the current
     * ratings (race 0) or in the snapshot frozen for a race.
     *
     * @return object[]
     */
    public function search(string $scheme, string $term, int $raceId = 0): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $db    = $this->getDatabase();
        $like  = $db->quote('%' . $db->escape($term, true) . '%', false);
        // Sail numbers are matched ignoring spaces, so "IRL 1234" finds "IRL1234".
        $sail  = $db->quote('%' . $db->escape(preg_replace('/\s+/', '', $term), true) . '%', false);
        $query = $db->getQuery(true)
            ->select('*')
            ->where($db->quoteName('scheme') . ' = ' . $db->quote($scheme))
            ->where(
                '(' . $db->quoteName('boat_name') . ' LIKE ' . $like
                . ' OR REPLACE(' . $db->quoteName('sail_no') . ", ' ', '') LIKE " . $sail . ')'
            )
            ->order($db->quoteName('boat_name') . ' ASC, ' . $db->quoteName('secondary') . ' ASC');

        if ($raceId > 0) {
            $query->from($db->quoteName('#__isorairc_snapshot_ratings'))
                ->where($db->quoteName('race_id') . ' = ' . $raceId);
        } else {
            $query->from($db->quoteName('#__isorairc_ratings'));
        }

        return $db->setQuery($query, 0, self::LIMIT)->loadObjectList() ?: [];
    }

    /**
     * Races that have at least one frozen snapshot, newest first. Each race
     * carries "schemes", the list of rating systems frozen for it.
     *
     * @return object[] keyed by race id
     */
    public function getRaces(): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['r.id', 'r.name', 'r.race_date', 'r.rating_deadline', 's.scheme']))
            ->from($db->quoteName('#__isorairc_races', 'r'))
            ->join('INNER', $db->quoteName('#__isorairc_race_snapshots', 's') . ' ON ' . $db->quoteName('s.race_id') . ' = ' . $db->quoteName('r.id'))
            ->order($db->quoteName('r.rating_deadline') . ' DESC, ' . $db->quoteName('r.id') . ' DESC');
        $races = [];

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            if (!isset($races[$row->id])) {
                $races[$row->id]          = $row;
                $races[$row->id]->schemes = [];
            }

            $races[$row->id]->schemes[] = $row->scheme;
        }

        return $races;
    }

    /**
     * When each rating system was last refreshed (UTC), keyed by scheme.
     *
     * @return array<string, string>
     */
    public function getLastImports(): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([$db->quoteName('scheme'), 'MAX(' . $db->quoteName('imported_at') . ') AS ' . $db->quoteName('imported_at')])
            ->from($db->quoteName('#__isorairc_imports'))
            ->group($db->quoteName('scheme'));

        return $db->setQuery($query)->loadAssocList('scheme', 'imported_at') ?: [];
    }
}
