<?php

namespace Isora\Component\Isorairc\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

/**
 * Fills in the YB Tracking fleet information sheet for one race.
 *
 * The workbook is YB's own sheet with the boat rows emptied
 * (resources/yb-fleetsheet-template.xlsx); only the boat rows and the
 * leaderboard group headings are written. The Serial and Tracker ID columns
 * are left empty for whoever hands out the trackers.
 */
class FleetsheetHelper
{
    public const DEFAULT_TYPE = 'MEDIUM MONOHULL';

    public const DEFAULT_FLAGS = "Ireland=IE\nNorthern Ireland=GB\nEngland=GB\nWales=XW";

    public const DEFAULT_BANDS = "IRC Class 0=1.070\nIRC Class 1=1.010\nIRC Class 2=0";

    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const SHEET = 'xl/worksheets/sheet1.xml';

    /** Row of the column headings, and of the first boat. */
    private const HEADER_ROW = 5;

    private const FIRST_ROW = 7;

    /** First leaderboard group column (R). */
    private const FIRST_GROUP = 18;

    /**
     * Country on the entry form => flag code, lower-cased keys.
     *
     * @return array<string, string>
     */
    public static function flags(): array
    {
        $text  = (string) ComponentHelper::getParams('com_isorairc')->get('fleet_flags', self::DEFAULT_FLAGS);
        $flags = [];

        foreach (self::pairs(trim($text) !== '' ? $text : self::DEFAULT_FLAGS) as [$country, $code]) {
            $flags[mb_strtolower($country)] = $code;
        }

        return $flags;
    }

    /**
     * IRC class bands as [heading, lowest TCC], highest band first.
     *
     * @return array<int, array{0: string, 1: float}>
     */
    public static function bands(): array
    {
        $text  = (string) ComponentHelper::getParams('com_isorairc')->get('fleet_bands', self::DEFAULT_BANDS);
        $bands = [];

        foreach (self::pairs(trim($text) !== '' ? $text : self::DEFAULT_BANDS) as [$label, $from]) {
            if (is_numeric($from)) {
                $bands[] = [$label, (float) $from];
            }
        }

        usort($bands, static function ($a, $b) {
            return $b[1] <=> $a[1];
        });

        return $bands;
    }

    /**
     * The sheet's rows for a race's entries: one array per boat with the
     * values for name, handicap, owner, skipper, type, model, flag, sail,
     * image and the list of group headings the boat belongs to.
     *
     * @param object[] $entries from EntriesHelper::entries()
     *
     * @return array<int, array<string, mixed>>
     */
    public static function rows(array $entries): array
    {
        $type  = trim((string) ComponentHelper::getParams('com_isorairc')->get('fleet_type', self::DEFAULT_TYPE)) ?: self::DEFAULT_TYPE;
        $flags = self::flags();
        $bands = self::bands();
        $rows  = [];

        foreach ($entries as $entry) {
            $isIrc  = $entry->scheme === 'irc';
            $groups = ['Line Honours'];

            if ($isIrc) {
                $groups[] = 'IRC Overall';

                if ($entry->rating !== null) {
                    foreach ($bands as [$label, $from]) {
                        if ($entry->rating >= $from) {
                            $groups[] = $label;

                            break;
                        }
                    }
                }

                if (preg_match('/2[\s-]*hand|two[\s-]*hand|double[\s-]*hand/i', $entry->class)) {
                    $groups[] = 'IRC 2-Handed';
                }
            }

            $rows[] = [
                'name'     => $entry->boat,
                // YB's handicap columns are time correction factors, so only IRC goes in.
                'handicap' => $isIrc ? $entry->rating : null,
                'owner'    => $entry->contact,
                'skipper'  => $entry->contact,
                'type'     => $type,
                'model'    => $entry->model,
                'flag'     => $flags[mb_strtolower(trim((string) ($entry->country ?? '')))] ?? '',
                'sail'     => $entry->sail,
                'image'    => self::imageUrl((string) ($entry->photo ?? '')),
                'groups'   => $groups,
            ];
        }

        return $rows;
    }

    /**
     * Build the workbook and return its contents.
     *
     * @param object[] $entries from EntriesHelper::entries()
     */
    public static function build(array $entries): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('The PHP zip extension is needed to create the fleetsheet.');
        }

        $template = JPATH_ADMINISTRATOR . '/components/com_isorairc/resources/yb-fleetsheet-template.xlsx';
        $tmpDir   = rtrim((string) Factory::getApplication()->get('tmp_path', sys_get_temp_dir()), '/\\');
        $file     = tempnam(is_dir($tmpDir) && is_writable($tmpDir) ? $tmpDir : sys_get_temp_dir(), 'ybfleet');

        if (!$file || !copy($template, $file)) {
            throw new \RuntimeException('Could not prepare the fleetsheet template.');
        }

        try {
            $zip = new \ZipArchive();

            if ($zip->open($file) !== true) {
                throw new \RuntimeException('Could not open the fleetsheet template.');
            }

            $xml = self::fill((string) $zip->getFromName(self::SHEET), self::rows($entries));
            $zip->addFromString(self::SHEET, $xml);
            $zip->close();

            return (string) file_get_contents($file);
        } finally {
            @unlink($file);
        }
    }

    /**
     * Write the group headings and the boat rows into the sheet's XML.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    private static function fill(string $xml, array $rows): string
    {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = true;

        if (!$xml || !$dom->loadXML($xml)) {
            throw new \RuntimeException('The fleetsheet template is damaged.');
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('m', self::NS);

        $sheetData = $xpath->query('//m:sheetData')->item(0);
        $header    = $xpath->query('m:row[@r="' . self::HEADER_ROW . '"]', $sheetData)->item(0);
        $proto     = $xpath->query('m:row[@r="' . self::FIRST_ROW . '"]', $sheetData)->item(0);

        if (!$sheetData || !$header || !$proto) {
            throw new \RuntimeException('The fleetsheet template does not have the expected rows.');
        }

        // Group headings: Line Honours, IRC Overall, one per class band, IRC 2-Handed.
        $groups = ['Line Honours', 'IRC Overall'];

        foreach (self::bands() as [$label]) {
            $groups[] = $label;
        }

        $groups[]  = 'IRC 2-Handed';
        $lastGroup = self::FIRST_GROUP + \count($groups) - 1;

        // Styles of the template's heading and boat cells, by column number.
        $headStyles = self::styles($xpath, $header);
        $rowStyles  = self::styles($xpath, $proto);
        $templateLastGroup = max(array_keys($rowStyles));

        foreach ($xpath->query('m:c', $header) as $cell) {
            if (self::column($cell->getAttribute('r')) > self::FIRST_GROUP) {
                $header->removeChild($cell);
            }
        }

        foreach ($groups as $i => $label) {
            $col = self::FIRST_GROUP + $i;

            if ($i === 0) {
                continue; // The template's own "Line Honours" heading stays.
            }

            $style = $col === $lastGroup
                ? ($headStyles[$templateLastGroup] ?? $headStyles[self::FIRST_GROUP + 1] ?? null)
                : ($headStyles[self::FIRST_GROUP + 1] ?? null);
            $header->appendChild(self::cell($dom, $col, self::HEADER_ROW, $label, $style));
        }

        // Boat rows replace the template's single blank row.
        $after = $proto->nextSibling;
        $sheetData->removeChild($proto);
        $count = max(1, \count($rows));

        for ($i = 0; $i < $count; $i++) {
            $number = self::FIRST_ROW + $i;
            $data   = $rows[$i] ?? null;
            $row    = $dom->createElementNS(self::NS, 'row');
            $row->setAttribute('r', (string) $number);

            foreach (['spans', 'ht', 'customHeight'] as $attribute) {
                if ($proto->hasAttribute($attribute)) {
                    $row->setAttribute($attribute, $proto->getAttribute($attribute));
                }
            }

            if ($i === 0 && $proto->hasAttribute('thickTop')) {
                $row->setAttribute('thickTop', $proto->getAttribute('thickTop'));
            }

            $values = $data ? [
                3  => $data['name'],
                4  => $data['handicap'],
                7  => $data['owner'],
                8  => $data['skipper'],
                9  => $data['type'],
                10 => $data['model'],
                11 => $data['flag'],
                12 => $data['sail'],
                13 => $data['image'],
            ] : [];

            foreach ($groups as $g => $label) {
                $values[self::FIRST_GROUP + $g] = $data && \in_array($label, $data['groups'], true) ? 'X' : null;
            }

            for ($col = 1; $col <= $lastGroup; $col++) {
                if ($col >= self::FIRST_GROUP) {
                    $style = $col === $lastGroup ? ($rowStyles[$templateLastGroup] ?? null) : ($rowStyles[self::FIRST_GROUP] ?? null);
                } else {
                    $style = $rowStyles[$col] ?? null;
                }

                $row->appendChild(self::cell($dom, $col, $number, $values[$col] ?? null, $style));
            }

            $sheetData->insertBefore($row, $after);
        }

        $dimension = $xpath->query('//m:dimension')->item(0);

        if ($dimension) {
            $dimension->setAttribute('ref', 'A1:' . self::letters(max($lastGroup, 25)) . (self::FIRST_ROW + $count - 1));
        }

        return $dom->saveXML();
    }

    /**
     * One cell: text is stored inline, numbers as numbers, null as an empty styled cell.
     *
     * @param string|float|int|null $value
     */
    private static function cell(\DOMDocument $dom, int $col, int $row, $value, ?string $style): \DOMElement
    {
        $cell = $dom->createElementNS(self::NS, 'c');
        $cell->setAttribute('r', self::letters($col) . $row);

        if ($style !== null && $style !== '') {
            $cell->setAttribute('s', $style);
        }

        if ($value === null || $value === '') {
            return $cell;
        }

        if (\is_float($value) || \is_int($value)) {
            $cell->appendChild($dom->createElementNS(self::NS, 'v', (string) $value));

            return $cell;
        }

        // Strip characters that are not allowed in XML.
        $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string) $value);
        $cell->setAttribute('t', 'inlineStr');
        $is = $dom->createElementNS(self::NS, 'is');
        $t  = $dom->createElementNS(self::NS, 't');
        $t->appendChild($dom->createTextNode((string) $text));
        $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        $is->appendChild($t);
        $cell->appendChild($is);

        return $cell;
    }

    /**
     * Style ids of a row's cells, keyed by column number.
     *
     * @return array<int, string>
     */
    private static function styles(\DOMXPath $xpath, \DOMElement $row): array
    {
        $styles = [];

        foreach ($xpath->query('m:c', $row) as $cell) {
            if ($cell->hasAttribute('s')) {
                $styles[self::column($cell->getAttribute('r'))] = $cell->getAttribute('s');
            }
        }

        return $styles;
    }

    /** Column number of a cell reference such as "R5". */
    private static function column(string $reference): int
    {
        $number = 0;

        foreach (str_split(preg_replace('/[^A-Z]/', '', strtoupper($reference))) as $letter) {
            $number = $number * 26 + (\ord($letter) - 64);
        }

        return $number;
    }

    /** Column letters for a column number (1 = A). */
    private static function letters(int $number): string
    {
        $letters = '';

        while ($number > 0) {
            $number--;
            $letters = \chr(65 + $number % 26) . $letters;
            $number  = intdiv($number, 26);
        }

        return $letters;
    }

    /**
     * Web address of a photo uploaded on the entry form. RSForm stores the
     * file's path on the server.
     */
    private static function imageUrl(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));

        if ($path === '') {
            return '';
        }

        // Only the first file when several were uploaded.
        $path = trim(preg_split('/\R/', $path)[0]);

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $root = rtrim(str_replace('\\', '/', JPATH_ROOT), '/') . '/';

        if (strpos($path, $root) === 0) {
            $relative = substr($path, \strlen($root));
        } elseif (preg_match('#/((?:components|images|media)/.+)$#', $path, $match)) {
            // The site has moved since the file was uploaded.
            $relative = $match[1];
        } else {
            return '';
        }

        return rtrim(Uri::root(), '/') . '/' . implode('/', array_map('rawurlencode', explode('/', $relative)));
    }

    /**
     * "Name=value" lines as [name, value] pairs.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private static function pairs(string $text): array
    {
        $pairs = [];

        foreach (preg_split('/\R/', $text) as $line) {
            if (strpos($line, '=') === false) {
                continue;
            }

            [$name, $value] = array_map('trim', explode('=', $line, 2));

            if ($name !== '') {
                $pairs[] = [$name, $value];
            }
        }

        return $pairs;
    }
}
