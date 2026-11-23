<?php

namespace IsoraTests\Cases;

use Isora\Component\Isorairc\Administrator\Helper\RatingsHelper;
use IsoraTests\Env;
use IsoraTests\SiteTest;

/**
 * Downloading the rating lists over the web and storing them.
 */
class ImportTest extends SiteTest
{
    private function count(string $scheme): int
    {
        return \count(Env::rows('#__isorairc_ratings', "scheme = '" . $scheme . "'"));
    }

    private function imports(string $scheme = 'irc'): array
    {
        return Env::rows('#__isorairc_imports', "scheme = '" . $scheme . "'");
    }

    public function testBothListsAreImported(): void
    {
        $result = RatingsHelper::run();

        $this->assertSame(['irc' => 120, 'ytc' => 110], $result['imported']);
        $this->assertSame([], $result['errors']);
        $this->assertSame(120, $this->count('irc'));
        $this->assertSame(110, $this->count('ytc'));
    }

    public function testValuesAreStoredAsPublished(): void
    {
        Env::feed('irc', Env::ircCsv([[
            'Boat Name' => "O'Malley's Pride", 'Sail No' => 'IRL 1234', 'Cert No' => '4411', 'Issue Date' => '15/03/2026', 'Cert Year' => '2026',
            'TCC' => '1.045', 'Endorsed' => 'E', 'Secondary' => '', 'Non Spi TCC' => '', 'Crew' => '9', 'ValidCode' => 'V',
        ]]));
        RatingsHelper::run();

        $row = Env::rating('irc', "O'Malley's Pride");
        $this->assertTrue($row !== null, 'Boat with an apostrophe is stored');
        $this->assertSame('IRL 1234', $row->sail_no);
        $this->assertNumber(1.045, $row->tcc);
        $this->assertSame(null, $row->non_spi_tcc, 'Empty rating is stored as no value');
        $this->assertSame('2026-03-15', $row->issue_date);
        $this->assertSame(null, $row->secondary);
        $this->assertSame('E', $row->endorsed);
        $this->assertSame(9, (int) $row->crew);
    }

    public function testSailCountsAndSssBaseValueAreStored(): void
    {
        Env::feed('irc', Env::ircCsv([
            ['Boat Name' => 'Sea Breeze', 'Sail No' => 'IRL1234', 'TCC' => '1.045', 'Headsails' => '3', 'Flying Headsails' => '0', 'Spinnakers' => '4', 'SSS Base Value' => '32'],
            ['Boat Name' => 'No Details', 'Sail No' => 'IRL1', 'TCC' => '1.000'],
        ]));
        RatingsHelper::run();

        $row = Env::rating('irc', 'Sea Breeze');
        $this->assertSame(['3', '0', '4', '32'], [(string) $row->headsails, (string) $row->flying_headsails, (string) $row->spinnakers, (string) $row->sss_base]);
        $this->assertTrue($row->flying_headsails !== null, 'Nought is stored as nought, not as no value');

        $blank = Env::rating('irc', 'No Details');
        $this->assertSame([null, null, null, null], [$blank->headsails, $blank->flying_headsails, $blank->spinnakers, $blank->sss_base]);
    }

    public function testNewDownloadReplacesTheOldRatings(): void
    {
        Env::feed('irc', Env::ircCsv([['Boat Name' => 'Leaving', 'Sail No' => '1', 'TCC' => '1.000']]));
        RatingsHelper::run();
        $this->assertTrue(Env::rating('irc', 'Leaving') !== null);

        Env::feed('irc', Env::ircCsv([['Boat Name' => 'Arriving', 'Sail No' => '2', 'TCC' => '1.000']]));
        RatingsHelper::run();

        $this->assertSame(null, Env::rating('irc', 'Leaving'), 'Boat no longer on the list');
        $this->assertTrue(Env::rating('irc', 'Arriving') !== null);
        $this->assertSame(121, $this->count('irc'), 'No duplicates');
        $this->assertSame(110, $this->count('ytc'), 'The other system is untouched');
    }

    public function testSailNumberLongerThanForty(): void
    {
        // Version 1.1.1: one very long sail number made the whole YTC import fail.
        $long = 'GBR 1234 (formerly IRL 99, K 4455 and several others before that)';
        Env::feed('ytc', Env::ytcCsv([['EntryID' => '1', 'Boat Name' => 'Much Renamed', 'Sail Number' => $long, 'YTC' => '900']]));
        $result = RatingsHelper::run();

        $this->assertSame([], $result['errors']);
        $this->assertSame($long, Env::rating('ytc', 'Much Renamed')->sail_no);
    }

    public function testListThatIsTooShortIsRefusedAndOldRatingsKept(): void
    {
        RatingsHelper::run();
        Env::feed('irc', Env::ircCsv([], 99));
        $result = RatingsHelper::run();

        $this->assertContains('looks incomplete (99 boats)', $result['errors']['irc'] ?? '');
        $this->assertSame(120, $this->count('irc'), 'Earlier ratings are still there');
        $this->assertCount(1, $this->imports(), 'The refused download is not logged');
        $this->assertSame(110, $result['imported']['ytc'], 'The other list still imports');
    }

    public function testExactlyOneHundredBoatsIsAccepted(): void
    {
        Env::feed('irc', Env::ircCsv([], 100));
        $this->assertSame(100, RatingsHelper::run()['imported']['irc']);
    }

    public function testServerErrorIsReportedAndOldRatingsKept(): void
    {
        RatingsHelper::run();
        Env::feed('irc', Env::ircCsv(), 500);
        $result = RatingsHelper::run();

        $this->assertContains('HTTP 500', $result['errors']['irc'] ?? '');
        $this->assertSame(120, $this->count('irc'));
        $this->assertSame(110, $result['imported']['ytc']);
    }

    public function testMissingPageIsReported(): void
    {
        Env::setParams(['feed_url' => Env::$feedUrl . '/nosuchlist.csv']);
        $result = RatingsHelper::run();

        $this->assertContains('HTTP 404', $result['errors']['irc'] ?? '');
    }

    public function testUnreachableServerIsReportedNotFatal(): void
    {
        Env::setParams(['feed_url' => 'http://127.0.0.1:9/irc.csv']);
        $result = RatingsHelper::run();

        $this->assertTrue(isset($result['errors']['irc']), 'IRC failure is reported');
        $this->assertSame(110, $result['imported']['ytc'], 'YTC still imports');
    }

    public function testChangedLayoutIsReportedAndOldRatingsKept(): void
    {
        RatingsHelper::run();
        Env::feed('irc', str_replace('TCC', 'Rating', Env::ircCsv()));
        $result = RatingsHelper::run();

        $this->assertContains('column "TCC" is missing', $result['errors']['irc'] ?? '');
        $this->assertSame(120, $this->count('irc'));
    }

    public function testSwitchedOffSystemIsNotDownloaded(): void
    {
        Env::setParams(['ytc_enabled' => 0]);
        $result = RatingsHelper::run();

        $this->assertSame(['irc' => 120], $result['imported']);
        $this->assertSame(0, $this->count('ytc'));
    }

    public function testEveryDownloadIsLoggedWithItsDate(): void
    {
        Env::feed('irc', Env::ircCsv(), 200, 'Tue, 03 Nov 2026 08:15:00 GMT');
        RatingsHelper::run();
        $log = $this->imports()[0];

        $this->assertSame(120, (int) $log->row_count);
        $this->assertSame('2026-11-03 08:15:00', $log->feed_modified, 'Date the list itself was last changed');
        $this->assertTrue(abs(strtotime($log->imported_at . ' UTC') - time()) < 120, 'Download time is now, in UTC');
        $this->assertSame((int) $log->id, (int) RatingsHelper::lastImport('irc')->id);
        $this->assertSame(null, RatingsHelper::lastImport('nosuch'));
    }

    public function testUnchangedListIsStoredOnlyOnce(): void
    {
        RatingsHelper::run();
        RatingsHelper::run();
        $log = $this->imports();

        $this->assertCount(2, $log);
        $this->assertTrue($log[0]->csv_gz !== null, 'First download is archived');
        $this->assertSame(null, $log[1]->csv_gz, 'Identical download is not stored again');
        $this->assertSame((int) $log[0]->id, (int) $log[1]->content_id, 'It points at the first one');
    }

    public function testChangedListIsArchivedInFull(): void
    {
        RatingsHelper::run();
        $changed = Env::ircCsv([['Boat Name' => 'New Boat', 'Sail No' => '9', 'TCC' => '1.111']]);
        Env::feed('irc', $changed);
        RatingsHelper::run();
        $log = $this->imports();

        $this->assertSame((int) $log[1]->id, (int) $log[1]->content_id);
        $this->assertSame($changed, gzdecode(base64_decode($log[1]->csv_gz)), 'Archive holds the download exactly');
    }

    public function testSummaryLineForAGoodRun(): void
    {
        $this->assertSame(
            'IRC: 120 boats imported; YTC: 110 boats imported; 0 race snapshot(s) taken.',
            RatingsHelper::summary(RatingsHelper::run())
        );
    }

    public function testSummaryLineNamesTheListThatFailed(): void
    {
        Env::feed('ytc', '', 503);
        $summary = RatingsHelper::summary(RatingsHelper::run());

        $this->assertContains('IRC: 120 boats imported', $summary);
        $this->assertContains('YTC import FAILED: feed returned HTTP 503', $summary);
    }
}
