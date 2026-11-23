# ISORA Race Office: the stored rating data

What ISORA Race Office keeps in the website's database, what each value means, and how another part of the Joomla site (a module, a plugin, another component, a script in a form) can read it. Written for version 1.8.0.

For a system outside the website, use the [API](api.md) instead.

## Where the data comes from

| System | Source | Refreshed |
|---|---|---|
| IRC | RORC's published list of valid certificates, a CSV file (`ClubListing.csv`) | By the daily scheduled task, or **Import ratings now** |
| YTC | The published YTC Google Sheet | The same |

Race entries are not stored by the extension. They stay in RSForm! Pro and are read from there each time (see "Entries" below).

## The tables

Table names start with the site's database prefix, written `#__` in Joomla code. On the ISORA site the prefix is `djd_`, so `#__isorairc_ratings` is `djd_isorairc_ratings`.

| Table | Holds | Changes when |
|---|---|---|
| `#__isorairc_ratings` | The current ratings: one row per certificate on the latest download of each list | Every import: that system's rows are deleted and written again |
| `#__isorairc_snapshot_ratings` | The frozen ratings: a full copy of a list for one race | Once per race and system, after the race's rating deadline. Never afterwards, unless the snapshot is rebuilt |
| `#__isorairc_races` | The races and their rating deadlines | When someone edits the races |
| `#__isorairc_race_snapshots` | One row per race and system once its ratings are frozen | When a snapshot is taken or rebuilt |
| `#__isorairc_imports` | A log of every download, and the downloads themselves | Every import adds a row |
| `#__isorairc_cert_choices` | Which boats chose their secondary IRC certificate, per race | When race management or Crewmanager changes a boat's certificate |

All dates with a time are stored in UTC.

## Rating columns

`#__isorairc_ratings` and `#__isorairc_snapshot_ratings` have the same rating columns. The snapshot table also has `race_id`.

| Column | Type | IRC | YTC |
|---|---|---|---|
| `id` | number | Row number. It changes on every import, so do not store it anywhere. | The same |
| `scheme` | text | `irc` | `ytc` |
| `race_id` | number | Snapshot table only: the race the row is frozen for | The same |
| `boat_name` | text | Boat Name, as published (usually capitals) | Boat Name |
| `sail_no` | text | Sail No, usually without a space (`IRL1234`) | Sail Number, as typed on the sheet |
| `tcc` | number, 3 decimals | TCC | YTC rating (a whole number) |
| `non_spi_tcc` | number or empty | Non Spi TCC | YTC White Sail |
| `cert_no` | text | Cert No | EntryID |
| `issue_date` | date or empty | Issue Date | Issue Date |
| `cert_year` | number or empty | Cert Year | not filled |
| `endorsed` | text or empty | Endorsed (`E` on an endorsed certificate) | not filled |
| `secondary` | text or empty | Secondary: `SEC` when the row is a boat's secondary certificate, otherwise empty | not filled |
| `crew` | number or empty | Crew | not filled |
| `headsails` | number or empty | Headsails | not filled |
| `flying_headsails` | number or empty | Flying Headsails | not filled |
| `spinnakers` | number or empty | Spinnakers | not filled |
| `sss_base` | number or empty | SSS Base Value | not filled |
| `valid_code` | text or empty | ValidCode (`Yes` on every row of the list at present) | not filled |
| `boat_type` | text or empty | not filled | Boat Type |
| `club` | text or empty | not filled | Club |
| `provisional` | text or empty | not filled | Provisional (`TRUE` or `FALSE`) |

"Empty" is a database NULL. A count of nought is stored as `0`, which is not the same as empty.

Things to know before using these rows:

- **One boat can have several rows.** An IRC boat with a secondary certificate has two rows with the same sail number; the secondary one has `secondary` set to `SEC` and usually " - SEC" after the boat name. Prefer the row where `secondary` is empty unless you want the secondary certificate.
- **Names and sail numbers are not tidied.** Compare names without regard to capitals, and sail numbers with spaces and hyphens removed.
- **The IRC list has more columns than are kept.** DLR, LH, Beam, Draft, Single Furling Headsail, Series Date, Age Date, Racing Area, STIX, AVS and Category are in the download but not in these tables. They are still in the archive (see below), so a later version could add them, including for past downloads.
- **Values added in a later version are empty in older rows.** The four sail and SSS columns arrived in 1.6.0; a snapshot frozen before that has them empty until it is rebuilt.

## Races

`#__isorairc_races`

| Column | Meaning |
|---|---|
| `id` | The race's number. It does not change when the race is renamed. |
| `name` | The race's name |
| `race_date` | The day of the race |
| `deadline_days` | How many days before the race the ratings are frozen |
| `rating_deadline` | The moment the ratings are frozen, in UTC |
| `entry_field` | The name of the race's question on the entry form, or empty when the race is not linked to the form |

`#__isorairc_race_snapshots`

| Column | Meaning |
|---|---|
| `race_id`, `scheme` | Which race and which system. A row here means that system's ratings are frozen for that race. |
| `taken` | When the snapshot was taken, in UTC |
| `import_id` | The download it was taken from (`#__isorairc_imports.id`) |
| `boat_count` | How many rows were frozen |
| `note` | How it was built, for example how many boats were kept on their pre-deadline rating |

**Which ratings apply to a race:** if `#__isorairc_race_snapshots` has a row for the race and system, use `#__isorairc_snapshot_ratings` with that `race_id`. Otherwise the deadline has not passed (or no import has run since), and the current ratings in `#__isorairc_ratings` apply.

## Certificate choices

`#__isorairc_cert_choices`

A boat is rated on its primary IRC certificate unless it has a row here for the race. Choosing the primary again deletes the row.

| Column | Meaning |
|---|---|
| `race_id` | The race the choice is for |
| `boat_key` | The boat: its sail number and its name as typed on the entry form, each reduced to capitals and digits, joined by a bar (`GBR4822R|MOJITO`) |
| `certificate` | Always `secondary` |
| `set_at` | When it was chosen, in UTC |
| `set_by` | Who chose it: a race manager's name, or `the API` for Crewmanager |

Read and change these through `EntriesHelper` (`certificateChoices()`, `setCertificate()`), which builds the key the same way the entries do. `setCertificate()` does not check the rating deadline; the API does that itself, and race management are allowed to change it at any time.

## The archive of downloads

`#__isorairc_imports`

| Column | Meaning |
|---|---|
| `id` | The download's number |
| `scheme` | `irc` or `ytc` |
| `imported_at` | When it was downloaded, in UTC |
| `feed_modified` | The date the source gave for the file, when it gave one |
| `row_count` | How many boats it had |
| `csv_hash` | A fingerprint of the file, used to spot a download identical to the one before |
| `content_id` | The `id` of the row that holds the file. Identical downloads share one copy. |
| `csv_gz` | The whole downloaded file, compressed and then base64 encoded. Empty when `content_id` points at another row. |

This is the complete history: every list as it was on every day an import ran. It is what makes rebuilding a snapshot possible, and it can answer "what was this boat's rating on a given day". The files are large, so never select `csv_gz` in a list query.

## Entries

Entries are read from RSForm! Pro's own tables (`#__rsform_submissions` and `#__rsform_submission_values`) for the form chosen in the options. The extension does not copy them. Which form question means what (boat name, sail number, class and so on) is set in the options, so read entries through the extension's helper (below) and not from the RSForm tables directly.

## Using the data elsewhere in Joomla

Three rules:

1. **Read only.** Do not write to these tables (certificate choices go through `EntriesHelper::setCertificate()`). The current ratings are replaced on every import, and the frozen ones are the record of what a race was scored on.
2. **Prefer the extension's own code to your own SQL** where it does what you need. It already knows which list applies to a race and how a boat is matched.
3. **The tables can gain columns in a later version.** The [changelog](../CHANGELOG.md) says when. Name the columns you select; avoid `SELECT *`.

The extension's classes can be used from any Joomla code on the site without loading anything first. The two that matter:

```php
use Isora\Component\Isorairc\Administrator\Helper\EntriesHelper;
use Isora\Component\Isorairc\Administrator\Helper\RatingsHelper;
```

### The boats entered for a race, with their ratings

```php
$races = EntriesHelper::races();          // races linked to the entry form, keyed by id, in date order
$race  = $races[7] ?? null;

if ($race) {
    foreach (EntriesHelper::entries($race) as $entry) {
        echo $entry->boat, ' ', $entry->sail, ' ', $entry->rating_text, "\n";
    }
}
```

Each entry has `boat`, `sail`, `model`, `class`, `club`, `scheme` (`irc` or `ytc`), `rating` (a number, or null), `rating_text` (the rating as published), `rating_status` and `rating_note`. For IRC boats on the list it also has `cert_no`, `issue_date`, `crew`, `headsails`, `flying_headsails`, `spinnakers` and `sss_base`, plus `certificate` (`primary` or `secondary`, the certificate all of those come from) and `has_secondary` (true when the list has a secondary certificate for the boat).

`rating_status` is `ok` when the list has a boat with that name and that sail number, `check` when only one of the two matched, `none` when the boat is not on the list, and `off` when that rating system is switched off.

An entry also carries the person in charge's name, e-mail address and mobile number, and the boat's own tracker number. Those are private: only show them to race management.

The ratings are the current ones before the race's rating deadline and the frozen ones afterwards. `EntriesHelper::ratingSources($race)` says which, per system (`current` or `snapshot`).

### One boat's current rating

There is no helper for a single boat outside a race, so this reads the table. It finds a boat by sail number, ignoring spaces, hyphens and capitals:

```php
use Joomla\CMS\Factory;

$db   = Factory::getContainer()->get('DatabaseDriver');
$sail = strtoupper(preg_replace('/[\s-]+/', '', $typedSailNumber));

$query = $db->getQuery(true)
    ->select($db->quoteName(['boat_name', 'sail_no', 'tcc', 'non_spi_tcc', 'cert_no', 'issue_date', 'crew']))
    ->from($db->quoteName('#__isorairc_ratings'))
    ->where($db->quoteName('scheme') . ' = ' . $db->quote('irc'))
    ->where('UPPER(REPLACE(REPLACE(' . $db->quoteName('sail_no') . ", ' ', ''), '-', '')) = " . $db->quote($sail))
    ->order($db->quoteName('secondary') . ' IS NOT NULL');   // primary certificate first

$boat = $db->setQuery($query, 0, 1)->loadObject();          // null when the boat is not on the list
```

For the rating frozen for a race, read `#__isorairc_snapshot_ratings` and add `->where($db->quoteName('race_id') . ' = ' . (int) $raceId)`.

To print a rating the way its system publishes it (three decimals for IRC, none for YTC):

```php
$system = RatingsHelper::systems()['irc'];
echo RatingsHelper::formatRating($boat->tcc, $system);      // "1.045"
```

### When the lists were last refreshed

```php
$last = RatingsHelper::lastImport('irc');   // null before the first import
echo $last ? $last->imported_at . ' UTC, ' . $last->row_count . ' boats' : 'Not imported yet';
```

### Ideas for other parts of the site

| Idea | How |
|---|---|
| Fill in or check the TCC a competitor types on the entry form | In a script on the RSForm! Pro form, look the boat up by sail number as above and compare or fill in the value |
| An entry list or "boats entered" count on the home page | A module that calls `EntriesHelper::races()` and `EntriesHelper::entries()` and prints only the public values |
| Results or a provisional class split | `EntriesHelper::entries()` for the race gives every boat's rating as frozen at the deadline |
| A crew limit check | `crew` on the boat's IRC row, or on its entry |
| A boat's rating history through the season | Read the snapshot rows for its sail number across races, or the archive for day-by-day history |

## Where this is defined in the code

| What | File |
|---|---|
| The tables | `src/com_isorairc/admin/sql/install.mysql.utf8.sql` |
| Which column of each list goes in which database column | `RatingsHelper::systems()` in `src/com_isorairc/admin/src/Helper/RatingsHelper.php` |
| Importing and freezing | The same file |
| Reading entries and matching boats to ratings | `src/com_isorairc/admin/src/Helper/EntriesHelper.php` |
