<?php

namespace Isora\Component\Isorairc\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;

/**
 * Reads race entries from the RSForm! Pro entry form and matches each boat
 * to its rating. Nothing is copied: the entry form stays the only record.
 */
class EntriesHelper
{
    /** Option name => default RSForm field name. */
    public const FIELD_DEFAULTS = [
        'entry_boat_field'     => 'Boat_Name',
        'entry_sail_field'     => 'Sail_Number',
        'entry_model_field'    => 'Manufacturer_Model',
        'entry_class_field'    => 'Class_Racing',
        'entry_club_field'     => 'Sailing_Club',
        'entry_declared_field' => 'Latest_IRC_TCC',
        'entry_contact_field'  => 'FullName',
        'entry_email_field'    => 'Email',
        'entry_phone_field'    => 'Mobile_Number_for_Main_Contact',
        'entry_category_field' => 'Race_Entry_Categoy',
        'entry_country_field'  => 'Country',
        'entry_photo_field'    => 'Upload_Yacht_Photograph_',
    ];

    /** The RSForm form that holds the entries, or 0 when not set. */
    public static function formId(): int
    {
        return (int) ComponentHelper::getParams('com_isorairc')->get('entry_form_id', 0);
    }

    /** The answer on the entry form that means "entered for this race". */
    public static function yesValue(): string
    {
        return trim((string) ComponentHelper::getParams('com_isorairc')->get('entry_yes_value', 'Yes')) ?: 'Yes';
    }

    /**
     * RSForm field names by role (boat, sail, model, class, club, declared, contact, email, phone, category, country, photo).
     *
     * @return array<string, string>
     */
    public static function fields(): array
    {
        $params = ComponentHelper::getParams('com_isorairc');
        $fields = [];

        foreach (self::FIELD_DEFAULTS as $option => $default) {
            $role          = substr($option, 6, -6);
            $fields[$role] = trim((string) $params->get($option, $default)) ?: $default;
        }

        return $fields;
    }

    /**
     * The race questions on the entry form (single-choice questions that offer
     * the "entered" answer), as field name => caption.
     * Empty when RSForm is not installed or no form is chosen.
     *
     * @return array<string, string>
     */
    public static function raceFields(): array
    {
        $formId = self::formId();

        if (!$formId) {
            return [];
        }

        try {
            $db    = self::db();
            $query = $db->getQuery(true)
                ->select([$db->quoteName('p.ComponentId'), $db->quoteName('p.PropertyName'), $db->quoteName('p.PropertyValue')])
                ->from($db->quoteName('#__rsform_components', 'c'))
                ->join('INNER', $db->quoteName('#__rsform_component_types', 't') . ' ON ' . $db->quoteName('t.ComponentTypeId') . ' = ' . $db->quoteName('c.ComponentTypeId'))
                ->join('INNER', $db->quoteName('#__rsform_properties', 'p') . ' ON ' . $db->quoteName('p.ComponentId') . ' = ' . $db->quoteName('c.ComponentId'))
                ->where($db->quoteName('c.FormId') . ' = ' . $formId)
                ->where($db->quoteName('t.ComponentTypeName') . ' = ' . $db->quote('radioGroup'))
                ->where($db->quoteName('p.PropertyName') . ' IN (' . $db->quote('NAME') . ',' . $db->quote('CAPTION') . ',' . $db->quote('ITEMS') . ')')
                ->order($db->quoteName('c.Order') . ' ASC');
            $byComponent = [];

            foreach ($db->setQuery($query)->loadObjectList() as $row) {
                $byComponent[$row->ComponentId][$row->PropertyName] = $row->PropertyValue;
            }
        } catch (\Throwable $e) {
            return [];
        }

        $fields = [];

        $yes = mb_strtolower(self::yesValue());

        foreach ($byComponent as $properties) {
            // A race question is one that can be answered with the "entered" answer.
            // Items are one per line, as "value|label[flags]" or just "value".
            $answers = [];

            foreach (preg_split('/\R/', (string) ($properties['ITEMS'] ?? '')) as $item) {
                $value     = explode('|', $item, 2)[0];
                $answers[] = mb_strtolower(trim(preg_replace('/\[[^\]]*\]\s*$/', '', $value)));
            }

            if (!empty($properties['NAME']) && \in_array($yes, $answers, true)) {
                $fields[$properties['NAME']] = trim((string) ($properties['CAPTION'] ?? '')) ?: $properties['NAME'];
            }
        }

        return $fields;
    }

    /**
     * Races that are linked to a question on the entry form, in date order.
     *
     * @return object[] keyed by race id
     */
    public static function races(): array
    {
        $db    = self::db();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__isorairc_races'))
            ->where($db->quoteName('entry_field') . ' IS NOT NULL')
            ->where($db->quoteName('entry_field') . " <> ''")
            ->order($db->quoteName('race_date') . ' ASC, ' . $db->quoteName('id') . ' ASC');

        return $db->setQuery($query)->loadObjectList('id') ?: [];
    }

    /**
     * The boats entered for a race, sorted by boat name, each with its rating.
     *
     * A boat that sent the form more than once counts once: its latest form
     * decides. Each entry is an object with: submission_id, submitted, boat,
     * sail, model, class, club, declared, contact, email, phone, category,
     * country, photo, scheme, rating, rating_text, rating_status (ok|check|none|off), rating_note.
     *
     * @return object[]
     */
    public static function entries(object $race): array
    {
        $formId = self::formId();

        if (!$formId || empty($race->entry_field)) {
            return [];
        }

        $fields = self::fields();
        $wanted = array_values($fields);
        $wanted[] = $race->entry_field;

        $db    = self::db();
        $query = $db->getQuery(true)
            ->select([$db->quoteName('s.SubmissionId'), $db->quoteName('s.DateSubmitted'), $db->quoteName('v.FieldName'), $db->quoteName('v.FieldValue')])
            ->from($db->quoteName('#__rsform_submissions', 's'))
            ->join('INNER', $db->quoteName('#__rsform_submission_values', 'v') . ' ON ' . $db->quoteName('v.SubmissionId') . ' = ' . $db->quoteName('s.SubmissionId'))
            ->where($db->quoteName('s.FormId') . ' = ' . $formId)
            ->where($db->quoteName('v.FieldName') . ' IN (' . implode(',', array_map([$db, 'quote'], array_unique($wanted))) . ')')
            ->order($db->quoteName('s.DateSubmitted') . ' ASC, ' . $db->quoteName('s.SubmissionId') . ' ASC');

        $forms = [];

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $id = (int) $row->SubmissionId;

            if (!isset($forms[$id])) {
                $forms[$id] = ['submission_id' => $id, 'submitted' => $row->DateSubmitted, 'values' => []];
            }

            $forms[$id]['values'][$row->FieldName] = trim((string) $row->FieldValue);
        }

        // Later forms from the same boat replace earlier ones.
        $latest = [];

        foreach ($forms as $form) {
            $boat = $form['values'][$fields['boat']] ?? '';
            $sail = $form['values'][$fields['sail']] ?? '';

            if ($boat === '' && $sail === '') {
                continue;
            }

            $latest[self::plain($sail) . '|' . self::plain($boat)] = $form;
        }

        $yes     = mb_strtolower(self::yesValue());
        $entries = [];

        foreach ($latest as $form) {
            if (mb_strtolower($form['values'][$race->entry_field] ?? '') !== $yes) {
                continue;
            }

            $entry = (object) ['submission_id' => $form['submission_id'], 'submitted' => $form['submitted']];

            foreach ($fields as $role => $name) {
                $entry->$role = $form['values'][$name] ?? '';
            }

            $entries[] = $entry;
        }

        usort($entries, static function ($a, $b) {
            return strcasecmp($a->boat, $b->boat) ?: strcasecmp($a->sail, $b->sail);
        });

        self::addRatings($entries, $race);

        return $entries;
    }

    /**
     * Which rating list a race uses for each system: 'snapshot' once the
     * ratings have been frozen for it, otherwise 'current'.
     *
     * @return array<string, string> keyed by scheme
     */
    public static function ratingSources(object $race): array
    {
        $status  = RatingsHelper::snapshotStatus([(int) $race->id])[(int) $race->id] ?? [];
        $sources = [];

        foreach (RatingsHelper::systems() as $scheme => $system) {
            $sources[$scheme] = isset($status[$scheme]) ? 'snapshot' : 'current';
        }

        return $sources;
    }

    /** The rating system a class on the entry form is scored under. */
    public static function schemeForClass(string $class): string
    {
        return stripos(trim($class), 'YTC') === 0 ? 'ytc' : 'irc';
    }

    /** True when the class is scored on the non-spinnaker / white sail rating. */
    public static function isNonSpinnaker(string $class): bool
    {
        return (bool) preg_match('/non[\s-]*spin|white\s*sail/i', $class);
    }

    /**
     * Look up every entry's rating and set scheme, rating, rating_text,
     * rating_status and rating_note on it.
     *
     * @param object[] $entries
     */
    private static function addRatings(array $entries, object $race): void
    {
        $systems = RatingsHelper::systems();
        $sources = self::ratingSources($race);
        $groups  = [];

        foreach ($entries as $entry) {
            $entry->scheme        = self::schemeForClass($entry->class);
            $entry->rating        = null;
            $entry->rating_text   = '';
            $entry->rating_status = isset($systems[$entry->scheme]) ? 'none' : 'off';
            $entry->rating_note   = isset($systems[$entry->scheme]) ? 'Not found in the ' . $systems[$entry->scheme]['label'] . ' list' : '';
            $groups[$entry->scheme][] = $entry;
        }

        foreach ($groups as $scheme => $group) {
            if (!isset($systems[$scheme])) {
                continue;
            }

            $candidates = self::candidates($group, $scheme, $sources[$scheme] === 'snapshot' ? (int) $race->id : 0);

            foreach ($group as $entry) {
                self::match($entry, $candidates, $systems[$scheme]);
            }
        }
    }

    /**
     * Rating rows that share a boat name or sail number with any of the entries.
     *
     * @param object[] $entries
     *
     * @return object[]
     */
    private static function candidates(array $entries, string $scheme, int $raceId): array
    {
        $db    = self::db();
        $sail  = 'UPPER(REPLACE(REPLACE(' . $db->quoteName('sail_no') . ", ' ', ''), '-', ''))";
        $where = [];

        foreach ($entries as $entry) {
            if ($entry->boat !== '') {
                $where[] = 'LOWER(' . $db->quoteName('boat_name') . ') = ' . $db->quote(mb_strtolower($entry->boat));
            }

            $plain  = self::plain($entry->sail);
            $digits = preg_replace('/\D+/', '', $entry->sail);

            if ($plain !== '') {
                $where[] = $sail . ' = ' . $db->quote($plain);
            }

            if (\strlen($digits) >= 2 && $digits !== $plain) {
                $where[] = $sail . ' LIKE ' . $db->quote('%' . $digits);
            }
        }

        if (!$where) {
            return [];
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName(['boat_name', 'sail_no', 'tcc', 'non_spi_tcc', 'secondary', 'issue_date', 'cert_no']))
            ->where($db->quoteName('scheme') . ' = ' . $db->quote($scheme))
            ->where('(' . implode(' OR ', array_unique($where)) . ')');

        if ($raceId) {
            $query->from($db->quoteName('#__isorairc_snapshot_ratings'))->where($db->quoteName('race_id') . ' = ' . $raceId);
        } else {
            $query->from($db->quoteName('#__isorairc_ratings'));
        }

        return $db->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * Pick the best rating row for an entry. Name and sail number both
     * agreeing is a firm match; only one of them agreeing is flagged to check.
     *
     * @param object[] $candidates
     */
    private static function match(object $entry, array $candidates, array $system): void
    {
        $name   = self::plain($entry->boat);
        $sail   = self::plain($entry->sail);
        $digits = preg_replace('/\D+/', '', $entry->sail);
        $best   = null;
        $score  = 0;

        foreach ($candidates as $row) {
            $rowSail   = self::plain((string) $row->sail_no);
            $rowDigits = preg_replace('/\D+/', '', (string) $row->sail_no);
            $nameOk    = $name !== '' && self::plain((string) $row->boat_name) === $name;
            $sailOk    = $sail !== '' && $rowSail === $sail;
            $digitsOk  = !$sailOk && \strlen($digits) >= 2 && $rowDigits === $digits;

            // 8 name, 4 exact sail, 2 same digits, 1 primary certificate.
            $rowScore = ($nameOk ? 8 : 0) + ($sailOk ? 4 : 0) + ($digitsOk ? 2 : 0);

            if ($rowScore === 0) {
                continue;
            }

            $rowScore += empty($row->secondary) ? 1 : 0;

            if ($rowScore > $score) {
                $score = $rowScore;
                $best  = $row;
            }
        }

        if (!$best) {
            return;
        }

        $nonSpinnaker = self::isNonSpinnaker($entry->class);
        $value        = $nonSpinnaker ? $best->non_spi_tcc : $best->tcc;
        $named        = ($score & 8) === 8;
        $sailed       = ($score & 6) !== 0;

        if ($value === null || $value === '') {
            $entry->rating_status = 'check';
            $entry->rating_note   = 'Listed as ' . $best->boat_name . ' ' . $best->sail_no . ' but with no non-spinnaker rating';

            return;
        }

        $entry->rating      = (float) $value;
        $entry->rating_text = RatingsHelper::formatRating($value, $system);

        if ($named && $sailed) {
            $entry->rating_status = 'ok';
            $entry->rating_note   = ($score & 4) ? '' : 'Listed with sail number ' . $best->sail_no;
        } elseif ($named) {
            $entry->rating_status = 'check';
            $entry->rating_note   = 'Boat name matches but the list has sail number ' . ($best->sail_no ?: 'blank');
        } else {
            $entry->rating_status = 'check';
            $entry->rating_note   = 'Sail number matches but the list has boat name ' . $best->boat_name;
        }
    }

    /** Lower case letters and digits only, for comparing names and sail numbers. */
    private static function plain(string $value): string
    {
        return strtoupper(preg_replace('/[^a-z0-9]+/i', '', $value));
    }

    private static function db()
    {
        return Factory::getContainer()->get('DatabaseDriver');
    }
}
