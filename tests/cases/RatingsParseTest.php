<?php

namespace IsoraTests\Cases;

use Isora\Component\Isorairc\Administrator\Helper\RatingsHelper;
use IsoraTests\Env;
use IsoraTests\SiteTest;

/**
 * Reading a rating list: addresses, columns, dates and awkward content.
 */
class RatingsParseTest extends SiteTest
{
    private function irc(): array
    {
        return RatingsHelper::systems(false)['irc'];
    }

    private function ytc(): array
    {
        return RatingsHelper::systems(false)['ytc'];
    }

    public function testGoogleSheetEditLinkBecomesItsCsvAddress(): void
    {
        $this->assertSame(
            'https://docs.google.com/spreadsheets/d/AbC_123-x/export?format=csv',
            RatingsHelper::csvUrl('https://docs.google.com/spreadsheets/d/AbC_123-x/edit')
        );
    }

    public function testGoogleSheetLinkKeepsItsTab(): void
    {
        $this->assertSame(
            'https://docs.google.com/spreadsheets/d/AbC/export?format=csv&gid=987',
            RatingsHelper::csvUrl('https://docs.google.com/spreadsheets/d/AbC/edit?usp=sharing#gid=987')
        );
    }

    public function testGoogleExportLinkAndOtherAddressesAreLeftAlone(): void
    {
        foreach (['https://docs.google.com/spreadsheets/d/AbC/export?format=csv&gid=3', 'https://www.topyacht.com.au/rorc/data/ClubListing.csv'] as $url) {
            $this->assertSame($url, RatingsHelper::csvUrl($url));
        }
    }

    public function testBuiltInAddressesAreUsedWhenTheSettingsAreEmpty(): void
    {
        Env::setParams(['feed_url' => '', 'ytc_url' => ' ']);
        $systems = RatingsHelper::systems();

        $this->assertSame(RatingsHelper::DEFAULT_IRC_URL, $systems['irc']['url']);
        $this->assertContains('/export?format=csv', $systems['ytc']['url'], 'YTC address');
    }

    public function testBothSystemsAreOnByDefaultAndCanBeSwitchedOff(): void
    {
        $this->assertSame(['irc', 'ytc'], array_keys(RatingsHelper::systems()));

        Env::setParams(['ytc_enabled' => 0]);
        $this->assertSame(['irc'], array_keys(RatingsHelper::systems()));
        $this->assertSame(['irc', 'ytc'], array_keys(RatingsHelper::systems(false)), 'Asking for all systems');

        Env::setParams(['irc_enabled' => 0, 'ytc_enabled' => 0]);
        $this->assertSame([], RatingsHelper::systems());
    }

    public function testIrcRowIsReadIntoTheRightColumns(): void
    {
        $csv = Env::ircCsv([[
            'Boat Name' => ' Sea Breeze ', 'Sail No' => 'IRL 1234', 'Cert No' => '4411', 'Issue Date' => '15/03/2026', 'Cert Year' => '2026',
            'TCC' => '1.045', 'Endorsed' => 'E', 'Secondary' => '', 'Non Spi TCC' => '1.021', 'Crew' => '9', 'ValidCode' => 'V',
        ]], 0);
        $rows = RatingsHelper::parse($csv, $this->irc());

        $this->assertCount(1, $rows);
        $this->assertSame('Sea Breeze', $rows[0]['boat_name'], 'Name is trimmed');
        $this->assertSame('IRL 1234', $rows[0]['sail_no']);
        $this->assertSame('4411', $rows[0]['cert_no']);
        $this->assertSame('2026-03-15', $rows[0]['issue_date'], 'dd/mm/yyyy date');
        $this->assertSame('1.045', $rows[0]['tcc']);
        $this->assertSame('1.021', $rows[0]['non_spi_tcc']);
        $this->assertSame('E', $rows[0]['endorsed']);
        $this->assertSame('9', $rows[0]['crew']);
        $this->assertSame('', $rows[0]['club'], 'Column the IRC list does not have');
    }

    public function testYtcRowIsReadIntoTheRightColumns(): void
    {
        $csv = Env::ytcCsv([[
            'EntryID' => '77', 'Boat Name' => 'Slow Coach', 'Sail Number' => 'GBR 55', 'Boat Type' => 'Sadler 32', 'YTC' => '845',
            'YTC White Sail' => '820', 'Club' => 'Holyhead SC', 'Provisional' => 'TRUE', 'Issue Date' => '2026-04-01',
        ]], 0);
        $row = RatingsHelper::parse($csv, $this->ytc())[0];

        $this->assertSame('845', $row['tcc'], 'YTC goes in the main rating');
        $this->assertSame('820', $row['non_spi_tcc'], 'White sail goes in the second rating');
        $this->assertSame('Sadler 32', $row['boat_type']);
        $this->assertSame('Holyhead SC', $row['club']);
        $this->assertSame('TRUE', $row['provisional']);
        $this->assertSame('77', $row['cert_no']);
        $this->assertSame('2026-04-01', $row['issue_date'], 'yyyy-mm-dd date');
    }

    public function testColumnsMayComeInAnyOrderWithExtraColumns(): void
    {
        $csv  = "Owner,TCC,Sail No,Boat Name\nSomeone,1.000,IRL1,Mixed Up\n";
        $rows = RatingsHelper::parse($csv, $this->irc());

        $this->assertSame('Mixed Up', $rows[0]['boat_name']);
        $this->assertSame('1.000', $rows[0]['tcc']);
        $this->assertSame('IRL1', $rows[0]['sail_no']);
    }

    public function testByteOrderMarkAtTheStartIsIgnored(): void
    {
        $rows = RatingsHelper::parse("\xEF\xBB\xBF" . "Boat Name,Sail No,TCC\nBom Boat,1,1.000\n", $this->irc());
        $this->assertSame('Bom Boat', $rows[0]['boat_name']);
    }

    public function testWindowsEncodedNamesAreConverted(): void
    {
        $csv  = "Boat Name,Sail No,TCC\n" . mb_convert_encoding('Aoife Áine,IRL2,1.000', 'Windows-1252', 'UTF-8') . "\n";
        $rows = RatingsHelper::parse($csv, $this->irc());

        $this->assertSame('Aoife Áine', $rows[0]['boat_name']);
    }

    public function testQuotedNamesWithCommasAndQuotes(): void
    {
        $csv  = "Boat Name,Sail No,TCC\n\"Rock, Paper \"\"Scissors\"\"\",IRL3,1.000\n";
        $rows = RatingsHelper::parse($csv, $this->irc());

        $this->assertSame('Rock, Paper "Scissors"', $rows[0]['boat_name']);
    }

    public function testRowsWithoutANameOrAProperRatingAreSkipped(): void
    {
        $csv = "Boat Name,Sail No,TCC\n,IRL4,1.000\nNo Rating,IRL5,\nWord Rating,IRL6,TBC\nGood,IRL7,0.987\n\n";
        $rows = RatingsHelper::parse($csv, $this->irc());

        $this->assertCount(1, $rows);
        $this->assertSame('Good', $rows[0]['boat_name']);
    }

    public function testBadDatesAndNonNumericExtrasBecomeEmpty(): void
    {
        $csv = Env::ircCsv([
            ['Boat Name' => 'A', 'TCC' => '1.000', 'Issue Date' => '31/02/2026', 'Non Spi TCC' => 'n/a', 'Crew' => 'six', 'Cert Year' => '-'],
            ['Boat Name' => 'B', 'TCC' => '1.000', 'Issue Date' => '2026-13-01'],
            ['Boat Name' => 'C', 'TCC' => '1.000', 'Issue Date' => 'March 2026'],
        ], 0);
        $rows = RatingsHelper::parse($csv, $this->irc());

        $this->assertCount(3, $rows);

        foreach ($rows as $row) {
            $this->assertSame('', $row['issue_date'], 'Issue date of ' . $row['boat_name']);
        }

        $this->assertSame('', $rows[0]['non_spi_tcc']);
        $this->assertSame('', $rows[0]['crew']);
        $this->assertSame('', $rows[0]['cert_year']);
    }

    public function testVeryLongTextIsCutToFitTheDatabase(): void
    {
        $csv  = "Boat Name,Sail No,TCC\nLong," . str_repeat('X', 400) . ",1.000\n";
        $rows = RatingsHelper::parse($csv, $this->irc());

        $this->assertSame(150, \strlen($rows[0]['sail_no']));
    }

    public function testMissingColumnIsReportedByName(): void
    {
        $this->assertThrows(function () {
            RatingsHelper::parse("Boat Name,Sail No,Rating\nA,1,1.000\n", $this->irc());
        }, 'column "TCC" is missing');

        $this->assertThrows(function () {
            RatingsHelper::parse("Boat Name,Sail No,TCC\nA,1,1.000\n", $this->ytc());
        }, 'column "Sail Number" is missing');
    }

    public function testEmptyListIsReported(): void
    {
        $this->assertThrows(function () {
            RatingsHelper::parse('', $this->irc());
        }, 'empty');
    }

    public function testWebPageInsteadOfAListIsReported(): void
    {
        $this->assertThrows(function () {
            RatingsHelper::parse("<!DOCTYPE html>\n<html><body>Sign in to continue</body></html>", $this->ytc());
        }, 'is missing');
    }

    public function testRatingsArePrintedTheWayEachSystemPublishesThem(): void
    {
        $this->assertSame('1.050', RatingsHelper::formatRating('1.05', $this->irc()));
        $this->assertSame('0.987', RatingsHelper::formatRating(0.987, $this->irc()));
        $this->assertSame('845', RatingsHelper::formatRating('845.000', $this->ytc()));
        $this->assertSame('', RatingsHelper::formatRating(null, $this->irc()));
        $this->assertSame('', RatingsHelper::formatRating('', $this->ytc()));
    }
}
