<?php

namespace IsoraTests\Cases;

use IsoraTests\Env;
use IsoraTests\TestCase;
use Joomla\CMS\Schema\ChangeSet;

/**
 * The package installed (and then updated in place) by tests/run.sh.
 */
class InstallTest extends TestCase
{
    private function version(): string
    {
        return (string) simplexml_load_file(JPATH_ADMINISTRATOR . '/components/com_isorairc/isorairc.xml')->version;
    }

    private function extension(string $type, string $element): ?object
    {
        $db = Env::db();

        return Env::rows('#__extensions', $db->quoteName('type') . ' = ' . $db->quote($type) . ' AND ' . $db->quoteName('element') . ' = ' . $db->quote($element), 'extension_id')[0] ?? null;
    }

    public function testPackageComponentAndPluginAreInstalledAtTheSameVersion(): void
    {
        $version = $this->version();
        $this->assertMatches('/^\d+\.\d+\.\d+$/', $version, 'Version');

        foreach ([['package', 'pkg_isorairc'], ['component', 'com_isorairc'], ['plugin', 'isorairc']] as [$type, $element]) {
            $row = $this->extension($type, $element);
            $this->assertTrue($row !== null, "$type $element is installed");
            $this->assertSame(1, (int) $row->enabled, "$type $element is enabled");
            $this->assertSame($version, json_decode($row->manifest_cache)->version, "$type $element version");
        }
    }

    public function testInstalledOnlyOnce(): void
    {
        $db = Env::db();
        $this->assertCount(3, Env::rows('#__extensions', $db->quoteName('element') . ' IN (' . $db->quote('pkg_isorairc') . ',' . $db->quote('com_isorairc') . ',' . $db->quote('isorairc') . ')', 'extension_id'), 'Extension records after installing twice');
    }

    public function testNameShownToAdminsIsIsoraRaceOffice(): void
    {
        $this->assertSame('ISORA Race Office', json_decode($this->extension('package', 'pkg_isorairc')->manifest_cache)->name);
    }

    public function testAllFiveTablesExist(): void
    {
        $db     = Env::db();
        $tables = $db->getTableList();

        foreach (Env::TABLES as $table) {
            $this->assertTrue(\in_array($db->replacePrefix($table), $tables, true), $table . ' exists');
        }
    }

    public function testRacesTableHasEveryColumnTheCodeUses(): void
    {
        $columns = array_keys(Env::db()->getTableColumns('#__isorairc_races'));

        foreach (['id', 'name', 'race_date', 'deadline_days', 'rating_deadline', 'entry_field'] as $column) {
            $this->assertTrue(\in_array($column, $columns, true), 'races.' . $column);
        }
    }

    public function testRatingTablesHaveEveryRatingColumn(): void
    {
        foreach (['#__isorairc_ratings', '#__isorairc_snapshot_ratings'] as $table) {
            $columns = array_keys(Env::db()->getTableColumns($table));

            foreach (array_merge(['scheme'], \Isora\Component\Isorairc\Administrator\Helper\RatingsHelper::DB_COLUMNS) as $column) {
                $this->assertTrue(\in_array($column, $columns, true), $table . '.' . $column);
            }
        }
    }

    /** The check behind Joomla's "Database" maintenance page and its pre-update check. */
    public function testJoomlaDatabaseCheckFindsNoProblems(): void
    {
        $changes  = new ChangeSet(Env::db(), JPATH_ADMINISTRATOR . '/components/com_isorairc/sql/updates/');
        $problems = [];

        foreach ($changes->check() as $problem) {
            $problems[] = $problem->file . ': ' . $problem->updateQuery;
        }

        $this->assertSame([], $problems, 'Database problems');
    }

    public function testRecordedDatabaseVersionIsTheNewestUpdateFile(): void
    {
        $files = array_map(static function ($file) {
            return basename($file, '.sql');
        }, glob(JPATH_ADMINISTRATOR . '/components/com_isorairc/sql/updates/mysql/*.sql'));
        usort($files, 'version_compare');

        $db       = Env::db();
        $recorded = $db->setQuery(
            'SELECT ' . $db->quoteName('version_id') . ' FROM ' . $db->quoteName('#__schemas') . ' WHERE ' . $db->quoteName('extension_id') . ' = ' . (int) $this->extension('component', 'com_isorairc')->extension_id
        )->loadResult();

        $this->assertSame(end($files), $recorded);
    }

    public function testComponentsMenuHasAnEntry(): void
    {
        $db = Env::db();
        $this->assertCount(1, Env::rows('#__menu', $db->quoteName('client_id') . ' = 1 AND ' . $db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_isorairc%')), 'Administrator menu entries');
    }

    public function testFleetsheetTemplateIsInstalled(): void
    {
        $this->assertTrue(is_file(JPATH_ADMINISTRATOR . '/components/com_isorairc/resources/yb-fleetsheet-template.xlsx'));
    }

    public function testPermissionsRecordExists(): void
    {
        $db = Env::db();
        $this->assertCount(1, Env::rows('#__assets', $db->quoteName('name') . ' = ' . $db->quote('com_isorairc')), 'Permission records');
    }
}
