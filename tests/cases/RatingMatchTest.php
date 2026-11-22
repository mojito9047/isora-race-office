<?php

namespace IsoraTests\Cases;

use Isora\Component\Isorairc\Administrator\Helper\EntriesHelper;
use IsoraTests\Env;
use IsoraTests\SiteTest;

/**
 * Finding each entered boat's rating, and flagging the doubtful ones.
 */
class RatingMatchTest extends SiteTest
{
    private int $race;

    protected function setUp(): void
    {
        parent::setUp();
        Env::standardForm();
        $this->race = Env::addRace('Race 1', '2027-05-01', '2027-04-24 22:59:00', 'Race_1');
    }

    /** Enter one boat and return its entry with the rating filled in. */
    private function enter(string $boat, string $sail, string $class = 'IRC'): object
    {
        Env::submit(Env::boat(['Boat_Name' => $boat, 'Sail_Number' => $sail, 'Class_Racing' => $class]));

        foreach (EntriesHelper::entries(Env::race($this->race)) as $entry) {
            if ($entry->boat === $boat) {
                return $entry;
            }
        }

        $this->fail($boat . ' is not in the entry list');
    }

    public function testClassDecidesTheRatingSystem(): void
    {
        foreach (['IRC' => 'irc', 'IRC 2-Handed' => 'irc', 'IRC Non-Spinnaker' => 'irc', '' => 'irc', 'YTC' => 'ytc', ' ytc White Sail' => 'ytc', 'ytc cruiser' => 'ytc'] as $class => $scheme) {
            $this->assertSame($scheme, EntriesHelper::schemeForClass($class), 'Class "' . $class . '"');
        }
    }

    public function testNonSpinnakerClassesAreRecognised(): void
    {
        foreach (['IRC Non-Spinnaker', 'IRC non spinnaker', 'IRC NonSpin', 'YTC White Sail', 'YTC whitesail'] as $class) {
            $this->assertTrue(EntriesHelper::isNonSpinnaker($class), 'Class "' . $class . '"');
        }

        foreach (['IRC', 'IRC 2-Handed', 'YTC', ''] as $class) {
            $this->assertFalse(EntriesHelper::isNonSpinnaker($class), 'Class "' . $class . '"');
        }
    }

    public function testNameAndSailNumberBothAgreeingIsAFirmMatch(): void
    {
        Env::addRating('irc', 'Sea Breeze', 'IRL1234', 1.045, 1.021);
        $entry = $this->enter('sea breeze', 'IRL 1234');

        $this->assertSame('ok', $entry->rating_status);
        $this->assertSame('1.045', $entry->rating_text);
        $this->assertNumber(1.045, $entry->rating);
        $this->assertSame('', $entry->rating_note);
        $this->assertSame('irc', $entry->scheme);
    }

    public function testPunctuationInNamesAndSailNumbersIsIgnored(): void
    {
        Env::addRating('irc', "Rock'n'Roll II", 'GBR-4321L', 1.100);
        $entry = $this->enter('Rock n Roll II', 'gbr 4321 l');

        $this->assertSame('ok', $entry->rating_status);
        $this->assertSame('1.100', $entry->rating_text);
    }

    public function testSailNumberTypedWithoutItsCountryLettersStillMatchesWithANote(): void
    {
        Env::addRating('irc', 'Sea Breeze', 'IRL1234', 1.045);
        $entry = $this->enter('Sea Breeze', '1234');

        $this->assertSame('ok', $entry->rating_status);
        $this->assertSame('1.045', $entry->rating_text);
        $this->assertSame('Listed with sail number IRL1234', $entry->rating_note);
    }

    public function testNameMatchingButDifferentSailNumberIsFlagged(): void
    {
        Env::addRating('irc', 'Sea Breeze', 'IRL1234', 1.045);
        $entry = $this->enter('Sea Breeze', 'GBR 99');

        $this->assertSame('check', $entry->rating_status);
        $this->assertSame('1.045', $entry->rating_text, 'The likely rating is still shown');
        $this->assertSame('Boat name matches but the list has sail number IRL1234', $entry->rating_note);
    }

    public function testSailNumberMatchingButDifferentNameIsFlagged(): void
    {
        Env::addRating('irc', 'Old Name', 'IRL1234', 1.045);
        $entry = $this->enter('New Name', 'IRL 1234');

        $this->assertSame('check', $entry->rating_status);
        $this->assertSame('Sail number matches but the list has boat name Old Name', $entry->rating_note);
    }

    public function testBoatNotOnTheListIsReportedAsNotFound(): void
    {
        Env::addRating('irc', 'Someone Else', 'IRL1', 1.000);
        $entry = $this->enter('Unrated', 'IRL 777');

        $this->assertSame('none', $entry->rating_status);
        $this->assertSame('', $entry->rating_text);
        $this->assertSame(null, $entry->rating);
        $this->assertSame('Not found in the IRC list', $entry->rating_note);
    }

    public function testSingleDigitSailNumberDoesNotMatchOtherBoatsByDigitsAlone(): void
    {
        Env::addRating('irc', 'Other Boat', 'IRL7', 1.000);
        $entry = $this->enter('My Boat', '7');

        $this->assertSame('none', $entry->rating_status);
    }

    public function testFullMatchBeatsAPartialOne(): void
    {
        Env::addRating('irc', 'Jedi', 'GBR8', 0.950);
        Env::addRating('irc', 'Jedi', 'IRL8', 1.050);
        Env::addRating('irc', 'Impostor', 'IRL8', 0.900);
        $entry = $this->enter('Jedi', 'IRL 8');

        $this->assertSame('ok', $entry->rating_status);
        $this->assertSame('1.050', $entry->rating_text);
    }

    public function testPrimaryCertificateIsPreferredOverASecondaryOne(): void
    {
        Env::addRating('irc', 'Two Certs', 'IRL5', 1.010, null, ['secondary' => 'S']);
        Env::addRating('irc', 'Two Certs', 'IRL5', 1.040);
        Env::addRating('irc', 'Two Certs', 'IRL5', 1.020, null, ['secondary' => 'S']);
        $entry = $this->enter('Two Certs', 'IRL 5');

        $this->assertSame('1.040', $entry->rating_text);
    }

    public function testNonSpinnakerClassUsesTheNonSpinnakerRating(): void
    {
        Env::addRating('irc', 'Gentle', 'IRL6', 1.045, 1.021);
        $entry = $this->enter('Gentle', 'IRL 6', 'IRC Non-Spinnaker');

        $this->assertSame('1.021', $entry->rating_text);
        $this->assertSame('ok', $entry->rating_status);
    }

    public function testNonSpinnakerClassWithoutANonSpinnakerRatingIsFlagged(): void
    {
        Env::addRating('irc', 'Gentle', 'IRL6', 1.045, null);
        $entry = $this->enter('Gentle', 'IRL 6', 'IRC Non-Spinnaker');

        $this->assertSame('check', $entry->rating_status);
        $this->assertSame('', $entry->rating_text, 'The spinnaker rating is not shown in its place');
        $this->assertSame('Listed as Gentle IRL6 but with no non-spinnaker rating', $entry->rating_note);
    }

    public function testYtcClassUsesTheYtcListAndWholeNumbers(): void
    {
        Env::addRating('irc', 'Slow Coach', 'GBR55', 0.925);
        Env::addRating('ytc', 'Slow Coach', 'GBR55', 845, 820);
        $entry = $this->enter('Slow Coach', 'GBR 55', 'YTC');

        $this->assertSame('ytc', $entry->scheme);
        $this->assertSame('845', $entry->rating_text);
        $this->assertSame('ok', $entry->rating_status);
    }

    public function testYtcWhiteSailClassUsesTheWhiteSailRating(): void
    {
        Env::addRating('ytc', 'Slow Coach', 'GBR55', 845, 820);

        $this->assertSame('820', $this->enter('Slow Coach', 'GBR 55', 'YTC White Sail')->rating_text);
    }

    public function testIrcBoatIsNotGivenAYtcRating(): void
    {
        Env::addRating('ytc', 'Slow Coach', 'GBR55', 845);
        $entry = $this->enter('Slow Coach', 'GBR 55', 'IRC');

        $this->assertSame('none', $entry->rating_status);
        $this->assertSame('Not found in the IRC list', $entry->rating_note);
    }

    public function testClassOfASwitchedOffSystemIsLeftBlankWithoutAWarning(): void
    {
        Env::addRating('ytc', 'Slow Coach', 'GBR55', 845);
        Env::setParams(['ytc_enabled' => 0]);
        $entry = $this->enter('Slow Coach', 'GBR 55', 'YTC');

        $this->assertSame('off', $entry->rating_status);
        $this->assertSame('', $entry->rating_text);
        $this->assertSame('', $entry->rating_note);
    }

    public function testCurrentRatingsAreUsedUntilTheRaceIsFrozen(): void
    {
        Env::addRating('irc', 'Mover', 'IRL9', 1.000);
        Env::addRating('irc', 'Mover', 'IRL9', 0.975, null, [], $this->race);

        $this->assertSame(['irc' => 'current', 'ytc' => 'current'], EntriesHelper::ratingSources(Env::race($this->race)));
        $this->assertSame('1.000', $this->enter('Mover', 'IRL 9')->rating_text);
    }

    public function testFrozenRatingsAreUsedOnceTheRaceIsFrozen(): void
    {
        Env::addRating('irc', 'Mover', 'IRL9', 1.000);
        Env::addRating('irc', 'Mover', 'IRL9', 0.975, null, [], $this->race);
        Env::freeze($this->race, 'irc');

        $this->assertSame(['irc' => 'snapshot', 'ytc' => 'current'], EntriesHelper::ratingSources(Env::race($this->race)));
        $this->assertSame('0.975', $this->enter('Mover', 'IRL 9')->rating_text);
    }

    public function testFrozenRatingsOfAnotherRaceAreNotUsed(): void
    {
        $other = Env::addRace('Race 2', '2027-06-01', '2027-05-25 22:59:00', 'Race_2');
        Env::addRating('irc', 'Mover', 'IRL9', 0.975, null, [], $other);
        Env::freeze($other, 'irc');
        Env::freeze($this->race, 'irc');

        $this->assertSame('none', $this->enter('Mover', 'IRL 9')->rating_status, 'Not in this race\'s frozen list');
    }

    public function testBoatLeftOutOfTheFrozenListIsNotGivenItsNewRating(): void
    {
        Env::addRating('irc', 'Latecomer', 'IRL3', 1.020);
        Env::freeze($this->race, 'irc');

        $this->assertSame('none', $this->enter('Latecomer', 'IRL 3')->rating_status);
    }

    public function testEachBoatGetsItsOwnRatingWhenManyAreEntered(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            Env::addRating('irc', 'Fleet ' . $i, 'IRL' . (100 + $i), 0.9 + $i / 1000);
            Env::submit(Env::boat(['Boat_Name' => 'Fleet ' . $i, 'Sail_Number' => 'IRL ' . (100 + $i)]));
        }

        $entries = EntriesHelper::entries(Env::race($this->race));
        $this->assertCount(30, $entries);

        foreach ($entries as $entry) {
            $number = (int) substr($entry->boat, 6);
            $this->assertSame(number_format(0.9 + $number / 1000, 3), $entry->rating_text, $entry->boat);
            $this->assertSame('ok', $entry->rating_status, $entry->boat);
        }
    }

    public function testQuoteInABoatNameDoesNotBreakTheLookup(): void
    {
        Env::addRating('irc', "Finn's \"Folly\"", 'IRL11', 1.000);
        $entry = $this->enter("Finn's \"Folly\"", "IRL 11' OR '1'='1");

        $this->assertSame('1.000', $entry->rating_text);
        $this->assertSame('check', $entry->rating_status, 'The odd sail number does not match, the name does');
    }

    public function testMatchedBoatCarriesItsCertificateNumber(): void
    {
        Env::addRating('irc', 'Two Certs', 'IRL5', 1.010, null, ['secondary' => 'SEC', 'cert_no' => '48507']);
        Env::addRating('irc', 'Two Certs', 'IRL5', 1.040, null, ['cert_no' => '41029']);

        $this->assertSame('41029', $this->enter('Two Certs', 'IRL 5')->cert_no, 'The certificate the rating came from');
        $this->assertSame('', $this->enter('Unrated', 'IRL 777')->cert_no, 'A boat that is not on the list');
    }

    public function testCertificateNumberComesFromTheFrozenListOnceFrozen(): void
    {
        Env::addRating('irc', 'Mover', 'IRL9', 1.000, null, ['cert_no' => 'NEW']);
        Env::addRating('irc', 'Mover', 'IRL9', 0.975, null, ['cert_no' => 'OLD'], $this->race);
        Env::freeze($this->race, 'irc');

        $this->assertSame('OLD', $this->enter('Mover', 'IRL 9')->cert_no);
    }

    public function testBoatDataPageSetting(): void
    {
        $this->assertSame('https://ircrating.org/boat-data-for-valid-irc-certificates/', EntriesHelper::boatDataUrl(), 'Default');

        Env::setParams(['irc_boatdata_url' => ' https://example.invalid/boats ']);
        $this->assertSame('https://example.invalid/boats', EntriesHelper::boatDataUrl());

        foreach (['', '  ', 'javascript:alert(1)', 'ircrating.org'] as $bad) {
            Env::setParams(['irc_boatdata_url' => $bad]);
            $this->assertSame('', EntriesHelper::boatDataUrl(), 'Setting "' . $bad . '" switches the button off');
        }
    }

    public function testRenamedBoatEnteredWithOnlyTheDigitsOfItsSailNumberIsFlaggedNotMissed(): void
    {
        Env::addRating('irc', 'Old Name', 'IRL1234', 1.045);
        $entry = $this->enter('New Name', '1234');

        $this->assertSame('check', $entry->rating_status);
        $this->assertSame('Sail number matches but the list has boat name Old Name', $entry->rating_note);
    }
}
