<?php

namespace IsoraTests\Cases;

use IsoraTests\Browser;
use IsoraTests\Env;
use IsoraTests\Response;
use IsoraTests\SiteTest;

/**
 * The Race entries page and its downloads, as the public, a signed-in
 * member and race management each see them.
 */
class EntriesPageTest extends SiteTest
{
    /** The page's address: its menu item, as set up in the admin guide. */
    private const PAGE = '/race-entries';

    private const PRIVATE_DETAILS = ['pat.skipper@example.invalid', '555 0100', 'Pat Skipper', 'On form', 'Download CSV', 'Download YB fleetsheet', 'Season entry', 'Boat data', 'pdf_search', 'ircrating.org'];

    private int $race;

    protected function setUp(): void
    {
        parent::setUp();
        Env::standardForm();
        $this->race = Env::addRace('Race 1', '2027-05-01', '2027-04-24 22:59:00', 'Race_1');
    }

    private function browser(string $role = ''): Browser
    {
        $browser = new Browser();

        if ($role !== '') {
            $browser->loginSite(...Env::user($role));
        }

        return $browser;
    }

    private function page(Browser $browser, ?int $race = null): Response
    {
        $page = $browser->get(self::PAGE . ($race === 0 ? '' : '?race=' . ($race ?? $this->race)));
        $this->assertSame(200, $page->status, 'Page status');
        $this->assertNoPhpErrors($page);

        $part = new Response();
        $part->body = $page->component('com-isorairc-entries');
        $this->assertTrue($part->body !== '', 'The entry list is on the page');

        return $part;
    }

    /** The address behind one of the download buttons. */
    private function link(Response $page, string $label): string
    {
        if (!preg_match('#<a class="isorairc-button" href="([^"]+)">' . preg_quote($label, '#') . '</a>#', $page->body, $match)) {
            $this->fail('No "' . $label . '" button on the page');
        }

        return html_entity_decode($match[1]);
    }

    /** @return array<int, string[]> */
    private function csv(string $body): array
    {
        $rows = [];

        foreach (preg_split('/\R/', trim($body)) as $line) {
            $rows[] = str_getcsv($line, ',', '"', '\\');
        }

        return $rows;
    }

    private function twoBoats(): void
    {
        Env::addRating('irc', 'Sea Breeze', 'IRL1234', 1.045);
        Env::submit(Env::boat(['Boat_Name' => 'Sea Breeze', 'Sail_Number' => 'IRL 1234', 'Latest_IRC_TCC' => '1.039']));
        Env::submit(Env::boat(['Boat_Name' => 'Slow Coach', 'Sail_Number' => 'GBR 55', 'Class_Racing' => 'YTC', 'Manufacturer_Model' => 'Sadler 32']));
    }

    // ------------------------------------------------------------ what the public sees

    public function testPublicSeesTheBoatsEnteredWithTheirRatings(): void
    {
        $this->twoBoats();
        $text = $this->page($this->browser())->text();

        $this->assertContains('2 boats entered: IRC 1, YTC 1.', $text);
        $this->assertContains('Race date 01 May 2027; rating deadline 24 Apr 2027 23:59.', $text);
        $this->assertContains('Sea Breeze IRL 1234 J/109 IRC Test Yacht Club 1.045', $text);
        $this->assertContains('Slow Coach GBR 55 Sadler 32 YTC Test Yacht Club Not listed', $text);
    }

    public function testPublicDoesNotSeeContactDetailsOrDownloads(): void
    {
        $this->twoBoats();
        $page = $this->page($this->browser());

        foreach (self::PRIVATE_DETAILS as $private) {
            $this->assertNotContains($private, $page->body, 'Public page');
        }

        $this->assertNotContains('Contact', $page->text(), 'Column headings');
    }

    public function testSignedInMemberSeesNoMoreThanThePublic(): void
    {
        $this->twoBoats();
        $page = $this->page($this->browser('registered'));

        $this->assertContains('Sea Breeze', $page->body);

        foreach (self::PRIVATE_DETAILS as $private) {
            $this->assertNotContains($private, $page->body, 'Member page');
        }
    }

    public function testRaceManagementSeesContactDetailsAndDownloads(): void
    {
        $this->twoBoats();
        $page = $this->page($this->browser('office'));
        $text = $page->text();

        $this->assertContains('Sea Breeze IRL 1234 J/109 IRC Test Yacht Club 1.045 On form: 1.039 Pat Skipper +353 85 555 0100 pat.skipper@example.invalid Season entry Sent 01 Oct 2026 11:00', $text);
        $this->assertContains('Not listed Not found in the YTC list', $text);
        $this->assertContains('1 entry needs its rating checked', $text);
        $this->assertTrue($this->link($page, 'Download CSV') !== '');
        $this->assertTrue($this->link($page, 'Download YB fleetsheet') !== '');
    }

    public function testRaceManagementGetABoatDataButtonForEachRatedIrcBoat(): void
    {
        Env::addRating('irc', 'Sea Breeze', 'IRL1234', 1.045, null, ['cert_no' => '41029']);
        Env::addRating('ytc', 'Slow Coach', 'GBR55', 845, null, ['cert_no' => '77']);
        Env::submit(Env::boat(['Boat_Name' => 'Sea Breeze', 'Sail_Number' => 'IRL 1234']));
        Env::submit(Env::boat(['Boat_Name' => 'Slow Coach', 'Sail_Number' => 'GBR 55', 'Class_Racing' => 'YTC']));
        Env::submit(Env::boat(['Boat_Name' => 'Unrated', 'Sail_Number' => 'IRL 777']));
        $page = $this->page($this->browser('office'));

        // The RORC page only accepts a posted search, so the button is a small form that opens in a new tab.
        $this->assertSame(1, substr_count($page->body, '<form class="isorairc-boatdata"'), 'One button: not for the YTC boat or the unrated one');
        $this->assertContains('<form class="isorairc-boatdata" method="post" action="https://ircrating.org/boat-data-for-valid-irc-certificates/" target="_blank" rel="noopener noreferrer">', $page->body);
        $this->assertContains('<input type="hidden" name="pdf_search" value="41029">', $page->body);
        $this->assertContains('1.045 On form: 1.010 Boat data Pat Skipper', $page->text());
    }

    public function testBoatDataButtonFollowsItsSetting(): void
    {
        Env::addRating('irc', 'Sea Breeze', 'IRL1234', 1.045, null, ['cert_no' => '41029']);
        Env::submit(Env::boat(['Boat_Name' => 'Sea Breeze', 'Sail_Number' => 'IRL 1234']));
        $office = $this->browser('office');

        Env::setParams(['irc_boatdata_url' => 'https://example.invalid/boats?a=1&b=2']);
        $this->assertContains('action="https://example.invalid/boats?a=1&amp;b=2"', $this->page($office)->body);

        Env::setParams(['irc_boatdata_url' => '']);
        $this->assertNotContains('isorairc-boatdata"', $this->page($office)->body, 'Empty setting hides the button');
    }

    public function testTableDoesNotNeedSidewaysScrollingColumns(): void
    {
        // Version 1.4.4: the race management table was folded from 13 columns into 8.
        $this->twoBoats();

        $this->assertSame(8, substr_count($this->page($this->browser('office'))->body, '<th scope="col">'), 'Race management columns');
        $this->assertSame(6, substr_count($this->page($this->browser())->body, '<th scope="col">'), 'Public columns');
    }

    public function testDoubtfulRatingIsMarkedForEveryoneAndExplainedToRaceManagement(): void
    {
        Env::addRating('irc', 'Sea Breeze', 'IRL1234', 1.045);
        Env::submit(Env::boat(['Boat_Name' => 'Sea Breeze', 'Sail_Number' => 'GBR 99']));

        $public = $this->page($this->browser());
        $this->assertContains('1.045 ?', $public->text());
        $this->assertNotContains('Boat name matches', $public->body);

        $office = $this->page($this->browser('office'));
        $this->assertContains('Boat name matches but the list has sail number IRL1234', $office->body);
    }

    public function testSaysWhetherRatingsAreCurrentOrFrozen(): void
    {
        Env::submit(Env::boat());
        $this->assertContains('IRC ratings: current list; they are frozen at the rating deadline.', $this->page($this->browser())->text());

        Env::freeze($this->race, 'irc');
        $text = $this->page($this->browser())->text();
        $this->assertContains('IRC ratings: as frozen at the rating deadline.', $text);
        $this->assertContains('YTC ratings: current list', $text);
    }

    public function testBoatNamesTypedOnTheFormAreShownSafely(): void
    {
        Env::submit(Env::boat(['Boat_Name' => '<img src=x onerror=alert(1)>', 'FullName' => '<script>alert(2)</script>']));
        $page = $this->page($this->browser('office'));

        $this->assertNotContains('<img src=x', $page->body);
        $this->assertNotContains('<script>alert(2)', $page->body);
        $this->assertContains('&lt;img src=x onerror=alert(1)&gt;', $page->body);
    }

    // ------------------------------------------------------------ which race is shown

    public function testEveryLinkedRaceIsInTheDropDown(): void
    {
        Env::addRace('Race 2', '2027-05-15', '2027-05-08 22:59:00', 'Race_2');
        Env::addRace('Unlinked race', '2027-06-01', '2027-05-25 22:59:00');
        $page = $this->page($this->browser());

        $this->assertContains('Race 1 – 01 May 2027', $page->text());
        $this->assertContains('Race 2 – 15 May 2027', $page->text());
        $this->assertNotContains('Unlinked race', $page->body);
    }

    public function testWithoutAChoiceTheNextRaceToBeSailedIsShown(): void
    {
        $db = Env::db();
        $db->setQuery('DELETE FROM ' . $db->quoteName('#__isorairc_races'))->execute();
        Env::addRace('Sailed already', Env::localDate(Env::utc('-20 days')), Env::utc('-27 days'), 'Race_1');
        Env::addRace('Coming up', Env::localDate(Env::utc('+5 days')), Env::utc('-2 days'), 'Race_2');
        Env::addRace('Later on', Env::localDate(Env::utc('+40 days')), Env::utc('+33 days'), 'Race_1');

        $this->assertContains('<h2 class="h4">Coming up</h2>', $this->page($this->browser(), 0)->body);
        $this->assertContains('<h2 class="h4">Coming up</h2>', $this->page($this->browser(), 99999)->body, 'Unknown race number');
    }

    public function testAfterTheSeasonTheLastRaceIsShown(): void
    {
        $db = Env::db();
        $db->setQuery('DELETE FROM ' . $db->quoteName('#__isorairc_races'))->execute();
        Env::addRace('First', Env::localDate(Env::utc('-60 days')), Env::utc('-67 days'), 'Race_1');
        Env::addRace('Last', Env::localDate(Env::utc('-20 days')), Env::utc('-27 days'), 'Race_2');

        $this->assertContains('<h2 class="h4">Last</h2>', $this->page($this->browser(), 0)->body);
    }

    public function testMessagesWhenThereIsNothingToShow(): void
    {
        $this->assertContains('No entries for this race yet.', $this->page($this->browser())->body);

        $db = Env::db();
        $db->setQuery('DELETE FROM ' . $db->quoteName('#__isorairc_races'))->execute();
        $this->assertContains('No races are linked to the entry form yet.', $this->page($this->browser())->body);

        Env::setParams(['entry_form_id' => 0]);
        $this->assertContains('The entry form has not been chosen yet.', $this->page($this->browser())->body);
    }

    public function testPageWorksFromItsMenuItem(): void
    {
        $this->twoBoats();
        $browser = $this->browser('office');

        $page = $browser->get('/race-entries?race=' . $this->race);
        $this->assertSame(200, $page->status);
        $this->assertNoPhpErrors($page);
        $this->assertContains('Sea Breeze', $page->body);
        $this->assertMatches('#<form action="/race-entries"#', $page->body, 'The race drop-down posts back to the menu page');

        $part = new Response();
        $part->body = $page->component('com-isorairc-entries');
        $csv = $browser->get($this->link($part, 'Download CSV'));
        $this->assertContains('text/csv', $csv->header('content-type'), 'CSV from the menu page');
    }

    // ------------------------------------------------------------ CSV download

    public function testCsvHasEveryDetailInItsOwnColumn(): void
    {
        $this->twoBoats();
        $browser = $this->browser('office');
        $csv     = $browser->get($this->link($this->page($browser), 'Download CSV'));

        $this->assertSame(200, $csv->status);
        $this->assertContains('text/csv', $csv->header('content-type'));
        $this->assertContains('attachment; filename="entries-race-' . $this->race . '.csv"', $csv->header('content-disposition'));

        $rows = $this->csv($csv->body);
        $this->assertSame(
            ['Boat', 'Sail No', 'Type', 'Class', 'Club', 'Rating', 'Rating note', 'Declared rating', 'Person in charge', 'Mobile', 'E-mail', 'Entry type', 'Form sent (UTC)'],
            $rows[0]
        );
        $this->assertSame(
            ['Sea Breeze', 'IRL 1234', 'J/109', 'IRC', 'Test Yacht Club', '1.045', '', '1.039', 'Pat Skipper', "'+353 85 555 0100", 'pat.skipper@example.invalid', 'Season entry (all races)', '2026-10-01 10:00:00'],
            $rows[1]
        );
        $this->assertSame('Not found in the YTC list', $rows[2][6]);
        $this->assertCount(3, $rows);
    }

    public function testCsvCannotSmuggleAFormulaIntoASpreadsheet(): void
    {
        Env::submit(Env::boat(['Boat_Name' => '=HYPERLINK("http://bad.invalid","Click")', 'Sailing_Club' => '@SUM(A1)', 'Latest_IRC_TCC' => '-1.5', 'FullName' => '-cmd']));
        $browser = $this->browser('office');
        $row     = $this->csv($browser->get($this->link($this->page($browser), 'Download CSV'))->body)[1];

        $this->assertSame('\'=HYPERLINK("http://bad.invalid","Click")', $row[0]);
        $this->assertSame("'@SUM(A1)", $row[4]);
        $this->assertSame('-1.5', $row[7], 'A plain negative number is left alone');
        $this->assertSame("'-cmd", $row[8]);
    }

    public function testCsvIsRefusedWithoutSigningIn(): void
    {
        $this->twoBoats();
        $office = $this->browser('office');
        $link   = $this->link($this->page($office), 'Download CSV');

        // Someone who is not signed in tries the race office's own link...
        $stolen = (new Browser())->get($link);
        $this->assertNotContains('pat.skipper@example.invalid', $stolen->body);
        $this->assertNotContains('text/csv', $stolen->header('content-type'));

        // ...and a link with a token of their own.
        $guest = new Browser();
        $token = $guest->token($guest->get('/index.php?option=com_users&view=login')->body);
        $own   = $guest->get(self::PAGE . '?task=entries.export&race=' . $this->race . '&' . $token . '=1');
        $this->assertSame(403, $own->status);
        $this->assertNotContains('pat.skipper@example.invalid', $own->body);
    }

    public function testCsvIsRefusedForAnOrdinaryMember(): void
    {
        $this->twoBoats();
        $member = $this->browser('registered');
        $token  = $member->token($member->get('/index.php?option=com_users&view=profile&layout=edit')->body);

        foreach (['entries.export', 'entries.fleetsheet'] as $task) {
            $download = $member->get(self::PAGE . '?task=' . $task . '&race=' . $this->race . '&' . $token . '=1');
            $this->assertSame(403, $download->status, $task);
            $this->assertNotContains('pat.skipper@example.invalid', $download->body, $task);
            $this->assertNotContains('Pat Skipper', $download->body, $task);
        }
    }

    public function testDownloadsNeedTheLinkFromThePage(): void
    {
        $this->twoBoats();
        $office = $this->browser('office');
        $this->page($office);

        foreach (['entries.export', 'entries.fleetsheet'] as $task) {
            $download = $office->get(self::PAGE . '?task=' . $task . '&race=' . $this->race);
            $this->assertNotContains('pat.skipper@example.invalid', $download->body, $task . ' without a token');
            $this->assertNotContains('spreadsheetml', $download->header('content-type'), $task . ' without a token');
        }
    }

    public function testDownloadOfAnUnknownRaceIsNotFound(): void
    {
        $this->twoBoats();
        $office = $this->browser('office');
        $link   = str_replace('race=' . $this->race, 'race=99999', $this->link($this->page($office), 'Download CSV'));

        $this->assertSame(404, $office->get($link)->status);
    }

    // ------------------------------------------------------------ fleetsheet download

    public function testFleetsheetDownloadsAsAnExcelWorkbookNamedAfterTheRace(): void
    {
        $this->twoBoats();
        $browser  = $this->browser('office');
        $download = $browser->get($this->link($this->page($browser), 'Download YB fleetsheet'));

        $this->assertSame(200, $download->status);
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $download->header('content-type'));
        $this->assertContains('filename="2027_ISORA_Race_1_Fleetsheet.xlsx"', $download->header('content-disposition'));
        $this->assertSame((string) \strlen($download->body), $download->header('content-length'));
        $this->assertSame('PK', substr($download->body, 0, 2), 'It is a zip-based workbook');

        $file = tempnam(sys_get_temp_dir(), 'fleet');
        file_put_contents($file, $download->body);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($file) === true);
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($file);

        $this->assertContains('Sea Breeze', $sheet);
        $this->assertContains('<v>1.045</v>', $sheet);
        $this->assertContains('Slow Coach', $sheet);
        $this->assertContains('Pat Skipper', $sheet);
        $this->assertNotContains('pat.skipper@example.invalid', $sheet, 'E-mail addresses are not sent to YB');
        $this->assertNotContains('555 0100', $sheet, 'Phone numbers are not sent to YB');
    }

    public function testFleetsheetIsRefusedWithoutSigningIn(): void
    {
        $this->twoBoats();
        $office = $this->browser('office');
        $stolen = (new Browser())->get($this->link($this->page($office), 'Download YB fleetsheet'));

        $this->assertNotContains('spreadsheetml', $stolen->header('content-type'));
        $this->assertNotContains('PK', substr($stolen->body, 0, 2));
    }
}
