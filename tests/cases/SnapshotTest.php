<?php

namespace IsoraTests\Cases;

use Isora\Component\Isorairc\Administrator\Helper\RatingsHelper;
use IsoraTests\Env;
use IsoraTests\SiteTest;

/**
 * Freezing every boat's rating at a race's rating deadline.
 *
 * Each test has a race whose deadline was ten days ago, and decides which
 * downloads happened before and after it.
 */
class SnapshotTest extends SiteTest
{
    private string $deadline;

    private int $race;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deadline = Env::utc('-10 days');
        $this->race     = Env::addRace('Race 1', Env::localDate($this->deadline, 7), $this->deadline);
    }

    /** One IRC boat. $issued is a number of days after (or, negative, before) the deadline date. */
    private function boat(string $name, string $sail, string $tcc, int $issued, array $more = []): array
    {
        return $more + ['Boat Name' => $name, 'Sail No' => $sail, 'TCC' => $tcc, 'Issue Date' => Env::localDate($this->deadline, $issued, 'd/m/Y')];
    }

    private function status(string $scheme = 'irc', ?int $race = null): ?object
    {
        return RatingsHelper::snapshotStatus([$race ?? $this->race])[$race ?? $this->race][$scheme] ?? null;
    }

    private function frozen(string $boat, string $scheme = 'irc'): ?object
    {
        return Env::rating($scheme, $boat, $this->race);
    }

    public function testNothingIsFrozenBeforeTheDeadline(): void
    {
        $future = Env::addRace('Next month', Env::localDate(Env::utc('+30 days')), Env::utc('+23 days'));
        $result = RatingsHelper::run();

        $this->assertSame(null, $this->status('irc', $future));
        $this->assertSame(2, $result['snapshots'], 'Only the race whose deadline has passed is frozen, once per rating system');
    }

    public function testNothingIsFrozenUntilAListIsDownloadedAfterTheDeadline(): void
    {
        Env::import('irc', Env::ircCsv(), Env::utc('-11 days'));

        $this->assertSame(0, RatingsHelper::processSnapshots());
        $this->assertSame(null, $this->status());
    }

    public function testTheFirstDownloadAfterTheDeadlineIsUsed(): void
    {
        Env::import('irc', Env::ircCsv([$this->boat('Steady', 'IRL1', '1.000', -100)]), Env::utc('-12 days'));
        $first = Env::import('irc', Env::ircCsv([$this->boat('Steady', 'IRL1', '1.001', -100)]), Env::utc('-9 days'));
        Env::import('irc', Env::ircCsv([$this->boat('Steady', 'IRL1', '1.002', -100)]), Env::utc('-5 days'));

        $this->assertSame(1, RatingsHelper::processSnapshots());
        $this->assertSame($first, (int) $this->status()->import_id);
        $this->assertNumber(1.001, $this->frozen('Steady')->tcc);
        $this->assertSame(121, (int) $this->status()->boat_count);
        $this->assertCount(121, Env::rows('#__isorairc_snapshot_ratings', 'race_id = ' . $this->race));
    }

    public function testCertificateReissuedAfterTheDeadlineKeepsItsOldRating(): void
    {
        Env::import('irc', Env::ircCsv([$this->boat('Optimised', 'IRL2', '1.000', -200, ['Cert No' => 'OLD'])]), Env::utc('-11 days'));
        Env::import('irc', Env::ircCsv([$this->boat('Optimised', 'IRL2', '0.990', 1, ['Cert No' => 'NEW'])]), Env::utc('-9 days'));
        RatingsHelper::processSnapshots();

        $this->assertNumber(1.000, $this->frozen('Optimised')->tcc, 'Frozen rating');
        $this->assertSame('OLD', $this->frozen('Optimised')->cert_no, 'Frozen certificate');
        $this->assertNumber(0.990, Env::rating('irc', 'Optimised')->tcc, 'Current rating shows the new certificate');
        $this->assertContains('1 kept on pre-deadline rating, 0 left out', $this->status()->note);
    }

    public function testBoatFirstRatedAfterTheDeadlineIsLeftOut(): void
    {
        Env::import('irc', Env::ircCsv(), Env::utc('-11 days'));
        Env::import('irc', Env::ircCsv([$this->boat('Latecomer', 'IRL3', '1.020', 2)]), Env::utc('-9 days'));
        RatingsHelper::processSnapshots();

        $this->assertSame(null, $this->frozen('Latecomer'));
        $this->assertSame(120, (int) $this->status()->boat_count);
        $this->assertContains('0 kept on pre-deadline rating, 1 left out', $this->status()->note);
    }

    public function testCertificateIssuedOnTheDeadlineDayCounts(): void
    {
        Env::import('irc', Env::ircCsv([$this->boat('Just In Time', 'IRL4', '1.000', -50)]), Env::utc('-11 days'));
        Env::import('irc', Env::ircCsv([$this->boat('Just In Time', 'IRL4', '0.995', 0)]), Env::utc('-9 days'));
        RatingsHelper::processSnapshots();

        $this->assertNumber(0.995, $this->frozen('Just In Time')->tcc);
    }

    public function testCertificateWithoutAnIssueDateIsTakenAsItStands(): void
    {
        Env::import('irc', Env::ircCsv([['Boat Name' => 'Undated', 'Sail No' => 'IRL5', 'TCC' => '1.033']]), Env::utc('-9 days'));
        RatingsHelper::processSnapshots();

        $this->assertNumber(1.033, $this->frozen('Undated')->tcc);
    }

    public function testBoatIsRecognisedDespiteSpacingAndCapitalsInItsDetails(): void
    {
        Env::import('irc', Env::ircCsv([$this->boat('Wild Thing', 'IRL 77', '1.000', -200)]), Env::utc('-11 days'));
        Env::import('irc', Env::ircCsv([$this->boat('WILD  THING', 'irl77', '0.980', 3)]), Env::utc('-9 days'));
        RatingsHelper::processSnapshots();

        $this->assertNumber(1.000, $this->frozen('Wild Thing')->tcc);
    }

    public function testPrimaryAndSecondaryCertificatesAreKeptApart(): void
    {
        Env::import('irc', Env::ircCsv([
            $this->boat('Two Certs', 'IRL8', '1.050', -200),
            $this->boat('Two Certs', 'IRL8', '1.030', -200, ['Secondary' => 'S']),
        ]), Env::utc('-11 days'));
        Env::import('irc', Env::ircCsv([
            $this->boat('Two Certs', 'IRL8', '1.050', -200),
            $this->boat('Two Certs', 'IRL8', '1.010', 1, ['Secondary' => 'S']),
        ]), Env::utc('-9 days'));
        RatingsHelper::processSnapshots();

        $rows = Env::rows('#__isorairc_snapshot_ratings', "boat_name = 'Two Certs'", 'tcc');
        $this->assertCount(2, $rows);
        $this->assertNumber(1.030, $rows[0]->tcc, 'Secondary certificate goes back to its own old rating');
        $this->assertNumber(1.050, $rows[1]->tcc, 'Primary certificate');
    }

    public function testWithNoEarlierDownloadLateCertificatesAreLeftOutAndTheNoteSaysSo(): void
    {
        Env::import('irc', Env::ircCsv([$this->boat('Changed', 'IRL9', '1.000', 1), $this->boat('Settled', 'IRL10', '1.000', -30)]), Env::utc('-9 days'));
        RatingsHelper::processSnapshots();

        $this->assertSame(null, $this->frozen('Changed'));
        $this->assertTrue($this->frozen('Settled') !== null);
        $this->assertContains('no download existed before the deadline', $this->status()->note);
    }

    public function testFrozenRatingsDoNotChangeWhenTheListChangesLater(): void
    {
        Env::import('irc', Env::ircCsv([$this->boat('Steady', 'IRL1', '1.000', -100)]), Env::utc('-9 days'));
        Env::import('ytc', Env::ytcCsv(), Env::utc('-9 days'));
        RatingsHelper::processSnapshots();
        $taken = $this->status()->taken;

        Env::feed('irc', Env::ircCsv([$this->boat('Steady', 'IRL1', '1.234', -100)]));
        $result = RatingsHelper::run();

        $this->assertSame(0, $result['snapshots'], 'Nothing is frozen a second time');
        $this->assertNumber(1.000, $this->frozen('Steady')->tcc, 'Frozen rating');
        $this->assertNumber(1.234, Env::rating('irc', 'Steady')->tcc, 'Current rating');
        $this->assertSame($taken, $this->status()->taken);
    }

    public function testEachRatingSystemIsFrozenSeparately(): void
    {
        Env::import('irc', Env::ircCsv(), Env::utc('-9 days'));
        RatingsHelper::processSnapshots();

        $this->assertTrue($this->status('irc') !== null, 'IRC is frozen');
        $this->assertSame(null, $this->status('ytc'), 'YTC waits for its own download');

        Env::import('ytc', Env::ytcCsv([['EntryID' => '1', 'Boat Name' => 'Slow Coach', 'Sail Number' => '5', 'YTC' => '845', 'YTC White Sail' => '820', 'Issue Date' => '2026-01-01']]), Env::utc('-8 days'));
        $this->assertSame(1, RatingsHelper::processSnapshots());
        $this->assertNumber(845, $this->frozen('Slow Coach', 'ytc')->tcc);
        $this->assertNumber(820, $this->frozen('Slow Coach', 'ytc')->non_spi_tcc);
        $this->assertSame(111, (int) $this->status('ytc')->boat_count);
    }

    public function testYtcCertificateReissuedAfterTheDeadlineKeepsItsOldRating(): void
    {
        $boat = ['EntryID' => '42', 'Boat Name' => 'Slow Coach', 'Sail Number' => 'GBR 5', 'YTC' => '845', 'Issue Date' => Env::localDate($this->deadline, -60)];
        Env::import('ytc', Env::ytcCsv([$boat]), Env::utc('-11 days'));
        Env::import('ytc', Env::ytcCsv([['YTC' => '860', 'Issue Date' => Env::localDate($this->deadline, 1)] + $boat]), Env::utc('-9 days'));
        RatingsHelper::processSnapshots();

        $this->assertNumber(845, $this->frozen('Slow Coach', 'ytc')->tcc);
    }

    public function testSwitchedOffSystemIsNotFrozen(): void
    {
        Env::import('ytc', Env::ytcCsv(), Env::utc('-9 days'));
        Env::setParams(['ytc_enabled' => 0]);

        $this->assertSame(0, RatingsHelper::processSnapshots());
        $this->assertSame(null, $this->status('ytc'));
    }

    public function testSnapshotCanBeTakenFromADownloadThatWasIdenticalToTheOneBefore(): void
    {
        $csv = Env::ircCsv([$this->boat('Steady', 'IRL1', '1.000', -100)]);
        Env::import('irc', $csv, Env::utc('-11 days'));
        Env::import('irc', $csv, Env::utc('-9 days'));

        $this->assertSame(1, RatingsHelper::processSnapshots());
        $this->assertNumber(1.000, $this->frozen('Steady')->tcc);
    }

    public function testRebuildTakesTheSnapshotAgainFromTheArchive(): void
    {
        Env::import('irc', Env::ircCsv([$this->boat('Optimised', 'IRL2', '1.000', -200)]), Env::utc('-11 days'));
        Env::import('irc', Env::ircCsv([$this->boat('Optimised', 'IRL2', '0.990', 1)]), Env::utc('-9 days'));
        RatingsHelper::processSnapshots();

        // Damage the snapshot, as a mistaken manual edit might.
        $db = Env::db();
        $db->setQuery('UPDATE ' . $db->quoteName('#__isorairc_snapshot_ratings') . ' SET tcc = 9.999')->execute();

        $this->assertSame(1, RatingsHelper::rebuild([$this->race]));
        $this->assertNumber(1.000, $this->frozen('Optimised')->tcc);
        $this->assertCount(121, Env::rows('#__isorairc_snapshot_ratings', 'race_id = ' . $this->race), 'No duplicate rows');
    }

    public function testRebuildOnlyTouchesTheRacesAskedFor(): void
    {
        $other = Env::addRace('Race 2', Env::localDate($this->deadline, 8), Env::utc('-9 days -12 hours'));
        Env::import('irc', Env::ircCsv(), Env::utc('-9 days'));
        RatingsHelper::processSnapshots();
        $db = Env::db();
        $db->setQuery('UPDATE ' . $db->quoteName('#__isorairc_race_snapshots') . " SET note = 'untouched'")->execute();

        RatingsHelper::rebuild([$this->race]);

        $this->assertSame('untouched', $this->status('irc', $other)->note);
        $this->assertTrue($this->status()->note !== 'untouched', 'The race asked for was rebuilt');
        $this->assertSame(0, RatingsHelper::rebuild([]), 'Rebuilding nothing does nothing');
    }

    public function testDeletingSnapshotsRemovesRatingsAndStatus(): void
    {
        Env::import('irc', Env::ircCsv(), Env::utc('-9 days'));
        RatingsHelper::processSnapshots();
        RatingsHelper::deleteSnapshots([$this->race]);

        $this->assertSame(null, $this->status());
        $this->assertCount(0, Env::rows('#__isorairc_snapshot_ratings'));
    }

    public function testTwoRacesWithDifferentDeadlinesGetDifferentRatings(): void
    {
        $later = Env::addRace('Race 2', Env::localDate(Env::utc('-1 day')), Env::utc('-6 days'));
        Env::import('irc', Env::ircCsv([$this->boat('Mover', 'IRL6', '1.000', -100)]), Env::utc('-9 days'));
        Env::import('irc', Env::ircCsv([$this->boat('Mover', 'IRL6', '1.015', 2)]), Env::utc('-5 days'));
        RatingsHelper::processSnapshots();

        $this->assertNumber(1.000, $this->frozen('Mover')->tcc, 'First race');
        $this->assertNumber(1.015, Env::rating('irc', 'Mover', $later)->tcc, 'Second race, whose deadline came after the new certificate');
    }

    public function testDeadlineDayIsWorkedOutInTheSitesTimeZone(): void
    {
        // In summer Irish time is an hour ahead of UTC. A deadline at 00:30 on 6 June in
        // Ireland is stored as 23:30 on 5 June UTC, yet 6 June is the deadline day:
        // a certificate dated 6 June counts, one dated 7 June does not.
        if (!\in_array(Env::timezone()->getName(), ['Europe/Dublin', 'Europe/London'], true)) {
            return;
        }

        $race = Env::addRace('Summer race', '2025-06-13', '2025-06-05 23:30:00');
        Env::import('irc', Env::ircCsv([
            ['Boat Name' => 'On The Day', 'Sail No' => 'S1', 'TCC' => '1.000', 'Issue Date' => '01/06/2025'],
            ['Boat Name' => 'Day After', 'Sail No' => 'S2', 'TCC' => '1.000', 'Issue Date' => '01/06/2025'],
        ]), '2025-06-05 12:00:00');
        Env::import('irc', Env::ircCsv([
            ['Boat Name' => 'On The Day', 'Sail No' => 'S1', 'TCC' => '0.900', 'Issue Date' => '06/06/2025'],
            ['Boat Name' => 'Day After', 'Sail No' => 'S2', 'TCC' => '0.900', 'Issue Date' => '07/06/2025'],
        ]), '2025-06-08 08:00:00');
        RatingsHelper::processSnapshots();

        $this->assertNumber(0.900, Env::rating('irc', 'On The Day', $race)->tcc);
        $this->assertNumber(1.000, Env::rating('irc', 'Day After', $race)->tcc);
    }
}
