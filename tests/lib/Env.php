<?php

/**
 * Everything the tests need to set a scene on the throwaway test site:
 * settings, rating feeds, races, ratings, an entry form with entries, and users.
 */

namespace IsoraTests;

use Isora\Component\Isorairc\Administrator\Helper\RatingsHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Asset;
use Joomla\CMS\Table\Usergroup;
use Joomla\CMS\User\User;

class Env
{
    /** The entry form's ID in the fixtures (the 2027 form on the real site is also 13). */
    public const FORM_ID = 13;

    public const OFFICE_GROUP = 'ISORA test race office';

    public const USER_PREFIX = 'isoratest_';

    public const TABLES = [
        '#__isorairc_ratings', '#__isorairc_snapshot_ratings', '#__isorairc_race_snapshots',
        '#__isorairc_races', '#__isorairc_imports', '#__isorairc_cert_choices',
    ];

    public const FORM_TABLES = [
        '#__rsform_submissions', '#__rsform_submission_values', '#__rsform_components',
        '#__rsform_properties', '#__rsform_component_types',
    ];

    /** Address of the test site, e.g. http://127.0.0.1:8089 */
    public static string $siteUrl = '';

    /** Address and folder of the stand-in rating feeds. */
    public static string $feedUrl = '';

    public static string $feedDir = '';

    public static string $adminUser = '';

    public static string $adminPass = '';

    /** Passwords of the users made by user(), by role. */
    private static array $users = [];

    private static int $componentOrder = 0;

    public static function db()
    {
        return Factory::getContainer()->get('DatabaseDriver');
    }

    /** Add a row to a table. */
    public static function insert(string $table, array $data): void
    {
        $row = (object) $data;
        self::db()->insertObject($table, $row);
    }

    /** The site's time zone. */
    public static function timezone(): \DateTimeZone
    {
        return new \DateTimeZone(Factory::getApplication()->get('offset', 'UTC'));
    }

    /**
     * Back to an empty extension: no races, ratings, downloads or entries,
     * default settings, and both feeds serving a healthy list.
     */
    public static function reset(): void
    {
        $db = self::db();

        foreach (array_merge(self::TABLES, self::FORM_TABLES) as $table) {
            $db->setQuery('DELETE FROM ' . $db->quoteName($table))->execute();
        }

        $db->setQuery('DELETE FROM ' . $db->quoteName('#__scheduler_tasks') . ' WHERE ' . $db->quoteName('type') . ' = ' . $db->quote('isorairc.import'))->execute();

        foreach ([1 => 'textBox', 3 => 'selectList', 4 => 'checkboxGroup', 5 => 'radioGroup', 9 => 'fileUpload'] as $id => $name) {
            self::insert('#__rsform_component_types', ['ComponentTypeId' => $id, 'ComponentTypeName' => $name, 'CanBeDuplicated' => 1]);
        }

        self::$componentOrder = 0;
        self::setParams([]);
        self::feed('irc', self::ircCsv());
        self::feed('ytc', self::ytcCsv());
    }

    /**
     * Set the extension's options. Anything not given keeps its built-in
     * default, except the feed addresses (the stand-in feeds) and the entry form.
     */
    public static function setParams(array $params): void
    {
        $params += [
            'feed_url'      => self::$feedUrl . '/irc.csv',
            'ytc_url'       => self::$feedUrl . '/ytc.csv',
            'entry_form_id' => self::FORM_ID,
        ];

        $db    = self::db();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = ' . $db->quote(json_encode($params)))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_isorairc'));
        $db->setQuery($query)->execute();

        // Joomla remembers the options for the length of a request; make it read them again.
        $property = new \ReflectionProperty(ComponentHelper::class, 'components');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }

    // ---------------------------------------------------------------- feeds

    /**
     * What a stand-in feed serves from now on.
     */
    public static function feed(string $scheme, string $csv, int $status = 200, ?string $lastModified = null): void
    {
        file_put_contents(self::$feedDir . '/' . $scheme . '.csv', $csv);
        file_put_contents(self::$feedDir . '/' . $scheme . '.json', json_encode(['status' => $status, 'last_modified' => $lastModified]));
    }

    /**
     * An IRC list in the published layout. Each boat is an array keyed by
     * column heading; missing columns are left empty. "Filler" boats are added
     * so the list passes the "at least 100 boats" check.
     */
    public static function ircCsv(array $boats = [], int $filler = 120): string
    {
        $columns = [
            'Boat Name', 'Sail No', 'Cert No', 'Issue Date', 'Cert Year', 'TCC', 'Endorsed', 'Secondary', 'Non Spi TCC', 'Crew', 'DLR', 'LH', 'Beam', 'Draft',
            'Single Furling Headsail', 'Headsails', 'Flying Headsails', 'Spinnakers', 'Series Date', 'Age Date', 'Racing Area', 'SSS Base Value', 'STIX', 'AVS', 'Category', 'ValidCode',
        ];

        for ($i = 1; $i <= $filler; $i++) {
            $boats[] = [
                'Boat Name' => sprintf('Filler %03d', $i), 'Sail No' => sprintf('FIL%03d', $i), 'Cert No' => (string) (9000 + $i),
                'Issue Date' => '02/01/2026', 'Cert Year' => '2026', 'TCC' => sprintf('%.3f', 0.9 + $i / 1000), 'Crew' => '8',
            ];
        }

        return self::csv($columns, $boats);
    }

    /** A YTC list in the layout of the published sheet. */
    public static function ytcCsv(array $boats = [], int $filler = 110): string
    {
        $columns = ['EntryID', 'Boat Name', 'Sail Number', 'Boat Type', 'YTC', 'YTC White Sail', 'Club', 'Provisional', 'Issue Date'];

        for ($i = 1; $i <= $filler; $i++) {
            $boats[] = [
                'EntryID' => (string) (5000 + $i), 'Boat Name' => sprintf('Cruiser %03d', $i), 'Sail Number' => sprintf('CR%03d', $i),
                'Boat Type' => 'Test 30', 'YTC' => (string) (800 + $i), 'YTC White Sail' => (string) (780 + $i), 'Club' => 'Test SC',
                'Provisional' => 'FALSE', 'Issue Date' => '2026-01-02',
            ];
        }

        return self::csv($columns, $boats);
    }

    public static function csv(array $columns, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $columns, ',', '"', '\\');

        foreach ($rows as $row) {
            $line = [];

            foreach ($columns as $column) {
                $line[] = $row[$column] ?? '';
            }

            fputcsv($handle, $line, ',', '"', '\\');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Download a list as the extension would, then (optionally) pretend the
     * download happened at another moment (UTC, "Y-m-d H:i:s").
     *
     * @return int The download's ID in the import log
     */
    public static function import(string $scheme, string $csv, ?string $at = null): int
    {
        self::feed($scheme, $csv);
        RatingsHelper::import($scheme, RatingsHelper::systems(false)[$scheme]);

        $db = self::db();
        $id = (int) $db->setQuery('SELECT MAX(' . $db->quoteName('id') . ') FROM ' . $db->quoteName('#__isorairc_imports'))->loadResult();

        if ($at !== null) {
            $db->setQuery(
                'UPDATE ' . $db->quoteName('#__isorairc_imports') . ' SET ' . $db->quoteName('imported_at') . ' = ' . $db->quote($at)
                . ' WHERE ' . $db->quoteName('id') . ' = ' . $id
            )->execute();
        }

        return $id;
    }

    /** A moment relative to now, in UTC, as stored in the database. */
    public static function utc(string $relative): string
    {
        return (new \DateTime($relative, new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }

    /** The calendar date of a UTC moment in the site's time zone, shifted by whole days. */
    public static function localDate(string $utc, int $days = 0, string $format = 'Y-m-d'): string
    {
        $date = new \DateTime($utc, new \DateTimeZone('UTC'));
        $date->setTimezone(self::timezone());

        if ($days) {
            $date->modify(($days > 0 ? '+' : '') . $days . ' days');
        }

        return $date->format($format);
    }

    // ---------------------------------------------------------------- races and ratings

    /**
     * Add a race straight to the database.
     *
     * @return int The race's ID
     */
    public static function addRace(string $name, string $raceDate, string $deadlineUtc, ?string $entryField = null, int $days = 7): int
    {
        $race = (object) [
            'name' => $name, 'race_date' => $raceDate, 'deadline_days' => $days,
            'rating_deadline' => $deadlineUtc, 'entry_field' => $entryField,
        ];
        self::db()->insertObject('#__isorairc_races', $race, 'id');

        return (int) $race->id;
    }

    public static function race(int $id): object
    {
        $db = self::db();

        return $db->setQuery('SELECT * FROM ' . $db->quoteName('#__isorairc_races') . ' WHERE ' . $db->quoteName('id') . ' = ' . $id)->loadObject();
    }

    /**
     * Put one boat straight into the current ratings, or into a race's frozen
     * ratings when a race ID is given.
     */
    public static function addRating(string $scheme, string $boat, string $sail, ?float $tcc, ?float $nonSpi = null, array $more = [], int $raceId = 0): void
    {
        $row = (object) (['scheme' => $scheme, 'boat_name' => $boat, 'sail_no' => $sail, 'tcc' => $tcc, 'non_spi_tcc' => $nonSpi] + $more);

        if ($raceId) {
            $row->race_id = $raceId;
        }

        self::db()->insertObject($raceId ? '#__isorairc_snapshot_ratings' : '#__isorairc_ratings', $row);
    }

    /** Mark a race's ratings as frozen for a rating system. */
    public static function freeze(int $raceId, string $scheme): void
    {
        self::insert('#__isorairc_race_snapshots', [
            'race_id' => $raceId, 'scheme' => $scheme, 'taken' => self::utc('now'), 'boat_count' => 0, 'note' => 'test',
        ]);
    }

    /** @return object[] */
    public static function rows(string $table, string $where = '1 = 1', string $order = 'id'): array
    {
        $db = self::db();

        return $db->setQuery('SELECT * FROM ' . $db->quoteName($table) . ' WHERE ' . $where . ' ORDER BY ' . $order)->loadObjectList();
    }

    /** One boat's row from the current or a race's frozen ratings, or null. */
    public static function rating(string $scheme, string $boat, int $raceId = 0): ?object
    {
        $db    = self::db();
        $where = $db->quoteName('scheme') . ' = ' . $db->quote($scheme) . ' AND ' . $db->quoteName('boat_name') . ' = ' . $db->quote($boat)
            . ($raceId ? ' AND ' . $db->quoteName('race_id') . ' = ' . $raceId : '');

        return self::rows($raceId ? '#__isorairc_snapshot_ratings' : '#__isorairc_ratings', $where)[0] ?? null;
    }

    // ---------------------------------------------------------------- the entry form

    /**
     * RSForm! Pro is a paid extension and is not on the test site, so the
     * tests make the five of its tables that the extension reads, laid out
     * as on the real site.
     */
    public static function createFormTables(): void
    {
        $db = self::db();

        $tables = [
            'CREATE TABLE IF NOT EXISTS `#__rsform_submissions` (
                `SubmissionId` int NOT NULL AUTO_INCREMENT, `FormId` int NOT NULL DEFAULT 0, `DateSubmitted` datetime NOT NULL,
                `UserIp` varchar(255) NOT NULL DEFAULT \'\', `Username` varchar(255) NOT NULL DEFAULT \'\', `UserId` int NOT NULL DEFAULT 0,
                `Lang` varchar(255) NOT NULL DEFAULT \'\', `confirmed` tinyint NOT NULL DEFAULT 0, `SubmissionHash` varchar(32) NOT NULL DEFAULT \'\',
                `ConfirmedIp` varchar(255) NOT NULL DEFAULT \'\', `ConfirmedDate` datetime DEFAULT NULL,
                PRIMARY KEY (`SubmissionId`), KEY `FormId` (`FormId`))',
            'CREATE TABLE IF NOT EXISTS `#__rsform_submission_values` (
                `SubmissionValueId` int NOT NULL AUTO_INCREMENT, `FormId` int NOT NULL, `SubmissionId` int NOT NULL DEFAULT 0,
                `FieldName` text NOT NULL, `FieldValue` mediumtext DEFAULT NULL,
                PRIMARY KEY (`SubmissionValueId`), KEY `FormId` (`FormId`), KEY `SubmissionId` (`SubmissionId`))',
            'CREATE TABLE IF NOT EXISTS `#__rsform_components` (
                `ComponentId` int NOT NULL AUTO_INCREMENT, `FormId` int NOT NULL DEFAULT 0, `ComponentTypeId` int NOT NULL DEFAULT 0,
                `Order` int NOT NULL DEFAULT 0, `Published` tinyint NOT NULL DEFAULT 1,
                UNIQUE KEY `ComponentId` (`ComponentId`), KEY `FormId` (`FormId`))',
            'CREATE TABLE IF NOT EXISTS `#__rsform_properties` (
                `PropertyId` int NOT NULL AUTO_INCREMENT, `ComponentId` int NOT NULL DEFAULT 0, `PropertyName` text NOT NULL,
                `PropertyValue` mediumtext DEFAULT NULL,
                UNIQUE KEY `PropertyId` (`PropertyId`), KEY `ComponentId` (`ComponentId`))',
            'CREATE TABLE IF NOT EXISTS `#__rsform_component_types` (
                `ComponentTypeId` int NOT NULL AUTO_INCREMENT, `ComponentTypeName` text NOT NULL, `CanBeDuplicated` tinyint NOT NULL DEFAULT 1,
                PRIMARY KEY (`ComponentTypeId`))',
        ];

        foreach ($tables as $sql) {
            $db->setQuery($sql . ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci')->execute();
        }
    }

    public static function dropFormTables(): void
    {
        $db = self::db();

        foreach (self::FORM_TABLES as $table) {
            $db->setQuery('DROP TABLE IF EXISTS ' . $db->quoteName($table))->execute();
        }
    }

    /**
     * Add a question to the entry form.
     *
     * @param string $type  radioGroup, selectList, checkboxGroup, textBox or fileUpload
     * @param string $items The answers offered, one per line, as RSForm stores them
     */
    public static function question(string $name, string $caption, string $type = 'radioGroup', string $items = "Yes\nNo", int $formId = self::FORM_ID): void
    {
        $db     = self::db();
        $typeId = ['textBox' => 1, 'selectList' => 3, 'checkboxGroup' => 4, 'radioGroup' => 5, 'fileUpload' => 9][$type];

        $component = (object) ['FormId' => $formId, 'ComponentTypeId' => $typeId, 'Order' => ++self::$componentOrder, 'Published' => 1];
        $db->insertObject('#__rsform_components', $component, 'ComponentId');

        foreach (['NAME' => $name, 'CAPTION' => $caption, 'ITEMS' => $items] as $property => $value) {
            if ($property === 'ITEMS' && $type === 'textBox') {
                continue;
            }

            self::insert('#__rsform_properties', [
                'ComponentId' => $component->ComponentId, 'PropertyName' => $property, 'PropertyValue' => $value,
            ]);
        }
    }

    /** A small entry form: two race questions and two questions that are not about races. */
    public static function standardForm(): void
    {
        self::question('Boat_Name', 'Boat name', 'textBox');
        self::question('Membership', 'Membership type', 'radioGroup', "Full\nAssociate");
        self::question('Race_1', 'Race 1 - Dun Laoghaire to Holyhead');
        self::question('Race_2', 'Race 2 - Holyhead to Dun Laoghaire', 'radioGroup', "No[c]\nYes|Yes please");
        self::question('Spinnaker', 'Flying a spinnaker?', 'selectList');
    }

    /** One boat's answers on the entry form, with sensible values for anything not given. */
    public static function boat(array $answers = []): array
    {
        return $answers + [
            'Boat_Name'                      => 'Test Boat',
            'Sail_Number'                    => 'IRL 1234',
            'Manufacturer_Model'             => 'J/109',
            'Class_Racing'                   => 'IRC',
            'Sailing_Club'                   => 'Test Yacht Club',
            'Latest_IRC_TCC'                 => '1.010',
            'FullName'                       => 'Pat Skipper',
            'Email'                          => 'pat.skipper@example.invalid',
            'Mobile_Number_for_Main_Contact' => '+353 85 555 0100',
            'Race_Entry_Categoy'             => 'Season entry (all races)',
            'Country'                        => 'Ireland',
            'Upload_Yacht_Photograph_'       => '',
            'Race_1'                         => 'Yes',
            'Race_2'                         => 'No',
        ];
    }

    /**
     * Send the entry form.
     *
     * @param array  $answers Field name => answer, usually from boat()
     * @param string $date    When it was sent (UTC)
     *
     * @return int The form's submission ID
     */
    public static function submit(array $answers, string $date = '2026-10-01 10:00:00', int $formId = self::FORM_ID): int
    {
        $db         = self::db();
        $submission = (object) ['FormId' => $formId, 'DateSubmitted' => $date, 'Lang' => 'en-GB', 'confirmed' => 1];
        $db->insertObject('#__rsform_submissions', $submission, 'SubmissionId');

        foreach ($answers as $name => $value) {
            self::insert('#__rsform_submission_values', [
                'FormId' => $formId, 'SubmissionId' => $submission->SubmissionId, 'FieldName' => $name, 'FieldValue' => $value,
            ]);
        }

        return (int) $submission->SubmissionId;
    }

    // ---------------------------------------------------------------- menu items

    /**
     * Add one of the extension's pages to the site's main menu, as an admin
     * would under Menus. The page is then at /<alias>.
     *
     * @param string $view "ratings" or "entries"
     */
    public static function menuItem(string $title, string $alias, string $view): int
    {
        $db          = self::db();
        $componentId = (int) $db->setQuery(
            'SELECT ' . $db->quoteName('extension_id') . ' FROM ' . $db->quoteName('#__extensions') . ' WHERE ' . $db->quoteName('type') . ' = ' . $db->quote('component')
            . ' AND ' . $db->quoteName('element') . ' = ' . $db->quote('com_isorairc')
        )->loadResult();

        $item = new \Joomla\Component\Menus\Administrator\Table\MenuTable($db);
        $item->setLocation(1, 'last-child');
        $data = [
            'menutype' => 'mainmenu', 'title' => $title, 'alias' => $alias, 'type' => 'component', 'published' => 1,
            'link' => 'index.php?option=com_isorairc&view=' . $view, 'component_id' => $componentId, 'access' => 1,
            'language' => '*', 'client_id' => 0, 'params' => '{}', 'img' => '', 'note' => '', 'path' => $alias,
        ];

        if (!$item->bind($data) || !$item->check() || !$item->store() || !$item->rebuildPath($item->id)) {
            throw new \RuntimeException('Could not make the menu item: ' . $item->getError());
        }

        return (int) $item->id;
    }

    /** Remove the menu items made by menuItem(). */
    public static function deleteMenuItems(): void
    {
        $db  = self::db();
        $ids = $db->setQuery(
            'SELECT ' . $db->quoteName('id') . ' FROM ' . $db->quoteName('#__menu') . ' WHERE ' . $db->quoteName('client_id') . ' = 0 AND '
            . $db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_isorairc%')
        )->loadColumn();

        foreach ($ids as $id) {
            (new \Joomla\Component\Menus\Administrator\Table\MenuTable($db))->delete((int) $id);
        }
    }

    // ---------------------------------------------------------------- users

    /**
     * A test user, made on first use: "registered" is an ordinary member and
     * "office" is in a group that has been given "Access Administration
     * Interface" for the extension only.
     *
     * @return array{0: string, 1: string} Username and password
     */
    public static function user(string $role): array
    {
        $username = self::USER_PREFIX . $role;

        if (isset(self::$users[$role])) {
            return [$username, self::$users[$role]];
        }

        $db      = self::db();
        $groupId = 2; // Registered

        if ($role === 'office') {
            $groupId = (int) $db->setQuery(
                'SELECT ' . $db->quoteName('id') . ' FROM ' . $db->quoteName('#__usergroups') . ' WHERE ' . $db->quoteName('title') . ' = ' . $db->quote(self::OFFICE_GROUP)
            )->loadResult();

            if (!$groupId) {
                $group = new Usergroup($db);

                if (!$group->save(['title' => self::OFFICE_GROUP, 'parent_id' => 2])) {
                    throw new \RuntimeException('Could not make the test user group: ' . $group->getError());
                }

                $groupId = (int) $group->id;
            }

            $asset = new Asset($db);

            if (!$asset->loadByName('com_isorairc')) {
                throw new \RuntimeException('The extension has no permissions record.');
            }

            $rules = json_decode((string) $asset->rules, true) ?: [];
            $rules['core.manage'][$groupId] = 1;
            $asset->rules = json_encode($rules);
            $asset->store();
        }

        $password = bin2hex(random_bytes(12)) . 'aA1!';
        $user     = new User();
        $data     = [
            'name' => 'Test ' . $role, 'username' => $username, 'email' => $username . '@example.invalid',
            'password' => $password, 'password2' => $password, 'groups' => [$groupId], 'block' => 0, 'requireReset' => 0,
        ];

        if (!$user->bind($data) || !$user->save()) {
            throw new \RuntimeException('Could not make the test user: ' . $user->getError());
        }

        self::$users[$role] = $password;

        return [$username, $password];
    }

    /** Remove the users and the group made by user(), including any left by an earlier run. */
    public static function deleteUsers(): void
    {
        $db  = self::db();
        $ids = $db->setQuery(
            'SELECT ' . $db->quoteName('id') . ' FROM ' . $db->quoteName('#__users') . ' WHERE ' . $db->quoteName('username') . ' LIKE ' . $db->quote(self::USER_PREFIX . '%')
        )->loadColumn();

        foreach ($ids as $id) {
            foreach (['#__users' => 'id', '#__user_usergroup_map' => 'user_id', '#__session' => 'userid'] as $table => $column) {
                $db->setQuery('DELETE FROM ' . $db->quoteName($table) . ' WHERE ' . $db->quoteName($column) . ' = ' . (int) $id)->execute();
            }
        }

        self::$users = [];
    }
}
