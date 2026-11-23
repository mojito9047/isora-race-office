# Tests

Automated tests for ISORA Race Office. They check the extension the way it is really used: installed in a Joomla site, with rating lists downloaded over the web, an entry form with entries, and pages fetched by a browser as the public, a member, race management and an administrator.

## What is covered

| Area | Test file | Examples of what is checked |
|---|---|---|
| Project files | `static.php` | No syntax errors; the three version numbers agree; the changelog, admin guide, guide for race organisers and data description are marked for this version; every file is in the package. |
| Install, update, uninstall | `cases/InstallTest.php`, `removed/RemovedTest.php` | Fresh install; update over an existing install; Joomla's database check is clean; uninstall leaves nothing behind and does not touch the entry form's tables. |
| Reading a rating list | `cases/RatingsParseTest.php` | Columns in any order; both date formats; accented names; rows without a rating skipped; a changed layout is reported by name. |
| Importing | `cases/ImportTest.php` | Both lists import; a short list, a server error or a changed layout is refused and the old ratings are kept; every download is archived. |
| Rating deadline | `cases/SnapshotTest.php` | Nothing is frozen before the deadline; a certificate reissued after the deadline keeps its old rating; a boat first rated after it is left out; summer time; rebuild from the archive. |
| Entry form | `cases/EntriesTest.php` | Only boats that answered yes; a boat's latest form wins; later forms can add or withdraw a boat; only race questions are offered; a boat's own tracker serial is read only when it answered yes; IRC certificate details; the primary or secondary certificate chosen per race. |
| Matching ratings to entries | `cases/RatingMatchTest.php` | Name and sail number; sail number typed without its letters; doubtful matches flagged; non-spinnaker and YTC classes; frozen ratings used once frozen. |
| YB fleetsheet | `cases/FleetsheetTest.php` | Class band limits; flag codes; two-handed group; YTC boats; the workbook itself, cell by cell; a boat's own tracker number in the Tracker ID column, otherwise Serial and Tracker ID empty. |
| Rating lookup page | `cases/LookupPageTest.php` | Search by name and sail number; frozen ratings of a race; odd characters shown safely. |
| Race entries page | `cases/EntriesPageTest.php` | The public and ordinary members never see contact details; race management does; swapping a boat between its primary and secondary certificate; the tag on boats with two certificates; the Details pop-up and what is in it for whom; CSV and fleetsheet downloads, and that nobody else can get them. |
| API | `cases/ApiTest.php` | Races and boats as JSON; nothing without the right key; the key is not accepted in the address; off until a key is set; choosing a boat's certificate, refused after the rating deadline. |
| Admin screens | `cases/AdminTest.php` | Adding and editing races; the deadline worked out in Irish time, summer and winter; Import, Rebuild and Delete buttons; the options screen. |
| Scheduled task | `cases/ScheduledTaskTest.php` | The daily import run through Joomla's scheduler, including a failed download. |

## How it works

`tests/run.sh` builds a throwaway Joomla site for each run:

1. Downloads Joomla (kept for next time) and unpacks it into a temporary folder.
2. Makes a new, empty database whose name starts with `isoratest_`.
3. Installs Joomla, then installs the extension package, then installs it a second time (the update path).
4. Starts PHP's built-in web server for the site, and a second one that stands in for the IRC and YTC rating lists, so the tests never depend on (or bother) the real ones.
5. Runs the tests.
6. Uninstalls the extension and checks nothing is left.
7. Removes the site and the database.

RSForm! Pro is a paid extension, so it is not installed on the throwaway site. The tests make the five RSForm tables the extension reads, laid out as on the real site, and fill them with made-up entries.

The tests empty the extension's tables as they go. As a safeguard, `run.php` refuses to run on any database whose name does not start with `isoratest_`, so it cannot be pointed at a real site by mistake.

## Running the tests

**Quick checks**, on any computer with PHP (takes a few seconds):

```
php tests/static.php
```

On a PHP without the zip extension (the usual Windows install) two of these checks are skipped; they still run in the full set.

**Everything**, on a Linux machine with PHP 8.1 or later, MySQL or MariaDB, `curl` and `unzip` (takes about a minute):

```
python build.py
bash tests/run.sh
```

To run only some tests, give part of a test's name: `bash tests/run.sh Fleetsheet`. Settings such as the Joomla version are listed at the top of `run.sh`.

**From a Windows computer**, send the work to a Linux machine over SSH:

```
ISORA_TEST_SSH="-i ~/.ssh/mykey root@test-server" bash tests/remote.sh
```

**On GitHub**, the workflow in `.github/workflows/tests.yml` runs everything on every push, on two PHP versions. A red cross next to a commit means a test failed; the run's log shows which.

## Reading the result

Each test prints one line, `ok` or `FAIL`. A failure shows what was expected, what was found and the line of the test:

```
SnapshotTest
  ok    nothing is frozen before the deadline
  FAIL  certificate reissued after the deadline keeps its old rating
        Frozen rating: expected 1.0, got "0.990"
        at SnapshotTest.php:92
```

The last line is `ALL TESTS PASSED` or `SOME TESTS FAILED`.

## Adding a test

A test is a public method whose name starts with `test`, in a class in `tests/cases`. Its name is its description: `testLaterFormWithdrawsABoat` is printed as "later form withdraws a boat". Each test starts from an empty extension with default settings. `lib/Env.php` has helpers to set the scene:

```php
Env::standardForm();                                             // an entry form with two race questions
$race = Env::addRace('Race 1', '2027-05-01', '2027-04-24 22:59:00', 'Race_1');
Env::addRating('irc', 'Sea Breeze', 'IRL1234', 1.045);           // a boat on the IRC list
Env::submit(Env::boat(['Boat_Name' => 'Sea Breeze']));           // a boat sends the entry form
Env::setParams(['fleet_type' => 'LARGE MONOHULL']);              // change an option
Env::feed('irc', Env::ircCsv([...]), 500);                       // make the IRC list fail
```

When a bug is found, add a test that fails because of it before fixing it, so it cannot come back unnoticed.

## What is not covered

- **How pages look.** The tests read the pages' content, not their appearance, so a layout problem (for example on a phone) still needs a person to look.
- **RSForm! Pro itself.** The tests imitate its tables. A future RSForm version that stores entries differently would not be noticed here.
- **The real rating lists.** A change in the published lists' layout is caught on the live site by the import (it refuses the list and reports the missing column), not by these tests.
- **YB Tracking's side.** Whether YB accept the sheet can only be confirmed by them.
