<?php

namespace IsoraTests\Cases;

use Isora\Component\Isorairc\Administrator\Helper\FleetsheetHelper;
use IsoraTests\Env;
use IsoraTests\SiteTest;

/**
 * The YB Tracking fleetsheet: what goes in each column, and the workbook itself.
 */
class FleetsheetTest extends SiteTest
{
    /** An entry as EntriesHelper::entries() returns it. */
    private function entry(array $values = []): object
    {
        return (object) ($values + [
            'boat' => 'Sea Breeze', 'sail' => 'IRL 1234', 'model' => 'J/109', 'class' => 'IRC', 'contact' => 'Pat Skipper',
            'country' => 'Ireland', 'photo' => '', 'tracker' => '', 'scheme' => 'irc', 'rating' => 1.045,
        ]);
    }

    private function row(array $values = []): array
    {
        return FleetsheetHelper::rows([$this->entry($values)])[0];
    }

    /**
     * Open a built workbook and return its cells as [reference => value],
     * e.g. ['C7' => 'Sea Breeze', 'D7' => 1.045].
     */
    private function cells(string $xlsx, ?array &$types = null): array
    {
        $file = $this->temp($xlsx);
        $zip  = new \ZipArchive();
        $this->assertTrue($zip->open($file) === true, 'The workbook opens as a zip file');
        $xml     = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $strings = (string) $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();
        unlink($file);

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml), 'The sheet is well-formed XML');
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        // The template's own headings are stored in the workbook's shared text list.
        $shared = [];
        $list   = new \DOMDocument();

        if ($strings !== '' && $list->loadXML($strings)) {
            foreach ($list->getElementsByTagName('si') as $item) {
                $shared[] = $item->textContent;
            }
        }

        $cells = [];
        $types = [];

        foreach ($xpath->query('//m:sheetData/m:row/m:c') as $cell) {
            $reference = $cell->getAttribute('r');
            $type      = $cell->getAttribute('t');
            $value     = $xpath->query('m:v', $cell)->item(0);
            $inline    = $xpath->query('m:is/m:t', $cell)->item(0);

            if ($inline) {
                $cells[$reference] = $inline->textContent;
                $types[$reference] = 'text';
            } elseif ($value && $type === 's') {
                $cells[$reference] = $shared[(int) $value->textContent] ?? '';
                $types[$reference] = 'text';
            } elseif ($value) {
                $cells[$reference] = (float) $value->textContent;
                $types[$reference] = 'number';
            }
        }

        return $cells;
    }

    private function temp(string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'fleet');
        file_put_contents($file, $contents);

        return $file;
    }

    // ------------------------------------------------------------ settings

    public function testDefaultFlagCodes(): void
    {
        $this->assertSame(['ireland' => 'IE', 'northern ireland' => 'GB', 'england' => 'GB', 'wales' => 'XW'], FleetsheetHelper::flags());
    }

    public function testFlagCodesFromTheOptions(): void
    {
        Env::setParams(['fleet_flags' => "Scotland = GB\r\nIsle of Man=IM\nnot a pair\n=XX\nFrance=FR"]);

        $this->assertSame(['scotland' => 'GB', 'isle of man' => 'IM', 'france' => 'FR'], FleetsheetHelper::flags());
    }

    public function testDefaultClassBandsAreTheNoticeOfRaceLimits(): void
    {
        $this->assertSame([['IRC Class 0', 1.07], ['IRC Class 1', 1.01], ['IRC Class 2', 0.0]], FleetsheetHelper::bands());
    }

    public function testClassBandsAreSortedHighestFirstAndBadLinesIgnored(): void
    {
        Env::setParams(['fleet_bands' => "Small=0\nBig=1.100\nMiddle=1.000\nBroken=abc"]);

        $this->assertSame([['Big', 1.1], ['Middle', 1.0], ['Small', 0.0]], FleetsheetHelper::bands());
    }

    public function testBlankSettingsFallBackToTheDefaults(): void
    {
        Env::setParams(['fleet_bands' => ' ', 'fleet_flags' => '', 'fleet_type' => '']);

        $this->assertCount(3, FleetsheetHelper::bands());
        $this->assertCount(4, FleetsheetHelper::flags());
        $this->assertSame('MEDIUM MONOHULL', $this->row()['type']);
    }

    // ------------------------------------------------------------ one boat's row

    public function testBoatDetailsGoInTheRightPlaces(): void
    {
        $row = $this->row();

        $this->assertSame('Sea Breeze', $row['name']);
        $this->assertNumber(1.045, $row['handicap']);
        $this->assertSame('Pat Skipper', $row['owner']);
        $this->assertSame('Pat Skipper', $row['skipper']);
        $this->assertSame('MEDIUM MONOHULL', $row['type']);
        $this->assertSame('J/109', $row['model']);
        $this->assertSame('IE', $row['flag']);
        $this->assertSame('IRL 1234', $row['sail']);
        $this->assertSame('', $row['image']);
    }

    public function testClassBandBoundaries(): void
    {
        $expected = [
            '1.250' => 'IRC Class 0', '1.070' => 'IRC Class 0', '1.069' => 'IRC Class 1', '1.010' => 'IRC Class 1',
            '1.009' => 'IRC Class 2', '0.850' => 'IRC Class 2',
        ];

        foreach ($expected as $rating => $band) {
            $this->assertSame(['Line Honours', 'IRC Overall', $band], $this->row(['rating' => (float) $rating])['groups'], 'TCC ' . $rating);
        }
    }

    public function testIrcBoatWithoutARatingIsInOverallButNoBand(): void
    {
        $row = $this->row(['rating' => null]);

        $this->assertSame(['Line Honours', 'IRC Overall'], $row['groups']);
        $this->assertSame(null, $row['handicap']);
    }

    public function testTwoHandedBoatsAreAlsoInTheTwoHandedGroup(): void
    {
        foreach (['IRC 2-Handed', 'IRC 2 handed', 'IRC Two Handed', 'IRC Double-Handed', 'irc 2handed'] as $class) {
            $this->assertSame(['Line Honours', 'IRC Overall', 'IRC Class 1', 'IRC 2-Handed'], $this->row(['class' => $class])['groups'], $class);
        }
    }

    public function testYtcBoatHasLineHonoursOnlyAndNoHandicap(): void
    {
        $row = $this->row(['class' => 'YTC', 'scheme' => 'ytc', 'rating' => 845.0]);

        $this->assertSame(['Line Honours'], $row['groups']);
        $this->assertSame(null, $row['handicap'], 'A YTC number is not a time correction factor');
    }

    public function testFlagFollowsTheOwnersCountry(): void
    {
        foreach (['Ireland' => 'IE', 'Northern Ireland' => 'GB', 'England' => 'GB', 'Wales' => 'XW', ' wales ' => 'XW', 'IRELAND' => 'IE', 'France' => '', '' => ''] as $country => $flag) {
            $this->assertSame($flag, $this->row(['country' => $country])['flag'], 'Country "' . $country . '"');
        }
    }

    public function testTypeComesFromTheOptions(): void
    {
        Env::setParams(['fleet_type' => 'LARGE MONOHULL']);

        $this->assertSame('LARGE MONOHULL', $this->row()['type']);
    }

    public function testCustomBandsAreUsedForGrouping(): void
    {
        Env::setParams(['fleet_bands' => "Fast=1.050\nSlow=0"]);

        $this->assertSame(['Line Honours', 'IRC Overall', 'Slow'], $this->row()['groups']);
        $this->assertSame(['Line Honours', 'IRC Overall', 'Fast'], $this->row(['rating' => 1.05])['groups']);
    }

    public function testPhotoUploadedOnThisSiteBecomesAWebAddress(): void
    {
        $row = $this->row(['photo' => JPATH_ROOT . '/components/com_rsform/uploads/my boat (1).jpg']);

        $this->assertSame(Env::$siteUrl . '/components/com_rsform/uploads/my%20boat%20%281%29.jpg', $row['image']);
    }

    public function testPhotoUploadedBeforeTheSiteMovedStillGetsAnAddress(): void
    {
        $row = $this->row(['photo' => '/home/oldhost/public_html/components/com_rsform/uploads/boat.jpg']);

        $this->assertSame(Env::$siteUrl . '/components/com_rsform/uploads/boat.jpg', $row['image']);
    }

    public function testWindowsStylePhotoPathIsUnderstood(): void
    {
        $row = $this->row(['photo' => 'C:\\inetpub\\site\\images\\boats\\boat.jpg']);

        $this->assertSame(Env::$siteUrl . '/images/boats/boat.jpg', $row['image']);
    }

    public function testOnlyTheFirstOfSeveralPhotosIsUsed(): void
    {
        $row = $this->row(['photo' => "https://example.invalid/a.jpg\nhttps://example.invalid/b.jpg"]);

        $this->assertSame('https://example.invalid/a.jpg', $row['image']);
    }

    public function testPhotoPathOutsideTheWebsiteIsLeftOut(): void
    {
        $this->assertSame('', $this->row(['photo' => '/tmp/phpA1b2C3'])['image']);
    }

    // ------------------------------------------------------------ the workbook

    public function testWorkbookKeepsEveryPartOfYbsTemplate(): void
    {
        $template = new \ZipArchive();
        $template->open(JPATH_ADMINISTRATOR . '/components/com_isorairc/resources/yb-fleetsheet-template.xlsx');
        $built = new \ZipArchive();
        $built->open($file = $this->temp(FleetsheetHelper::build([$this->entry()])));

        for ($i = 0; $i < $template->numFiles; $i++) {
            $name = $template->getNameIndex($i);
            $this->assertTrue($built->locateName($name) !== false, $name . ' is in the workbook');

            if ($name !== 'xl/worksheets/sheet1.xml') {
                $this->assertTrue($template->getFromIndex($i) === $built->getFromName($name), $name . ' is unchanged');
            }
        }

        $this->assertSame($template->numFiles, $built->numFiles, 'Number of parts');
        $template->close();
        $built->close();
        unlink($file);
    }

    public function testBoatRowIsWrittenInYbsColumns(): void
    {
        $cells = $this->cells(FleetsheetHelper::build([$this->entry(['class' => 'IRC 2-Handed'])]), $types);

        $this->assertSame('Sea Breeze', $cells['C7'] ?? null, 'Name');
        $this->assertNumber(1.045, $cells['D7'] ?? null, 'TCF1');
        $this->assertSame('number', $types['D7'] ?? null, 'The handicap is a number, not text');
        $this->assertSame('Pat Skipper', $cells['G7'] ?? null, 'Owner');
        $this->assertSame('Pat Skipper', $cells['H7'] ?? null, 'Skipper');
        $this->assertSame('MEDIUM MONOHULL', $cells['I7'] ?? null, 'Type');
        $this->assertSame('J/109', $cells['J7'] ?? null, 'Model');
        $this->assertSame('IE', $cells['K7'] ?? null, 'Flag');
        $this->assertSame('IRL 1234', $cells['L7'] ?? null, 'Sail number');
        $this->assertSame('text', $types['L7'] ?? null);
    }

    public function testOwnTrackersNumberGoesInTheTrackerIdColumn(): void
    {
        $cells = $this->cells(FleetsheetHelper::build([
            $this->entry(['tracker' => '0412345']),
            $this->entry(['boat' => 'Second']),
            $this->entry(['boat' => 'Third', 'tracker' => ' YB3-7781 ']),
        ]), $types);

        $this->assertSame('0412345', $cells['B7'] ?? null, 'Tracker ID of the first boat');
        $this->assertSame('text', $types['B7'] ?? null, 'So that leading zeros survive');
        $this->assertFalse(isset($cells['B8']), 'A boat without its own tracker has no Tracker ID');
        $this->assertSame('YB3-7781', $cells['B9'] ?? null);

        foreach (['A7', 'A8', 'A9'] as $reference) {
            $this->assertFalse(isset($cells[$reference]), 'Serial ' . $reference . ' has a value');
        }

        $this->assertSame('Sea Breeze', $cells['C7'] ?? null, 'The other columns have not moved');
    }

    public function testEntryWithoutTrackerDetailsStillBuilds(): void
    {
        $entry = $this->entry();
        unset($entry->tracker);

        $this->assertSame('', FleetsheetHelper::rows([$entry])[0]['tracker']);
    }

    public function testSerialAndTrackerIdAreLeftEmptyForBoatsWithoutTheirOwnTracker(): void
    {
        $cells = $this->cells(FleetsheetHelper::build([$this->entry(), $this->entry(['boat' => 'Second'])]));

        foreach (['A7', 'B7', 'A8', 'B8'] as $reference) {
            $this->assertFalse(isset($cells[$reference]), $reference . ' has a value');
        }
    }

    public function testGroupHeadingsAndCrosses(): void
    {
        $cells = $this->cells(FleetsheetHelper::build([
            $this->entry(['boat' => 'Big', 'rating' => 1.2]),
            $this->entry(['boat' => 'Small Two-Hander', 'rating' => 0.95, 'class' => 'IRC 2-Handed']),
            $this->entry(['boat' => 'Cruiser', 'scheme' => 'ytc', 'class' => 'YTC', 'rating' => 850.0]),
        ]));

        $headings = [];

        foreach (['R', 'S', 'T', 'U', 'V', 'W', 'X'] as $column) {
            $headings[$column] = $cells[$column . '5'] ?? null;
        }

        $this->assertSame(
            ['R' => 'Line Honours', 'S' => 'IRC Overall', 'T' => 'IRC Class 0', 'U' => 'IRC Class 1', 'V' => 'IRC Class 2', 'W' => 'IRC 2-Handed', 'X' => null],
            $headings
        );

        $crosses = static function (int $row) use ($cells) {
            $marked = '';

            foreach (['R', 'S', 'T', 'U', 'V', 'W'] as $column) {
                $marked .= ($cells[$column . $row] ?? '') === 'X' ? $column : '';
            }

            return $marked;
        };

        $this->assertSame('RST', $crosses(7), 'Big: line honours, overall, class 0');
        $this->assertSame('RSVW', $crosses(8), 'Small two-hander: line honours, overall, class 2, 2-handed');
        $this->assertSame('R', $crosses(9), 'YTC cruiser: line honours only');
        $this->assertFalse(isset($cells['D9']), 'YTC cruiser has no handicap');
    }

    public function testCustomBandsChangeTheHeadings(): void
    {
        Env::setParams(['fleet_bands' => "Class A=1.100\nClass B=1.050\nClass C=1.000\nClass D=0"]);
        $cells = $this->cells(FleetsheetHelper::build([$this->entry()]));

        $this->assertSame('Class A', $cells['T5'] ?? null);
        $this->assertSame('Class D', $cells['W5'] ?? null);
        $this->assertSame('IRC 2-Handed', $cells['X5'] ?? null);
        $this->assertSame('X', $cells['V7'] ?? null, '1.045 is in Class C');
        $this->assertFalse(isset($cells['U7']), '1.045 is not in Class B');
    }

    public function testOneRowPerBoatInTheOrderGiven(): void
    {
        $entries = [];

        for ($i = 1; $i <= 40; $i++) {
            $entries[] = $this->entry(['boat' => 'Boat ' . $i, 'sail' => (string) $i]);
        }

        $cells = $this->cells(FleetsheetHelper::build($entries));

        $this->assertSame('Boat 1', $cells['C7']);
        $this->assertSame('Boat 40', $cells['C46']);
        $this->assertFalse(isset($cells['C47']), 'Nothing after the last boat');
    }

    public function testRaceWithNoEntriesStillGivesAValidEmptySheet(): void
    {
        $cells = $this->cells(FleetsheetHelper::build([]));

        $this->assertSame('Line Honours', $cells['R5'] ?? null);
        $this->assertFalse(isset($cells['C7']));
    }

    public function testAwkwardCharactersInNamesDoNotBreakTheWorkbook(): void
    {
        $name  = "Fish & Chips <\"Tír na nÓg\"> 'é' \u{1F6A2}";
        $cells = $this->cells(FleetsheetHelper::build([$this->entry(['boat' => $name . "\x07", 'contact' => "Seán O'Brien & Sons"])]));

        $this->assertSame($name, $cells['C7'], 'Name, with the unprintable character removed');
        $this->assertSame("Seán O'Brien & Sons", $cells['G7']);
    }

    public function testNumericLookingSailNumberStaysText(): void
    {
        $this->cells(FleetsheetHelper::build([$this->entry(['sail' => '007'])]), $types);

        $this->assertSame('text', $types['L7'], 'So that leading zeros survive');
    }

    public function testTemplateHasNoBoatsOrTrackersLeftInIt(): void
    {
        $cells = $this->cells(file_get_contents(JPATH_ADMINISTRATOR . '/components/com_isorairc/resources/yb-fleetsheet-template.xlsx'));

        foreach ($cells as $reference => $value) {
            $this->assertTrue((int) preg_replace('/\D/', '', $reference) < 7, 'Template cell ' . $reference . ' holds "' . $value . '"');
        }
    }

    public function testBuildingLeavesNoTemporaryFilesBehind(): void
    {
        $folder = rtrim((string) \Joomla\CMS\Factory::getApplication()->get('tmp_path'), '/');
        $before = glob($folder . '/ybfleet*');
        FleetsheetHelper::build([$this->entry()]);

        $this->assertSame($before, glob($folder . '/ybfleet*'));
    }
}
