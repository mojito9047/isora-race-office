<?php

namespace IsoraTests\Cases;

use IsoraTests\Browser;
use IsoraTests\Env;
use IsoraTests\TestCase;

/**
 * Run after the package has been uninstalled: nothing of it should remain.
 */
class RemovedTest extends TestCase
{
    public function testExtensionRecordsAreGone(): void
    {
        $db = Env::db();
        $this->assertCount(0, Env::rows('#__extensions', $db->quoteName('element') . ' IN (' . $db->quote('pkg_isorairc') . ',' . $db->quote('com_isorairc') . ',' . $db->quote('isorairc') . ')', 'extension_id'), 'Extension records');
    }

    public function testTablesAreGone(): void
    {
        $db     = Env::db();
        $tables = $db->getTableList();

        foreach (Env::TABLES as $table) {
            $this->assertFalse(\in_array($db->replacePrefix($table), $tables, true), $table . ' still exists');
        }
    }

    public function testFilesAreGone(): void
    {
        foreach ([JPATH_ADMINISTRATOR . '/components/com_isorairc', JPATH_SITE . '/components/com_isorairc', JPATH_PLUGINS . '/task/isorairc'] as $folder) {
            $this->assertFalse(is_dir($folder), $folder . ' still exists');
        }
    }

    public function testAdministratorMenuEntryIsGone(): void
    {
        $db = Env::db();
        $this->assertCount(0, Env::rows('#__menu', $db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_isorairc%')), 'Menu entries');
    }

    public function testTheEntryFormsTablesAreLeftAlone(): void
    {
        $db = Env::db();
        $this->assertTrue(\in_array($db->replacePrefix('#__rsform_submissions'), $db->getTableList(), true), 'RSForm tables survive the uninstall');
    }

    public function testSiteStillWorks(): void
    {
        $this->assertSame(200, (new Browser())->get('/')->status, 'Home page status');
    }
}
