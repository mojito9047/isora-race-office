<?php

namespace IsoraTests\Cases;

use IsoraTests\Browser;
use IsoraTests\Env;
use IsoraTests\Response;
use IsoraTests\SiteTest;

/**
 * The administrator screens, driven through the browser like an admin would.
 */
class AdminTest extends SiteTest
{
    private const LIST = '/administrator/index.php?option=com_isorairc&view=races';

    /** One signed-in administrator session shared by the tests, to save time. */
    private static ?Browser $admin = null;

    private function admin(): Browser
    {
        if (!self::$admin) {
            self::$admin = new Browser();
            self::$admin->loginAdmin(Env::$adminUser, Env::$adminPass);
        }

        return self::$admin;
    }

    private function list(): Response
    {
        $page = $this->admin()->get(self::LIST);
        $this->assertSame(200, $page->status, 'Race list status');
        $this->assertNoPhpErrors($page, 'Race list');

        return $page;
    }

    /** Press a toolbar button on the race list. */
    private function press(string $task, array $raceIds = []): Response
    {
        $page = $this->admin()->post('/administrator/index.php?option=com_isorairc&view=races', [
            'task' => $task, 'cid' => $raceIds, 'boxchecked' => \count($raceIds), $this->admin()->token($this->list()->body) => 1,
        ]);
        $this->assertNoPhpErrors($page, 'After ' . $task);

        return $page;
    }

    /** Fill in and save the race form. $id 0 adds a race. */
    private function save(array $fields, int $id = 0): Response
    {
        $form = $this->admin()->get('/administrator/index.php?option=com_isorairc&task=' . ($id ? 'race.edit&id=' . $id : 'race.add'));
        $this->assertNoPhpErrors($form, 'Race form');

        $page = $this->admin()->post('/administrator/index.php?option=com_isorairc&layout=edit&id=' . $id, [
            'task' => 'race.save', 'jform' => $fields + ['id' => $id], $this->admin()->token($form->body) => 1,
        ]);
        $this->assertNoPhpErrors($page, 'After saving');

        return $page;
    }

    private function onlyRace(): object
    {
        $races = Env::rows('#__isorairc_races');
        $this->assertCount(1, $races, 'Races in the database');

        return $races[0];
    }

    // ------------------------------------------------------------ access

    public function testVisitorsCannotReachTheAdminScreens(): void
    {
        $page = (new Browser())->get(self::LIST);

        $this->assertContains('name="passwd"', $page->body, 'The sign-in form is shown instead');
        $this->assertNotContains('Import ratings now', $page->body);
    }

    public function testRaceManagementPermissionAloneDoesNotOpenTheAdminArea(): void
    {
        [$username, $password] = Env::user('office');
        $this->assertThrows(static function () use ($username, $password) {
            (new Browser())->loginAdmin($username, $password);
        }, 'did not work');
    }

    // ------------------------------------------------------------ race list

    public function testEmptyListExplainsWhatToDo(): void
    {
        $text = $this->list()->text();

        $this->assertContains('ISORA Race Office', $text);
        $this->assertContains('IRC: not imported yet.', $text);
        $this->assertContains('YTC: not imported yet.', $text);
        $this->assertContains('No races yet. Add a race with "New".', $text);

        foreach (['Import ratings now', 'Rebuild snapshot', 'Options'] as $button) {
            $this->assertContains($button, $text, 'Toolbar');
        }
    }

    public function testComponentsMenuUsesTheNewName(): void
    {
        $home = $this->admin()->get('/administrator/index.php');

        $this->assertContains('ISORA Race Office', $home->body);
        $this->assertNotContains('COM_ISORAIRC', $home->body, 'No untranslated labels');
        $this->assertNotContains('ISORA Ratings', $home->body, 'The old name is gone');
    }

    public function testListShowsEachRacesDeadlineAndSnapshotState(): void
    {
        Env::addRace('Future race', '2027-06-12', '2027-06-05 22:59:00', 'Race_1');
        $past = Env::addRace('Past race', '2026-05-09', '2026-05-02 22:59:00');
        $text = $this->list()->text();

        $this->assertContains('Future race 12 Jun 2027 05 Jun 2027 23:59 7 days before the race Race_1 IRC: Pending (deadline not reached) YTC: Pending (deadline not reached)', $text);
        $this->assertContains('Past race 09 May 2026 02 May 2026 23:59 7 days before the race IRC: Due (taken by the next import) YTC: Due (taken by the next import)', $text);

        Env::import('irc', Env::ircCsv(), '2026-05-03 06:00:00');
        $this->press('races.rebuild', [$past]);
        $text = $this->list()->text();
        $this->assertContains('IRC: 120 boats, taken', $text);
        $this->assertContains('Download CSV', $text);
        $this->assertContains('Feed downloaded 2026-05-03 06:00:00 UTC; no download existed before the deadline', $text);
    }

    public function testNoticeWhenEverySystemIsSwitchedOff(): void
    {
        Env::setParams(['irc_enabled' => 0, 'ytc_enabled' => 0]);

        $this->assertContains('No rating system is switched on.', $this->list()->text());
    }

    // ------------------------------------------------------------ adding and editing races

    public function testNewRaceFormOffersTheDefaultDaysAndOnlyRaceQuestions(): void
    {
        Env::standardForm();
        Env::setParams(['deadline_days' => 10]);
        $form = $this->admin()->get('/administrator/index.php?option=com_isorairc&task=race.add');

        $this->assertNoPhpErrors($form);
        $this->assertContains('New race', $form->body);
        $this->assertMatches('/name="jform\[deadline_days\]"[^>]*value="10"/', $form->body, 'Days come from the options');
        $this->assertContains('Race 1 - Dun Laoghaire to Holyhead (Race_1)', $form->body);
        $this->assertContains('Race 2 - Holyhead to Dun Laoghaire (Race_2)', $form->body);
        $this->assertNotContains('(Membership)', $form->body, 'Not a race question');
        $this->assertNotContains('(Spinnaker)', $form->body, 'Not a race question');
        $this->admin()->get('/administrator/index.php?option=com_isorairc&task=race.cancel');
    }

    public function testSavingARaceWorksOutItsDeadlineInIrishSummerTime(): void
    {
        $page = $this->save(['name' => 'Summer race', 'race_date' => '2027-06-12', 'deadline_days' => 7, 'entry_field' => 'Race_1']);
        $race = $this->onlyRace();

        $this->assertContains('Race saved.', $page->body);
        $this->assertSame('Summer race', $race->name);
        $this->assertSame('2027-06-12', $race->race_date);
        $this->assertSame(7, (int) $race->deadline_days);
        $this->assertSame('Race_1', $race->entry_field);
        // 23:59 on 5 June in Ireland is 22:59 UTC.
        $this->assertSame('2027-06-05 22:59:00', $race->rating_deadline);
    }

    public function testSavingARaceWorksOutItsDeadlineInWinterTime(): void
    {
        $this->save(['name' => 'Winter race', 'race_date' => '2027-01-16', 'deadline_days' => 7, 'entry_field' => '']);

        $this->assertSame('2027-01-09 23:59:00', $this->onlyRace()->rating_deadline);
    }

    public function testDeadlineAcrossTheClockChange(): void
    {
        // Race on Saturday 3 April 2027; clocks go forward on 28 March. Seven days before is 27 March, still winter time.
        $this->save(['name' => 'Spring race', 'race_date' => '2027-04-03', 'deadline_days' => 7, 'entry_field' => '']);

        $this->assertSame('2027-03-27 23:59:00', $this->onlyRace()->rating_deadline);
    }

    public function testNumberOfDaysCanBeDifferentForEachRace(): void
    {
        $this->save(['name' => 'Long notice', 'race_date' => '2027-06-12', 'deadline_days' => 14, 'entry_field' => '']);
        $this->assertSame('2027-05-29 22:59:00', $this->onlyRace()->rating_deadline);

        $this->save(['name' => 'Same day', 'race_date' => '2027-06-12', 'deadline_days' => 0, 'entry_field' => ''], (int) $this->onlyRace()->id);
        $this->assertSame('2027-06-12 22:59:00', $this->onlyRace()->rating_deadline, 'Zero days: the evening of the race day itself');
    }

    public function testTimeOfDayComesFromTheOptions(): void
    {
        Env::setParams(['deadline_time' => '18:00']);
        $this->save(['name' => 'Early cut-off', 'race_date' => '2027-06-12', 'deadline_days' => 7, 'entry_field' => '']);
        $this->assertSame('2027-06-05 17:00:00', $this->onlyRace()->rating_deadline);

        Env::setParams(['deadline_time' => 'teatime']);
        $this->save(['name' => 'Early cut-off', 'race_date' => '2027-06-12', 'deadline_days' => 7, 'entry_field' => ''], (int) $this->onlyRace()->id);
        $this->assertSame('2027-06-05 22:59:00', $this->onlyRace()->rating_deadline, 'A nonsense time falls back to 23:59');
    }

    public function testEditingARaceRecalculatesItsDeadline(): void
    {
        $this->save(['name' => 'Movable', 'race_date' => '2027-06-12', 'deadline_days' => 7, 'entry_field' => '']);
        $id   = (int) $this->onlyRace()->id;
        $form = $this->admin()->get('/administrator/index.php?option=com_isorairc&task=race.edit&id=' . $id);

        $this->assertContains('Edit race', $form->body);
        $this->assertContains('Current rating deadline: 05 Jun 2027 23:59', $form->text());
        $this->admin()->get('/administrator/index.php?option=com_isorairc&task=race.cancel&id=' . $id);

        $this->save(['name' => 'Moved', 'race_date' => '2027-07-10', 'deadline_days' => 7, 'entry_field' => 'Race_2'], $id);
        $race = $this->onlyRace();

        $this->assertSame($id, (int) $race->id, 'The same race, not a copy');
        $this->assertSame('Moved', $race->name);
        $this->assertSame('2027-07-03 22:59:00', $race->rating_deadline);
        $this->assertSame('Race_2', $race->entry_field);
    }

    public function testRaceWithoutANameIsNotSaved(): void
    {
        $this->save(['name' => '', 'race_date' => '2027-06-12', 'deadline_days' => 7, 'entry_field' => '']);
        $this->assertCount(0, Env::rows('#__isorairc_races'));
        $this->admin()->get('/administrator/index.php?option=com_isorairc&task=race.cancel');
    }

    public function testRaceWithoutADateIsNotSaved(): void
    {
        $this->save(['name' => 'No date', 'race_date' => '', 'deadline_days' => 7, 'entry_field' => '']);
        $this->assertCount(0, Env::rows('#__isorairc_races'));
        $this->admin()->get('/administrator/index.php?option=com_isorairc&task=race.cancel');
    }

    public function testDeletingARaceAlsoDeletesItsFrozenRatings(): void
    {
        $doomed = Env::addRace('Doomed', '2026-05-09', '2026-05-02 22:59:00');
        $kept   = Env::addRace('Kept', '2026-05-23', '2026-05-16 22:59:00');
        Env::import('irc', Env::ircCsv(), '2026-05-17 06:00:00');
        $this->press('races.rebuild', [$doomed, $kept]);
        $this->assertCount(240, Env::rows('#__isorairc_snapshot_ratings'), 'Both races frozen first');

        $page = $this->press('races.delete', [$doomed]);

        $this->assertContains('Race deleted.', $page->body);
        $this->assertSame(['Kept'], array_column(Env::rows('#__isorairc_races'), 'name'));
        $this->assertCount(0, Env::rows('#__isorairc_snapshot_ratings', 'race_id = ' . $doomed), 'Frozen ratings of the deleted race');
        $this->assertCount(0, Env::rows('#__isorairc_race_snapshots', 'race_id = ' . $doomed, 'race_id'));
        $this->assertCount(120, Env::rows('#__isorairc_snapshot_ratings', 'race_id = ' . $kept), 'Frozen ratings of the other race');
    }

    // ------------------------------------------------------------ toolbar buttons

    public function testImportRatingsNowButton(): void
    {
        $page = $this->press('races.import');
        $text = $page->text();

        $this->assertContains('IRC: 120 boats imported; YTC: 110 boats imported; 0 race snapshot(s) taken.', $page->body);
        $this->assertContains('IRC: last imported', $text);
        $this->assertContains('(120 boats', $text);
        $this->assertCount(230, Env::rows('#__isorairc_ratings'));
    }

    public function testImportButtonReportsAListThatFailed(): void
    {
        Env::feed('irc', '', 500);
        $page = $this->press('races.import');

        $this->assertContains('IRC import FAILED: feed returned HTTP 500', $page->body);
        $this->assertContains('YTC: 110 boats imported', $page->body);
    }

    public function testImportButtonAlsoFreezesRacesThatAreDue(): void
    {
        $race = Env::addRace('Due race', Env::localDate(Env::utc('-1 day')), Env::utc('-8 days'));
        $page = $this->press('races.import');

        $this->assertContains('2 race snapshot(s) taken', $page->body);
        $this->assertCount(230, Env::rows('#__isorairc_snapshot_ratings', 'race_id = ' . $race));
    }

    public function testButtonsDoNothingWithoutAGenuineForm(): void
    {
        $this->admin()->post('/administrator/index.php?option=com_isorairc&view=races', ['task' => 'races.import', 'a0000000000000000000000000000000' => 1]);

        $this->assertCount(0, Env::rows('#__isorairc_ratings'), 'A forged request imports nothing');
    }

    public function testRebuildSnapshotButton(): void
    {
        $race = Env::addRace('Past race', '2026-05-09', '2026-05-02 22:59:00');
        Env::import('irc', Env::ircCsv([['Boat Name' => 'Steady', 'Sail No' => 'IRL1', 'TCC' => '1.000']]), '2026-05-03 06:00:00');
        $this->press('races.rebuild', [$race]);
        $db = Env::db();
        $db->setQuery('UPDATE ' . $db->quoteName('#__isorairc_snapshot_ratings') . ' SET tcc = 9.999')->execute();

        $page = $this->press('races.rebuild', [$race]);

        $this->assertContains('1 race snapshot(s) rebuilt from the archive.', $page->body);
        $this->assertNumber(1.000, Env::rating('irc', 'Steady', $race)->tcc);
    }

    public function testFrozenRatingsCanBeDownloadedAsCsv(): void
    {
        $race = Env::addRace('Past race', '2026-05-09', '2026-05-02 22:59:00');
        Env::addRating('irc', 'Sea Breeze', 'IRL1234', 1.05, 1.021, ['cert_no' => '4411', 'issue_date' => '2026-03-15'], $race);
        Env::addRating('ytc', 'Slow Coach', 'GBR55', 845, 820, ['club' => 'Holyhead SC'], $race);

        $irc = $this->admin()->get('/administrator/index.php?option=com_isorairc&task=races.export&id=' . $race . '&scheme=irc');
        $this->assertContains('text/csv', $irc->header('content-type'));
        $this->assertContains('filename="irc-ratings-race-' . $race . '.csv"', $irc->header('content-disposition'));
        $lines = preg_split('/\R/', trim($irc->body));
        $this->assertSame('"Boat Name","Sail No","Cert No","Issue Date","Cert Year",TCC,Endorsed,Secondary,"Non Spi TCC",Crew,ValidCode', $lines[0]);
        $this->assertSame('"Sea Breeze",IRL1234,4411,2026-03-15,,1.050,,,1.021,,', $lines[1]);
        $this->assertCount(2, $lines, 'Only this system\'s boats');

        $ytc = $this->admin()->get('/administrator/index.php?option=com_isorairc&task=races.export&id=' . $race . '&scheme=ytc');
        $this->assertContains('"Slow Coach",GBR55,,845,820,"Holyhead SC"', $ytc->body, 'YTC in whole numbers');

        $this->assertSame(404, $this->admin()->get('/administrator/index.php?option=com_isorairc&task=races.export&id=' . $race . '&scheme=xyz')->status);
    }

    public function testFrozenRatingsDownloadNeedsAnAdminSignIn(): void
    {
        $race = Env::addRace('Past race', '2026-05-09', '2026-05-02 22:59:00');
        Env::addRating('irc', 'Sea Breeze', 'IRL1234', 1.05, null, [], $race);
        $page = (new Browser())->get('/administrator/index.php?option=com_isorairc&task=races.export&id=' . $race . '&scheme=irc');

        $this->assertNotContains('Sea Breeze', $page->body);
    }

    // ------------------------------------------------------------ options

    public function testOptionsScreenHasEveryTabWithItsDefaults(): void
    {
        $db = Env::db();
        $db->setQuery('UPDATE ' . $db->quoteName('#__extensions') . ' SET ' . $db->quoteName('params') . " = '{}' WHERE " . $db->quoteName('element') . " = 'com_isorairc'")->execute();
        $page = $this->admin()->get('/administrator/index.php?option=com_config&view=component&component=com_isorairc');

        $this->assertSame(200, $page->status);
        $this->assertNoPhpErrors($page, 'Options screen');
        $this->assertContains('ISORA Race Office: Options', $page->body);

        foreach (['Rating sources', 'Rating deadline', 'Entry form', 'YB fleetsheet', 'Permissions'] as $tab) {
            $this->assertContains($tab, $page->body, 'Tab');
        }

        foreach (['https://www.topyacht.com.au/rorc/data/ClubListing.csv', 'https://ircrating.org/boat-data-for-valid-irc-certificates/', '23:59', 'Boat_Name', 'Race_Entry_Categoy', 'MEDIUM MONOHULL', 'IRC Class 0=1.070', 'Wales=XW', 'Upload_Yacht_Photograph_'] as $default) {
            $this->assertContains($default, $page->body, 'Default value');
        }

        $this->admin()->get('/administrator/index.php?option=com_config&task=component.cancel&component=com_isorairc');
    }
}
