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
        'entry_owntracker_field' => 'OwnTracker',
        'entry_tracker_field'  => 'TrackerSerialNumber',
    ];

    /** RORC's page of boat data for valid IRC certificates. */
    public const DEFAULT_BOAT_DATA_URL = 'https://ircrating.org/boat-data-for-valid-irc-certificates/';

    /**
     * Details from a boat's IRC certificate that are shown with its entry,
     * as entry property => label. Each is a column of the rating tables.
     */
    public const IRC_DETAILS = [
        'issue_date'       => 'Issue date',
        'crew'             => 'Crew',
        'headsails'        => 'Headsails',
        'flying_headsails' => 'Flying headsails',
        'spinnakers'       => 'Spinnakers',
        'sss_base'         => 'SSS base value',
    ];

    /** Name of the search box on that page. */
    public const BOAT_DATA_FIELD = 'pdf_search';

    /**
     * Address of the page where a boat's IRC data can be looked up by
     * certificate number, or '' when the link has been switched off by
     * emptying the setting.
     */
    public static function boatDataUrl(): string
    {
        // A setting that was saved empty means "off"; one never saved means the default.
        $params = ComponentHelper::getParams('com_isorairc');
        $url    = $params->exists('irc_boatdata_url') ? trim((string) $params->get('irc_boatdata_url', '')) : self::DEFAULT_BOAT_DATA_URL;

        return preg_match('#^https?://#i', $url) ? $url : '';
    }

    /**
     * The RSForm form chosen in the options, or 0 when not set. New races are
     * linked to this form; 0 switches the Race entries page off.
     */
    public static function formId(): int
    {
        return (int) ComponentHelper::getParams('com_isorairc')->get('entry_form_id', 0);
    }

    /**
     * The RSForm form that holds a race's entries: the one stored with the race,
     * or the one in the options for a race that has none stored. 0 when the
     * options have no form (the page is switched off).
     */
    public static function raceFormId(object $race): int
    {
        $default = self::formId();

        if (!$default) {
            return 0;
        }

        return (int) ($race->form_id ?? 0) ?: $default;
    }

    /** The answer on the entry form that means "entered for this race". */
    public static function yesValue(): string
    {
        return trim((string) ComponentHelper::getParams('com_isorairc')->get('entry_yes_value', 'Yes')) ?: 'Yes';
    }

    /**
     * RSForm field names by role (boat, sail, model, class, club, declared, contact, email, phone, category, country, photo, owntracker, tracker).
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
     * @param int $formId The form to read; 0 for the one in the options
     *
     * @return array<string, string>
     */
    public static function raceFields(int $formId = 0): array
    {
        $formId = $formId > 0 ? $formId : self::formId();

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

        // The "use your own tracker?" question is answered yes or no too, but is not a race.
        $ownTracker = self::fields()['owntracker'];

        foreach ($byComponent as $properties) {
            if (($properties['NAME'] ?? '') === $ownTracker) {
                continue;
            }

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
     * country, photo, owntracker (the answer to "use your own tracker?"), tracker (the serial
     * number of the boat's own tracker, or '' when it is not using one), scheme, rating, rating_text, rating_status (ok|check|none|off), rating_note,
     * cert_no (certificate number of the rating row the boat was matched to, or ''),
     * certificate ('primary' or 'secondary': the IRC certificate the rating is taken
     * from, or '' when the boat is not on the IRC list), has_secondary (true when the
     * list has a secondary certificate for the boat), certificate_wanted ('secondary'
     * when that was chosen for this race, else 'primary'), certificate_set_at and
     * certificate_set_by (when and by whom the choice was made, or ''),
     * and for a boat matched to an IRC certificate the IRC_DETAILS (issue_date as
     * YYYY-MM-DD, crew, headsails, flying_headsails, spinnakers, sss_base), each ''
     * when the list has no value.
     *
     * @return object[]
     */
    public static function entries(object $race): array
    {
        $formId = self::raceFormId($race);

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

            // A serial number only counts when the boat said it is using its own tracker.
            $entry->tracker = self::saidYes($entry->owntracker) ? $entry->tracker : '';

            $entries[] = $entry;
        }

        usort($entries, static function ($a, $b) {
            return strcasecmp($a->boat, $b->boat) ?: strcasecmp($a->sail, $b->sail);
        });

        self::addRatings($entries, $race);

        return $entries;
    }

    /**
     * One boat's rating on the current list, found the way the Race entries
     * page finds it: by boat name and sail number, in the list its class is
     * scored under (a class starting "YTC" uses the YTC list, a non-spinnaker
     * class the non-spinnaker rating).
     *
     * The answer has boat, sail, class, scheme, rating, rating_text,
     * rating_status (ok|check|none|off), rating_note, cert_no, certificate,
     * has_secondary and the IRC_DETAILS, as an entry from entries() does.
     * A boat with two IRC certificates is given on its primary one.
     */
    public static function lookup(string $boat, string $sail, string $class = 'IRC'): object
    {
        $entry = (object) ['boat' => trim($boat), 'sail' => trim($sail), 'class' => trim($class)];

        // Race 0 is no race: the current lists, and nobody's certificate choice.
        self::addRatings([$entry], (object) ['id' => 0]);

        return $entry;
    }

    /**
     * For a script on the entry form: fill in the boat's IRC rating and the
     * issue date of its IRC certificate from the current IRC list, in the
     * answers being sent (RSForm's $_POST['form']), before they are stored.
     *
     * Only done when the list has a boat with both that name and that sail
     * number and the class is an IRC one. Otherwise the rating is left as
     * typed and the issue date is emptied. The boat name, sail number and
     * class are read from the fields named in the options.
     *
     * Never stops the form: any problem leaves the answers alone.
     *
     * @param array $form    The answers, by field name; changed in place
     * @param array $targets Field names to fill: 'rating' and 'issue_date'. Leave one out, or empty, to skip it.
     *
     * @return object|null What lookup() found, or null when nothing could be looked up
     */
    public static function fillFromIrcList(&$form, array $targets = ['rating' => 'Latest_IRC_TCC', 'issue_date' => 'IRCCertIssueDate']): ?object
    {
        if (!\is_array($form)) {
            return null;
        }

        try {
            $fields = self::fields();
            $answer = static function (string $name) use ($form): string {
                $value = $form[$name] ?? '';

                // A question with choices arrives as a list of the ones ticked.
                return trim((string) (\is_array($value) ? reset($value) : $value));
            };

            $ratingField = trim((string) ($targets['rating'] ?? ''));
            $dateField   = trim((string) ($targets['issue_date'] ?? ''));

            // A date somebody typed or left over is not the list's.
            if ($dateField !== '') {
                $form[$dateField] = '';
            }

            $boat = $answer($fields['boat']);
            $sail = $answer($fields['sail']);

            if ($boat === '' || $sail === '') {
                return null;
            }

            $found = self::lookup($boat, $sail, $answer($fields['class']));

            if ($found->scheme !== 'irc' || $found->rating_status !== 'ok' || $found->rating === null) {
                return $found;
            }

            if ($ratingField !== '') {
                $form[$ratingField] = $found->rating_text;
            }

            if ($dateField !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $found->issue_date)) {
                $form[$dateField] = date_create((string) $found->issue_date, new \DateTimeZone('UTC'))->format('j M Y');
            }

            return $found;
        } catch (\Throwable $e) {
            return null;
        }
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
        $choices = self::certificateChoices((int) $race->id);

        foreach ($entries as $entry) {
            $choice = $choices[self::boatKey($entry->boat, $entry->sail)] ?? null;

            $entry->scheme        = self::schemeForClass($entry->class);
            $entry->certificate   = '';
            $entry->has_secondary = false;
            $entry->certificate_wanted = $choice ? 'secondary' : 'primary';
            $entry->certificate_set_at = $choice ? (string) $choice->set_at : '';
            $entry->certificate_set_by = $choice ? (string) $choice->set_by : '';
            $entry->rating        = null;
            $entry->rating_text   = '';
            $entry->cert_no       = '';

            foreach (array_keys(self::IRC_DETAILS) as $detail) {
                $entry->$detail = '';
            }

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
                // The list names a boat's secondary certificate "NAME - SEC".
                $where[] = 'LOWER(' . $db->quoteName('boat_name') . ') = ' . $db->quote(mb_strtolower($entry->boat) . ' - sec');
            }

            $plain  = self::plain($entry->sail);
            $digits = preg_replace('/\D+/', '', $entry->sail);

            if ($plain !== '') {
                $where[] = $sail . ' = ' . $db->quote($plain);
            }

            // Also when only the digits were typed: "1234" must find "IRL1234".
            if (\strlen($digits) >= 2) {
                $where[] = $sail . ' LIKE ' . $db->quote('%' . $digits);
            }
        }

        if (!$where) {
            return [];
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName(array_merge(['boat_name', 'sail_no', 'tcc', 'non_spi_tcc', 'secondary', 'cert_no'], array_keys(self::IRC_DETAILS))))
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

        // The boat's other certificate: the secondary one that goes with the row found.
        $wanted = $entry->certificate_wanted === 'secondary';
        $pair   = $entry->scheme === 'irc' && empty($best->secondary) ? self::secondaryOf($best, $candidates) : null;
        $unmet  = '';

        if ($entry->scheme === 'irc') {
            $entry->has_secondary = $pair !== null;

            if ($wanted && $pair) {
                $best = $pair;
            } elseif ($wanted && empty($best->secondary)) {
                $unmet = 'Secondary certificate chosen, but the list has none for this boat';
            }

            $entry->certificate = empty($best->secondary) ? 'primary' : 'secondary';
        }

        $entry->cert_no = trim((string) ($best->cert_no ?? ''));

        if ($entry->scheme === 'irc') {
            foreach (array_keys(self::IRC_DETAILS) as $detail) {
                $entry->$detail = trim((string) ($best->$detail ?? ''));
            }
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

        if ($unmet !== '') {
            $entry->rating_status = 'check';
            $entry->rating_note   = trim($entry->rating_note . '. ' . $unmet, '. ');
        }
    }

    /**
     * The secondary certificate that belongs to a primary one: the secondary row
     * with the same sail number, or failing that the same boat name (the list
     * writes a secondary certificate's name as "NAME - SEC").
     *
     * @param object[] $candidates
     */
    private static function secondaryOf(object $primary, array $candidates): ?object
    {
        $sail  = self::plain((string) $primary->sail_no);
        $name  = self::plain((string) $primary->boat_name);
        $named = null;

        foreach ($candidates as $row) {
            if (empty($row->secondary)) {
                continue;
            }

            if ($sail !== '' && self::plain((string) $row->sail_no) === $sail) {
                return $row;
            }

            if ($name !== '' && self::plain(preg_replace('/\s*-?\s*SEC\s*$/i', '', (string) $row->boat_name)) === $name) {
                $named = $row;
            }
        }

        return $named;
    }

    /** How a boat is told apart from the others: its sail number and name, capitals and digits only. */
    public static function boatKey(string $boat, string $sail): string
    {
        return mb_substr(self::plain($sail) . '|' . self::plain($boat), 0, 190);
    }

    /**
     * The boats that chose their secondary certificate for a race, as boat key => row
     * (set_at, set_by). A boat without a row is on its primary certificate.
     *
     * @return array<string, object>
     */
    public static function certificateChoices(int $raceId): array
    {
        $db    = self::db();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['boat_key', 'certificate', 'set_at', 'set_by']))
            ->from($db->quoteName('#__isorairc_cert_choices'))
            ->where($db->quoteName('race_id') . ' = ' . $raceId)
            ->where($db->quoteName('certificate') . ' = ' . $db->quote('secondary'));

        return $db->setQuery($query)->loadObjectList('boat_key') ?: [];
    }

    /**
     * Record which IRC certificate a boat uses for a race. This does not look at
     * the rating deadline: the caller decides who may change it and when.
     *
     * @param string $certificate 'primary' or 'secondary'
     * @param string $by          Who made the choice, for race management to see
     */
    public static function setCertificate(int $raceId, string $boat, string $sail, string $certificate, string $by): void
    {
        if (!\in_array($certificate, ['primary', 'secondary'], true)) {
            throw new \InvalidArgumentException('The certificate must be primary or secondary.');
        }

        $db  = self::db();
        $key = self::boatKey($boat, $sail);

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__isorairc_cert_choices'))
            ->where($db->quoteName('race_id') . ' = ' . $raceId)
            ->where($db->quoteName('boat_key') . ' = ' . $db->quote($key));
        $db->setQuery($query)->execute();

        if ($certificate === 'secondary') {
            $row = (object) [
                'race_id' => $raceId, 'boat_key' => $key, 'certificate' => 'secondary',
                'set_at' => Factory::getDate()->toSql(), 'set_by' => mb_substr($by, 0, 100),
            ];
            $db->insertObject('#__isorairc_cert_choices', $row);
        }
    }

    /**
     * Forget the certificate choices of the given races.
     *
     * @param int[] $raceIds
     */
    public static function deleteCertificateChoices(array $raceIds): void
    {
        $raceIds = array_filter(array_map('intval', $raceIds));

        if (!$raceIds) {
            return;
        }

        $db    = self::db();
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__isorairc_cert_choices'))
            ->where($db->quoteName('race_id') . ' IN (' . implode(',', $raceIds) . ')');
        $db->setQuery($query)->execute();
    }

    /** True when the entry has at least one detail from its IRC certificate. */
    public static function hasIrcDetails(object $entry): bool
    {
        foreach (array_keys(self::IRC_DETAILS) as $detail) {
            if (($entry->$detail ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

    /** True when a yes/no question on the entry form was answered yes. */
    public static function saidYes(string $answer): bool
    {
        $answer = mb_strtolower(trim($answer));

        return $answer !== '' && ($answer === 'yes' || $answer === mb_strtolower(self::yesValue()));
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
