# ISORA Race Office

A Joomla extension for the Irish Sea Offshore Racing Association (ISORA) website. It handles the rating and entry paperwork that has to be ready before each race.

## What it does

- **Rating lookup.** Imports the published IRC and YTC rating lists every day and gives the site a page where anyone can look up a boat by name or sail number.
- **Ratings frozen at the rating deadline.** For each race it keeps every boat's rating as it stood at that race's rating deadline (normally 7 days before the race, at 23:59). A certificate reissued after the deadline does not change the frozen rating.
- **Race entries.** Reads the RSForm! Pro entry form and lists the boats entered for each race, with each boat's rating filled in and mismatches flagged.
- **API.** A key-protected, read-only JSON feed of the races and the boats entered, for Crewmanager (`docs/api.md`).
- **YB Tracking fleetsheet.** Produces YB's fleet information sheet for a race, filled in, including the serial number of a boat's own tracker when it gave one on the entry form.

## Install

1. Download `pkg_isorairc-<version>.zip` from the [Releases](../../releases) page.
2. In Joomla's administrator area, go to **System → Install → Extensions** and upload it.
3. Follow the [admin guide](docs/admin-guide.md) to set it up.

Uploading a newer package updates an existing install in place; races, snapshots and settings are kept.

## Requirements

- Joomla 5 (tested on 5.4.10) with MySQL or MariaDB.
- Outgoing web access from the server to the rating sources.
- RSForm! Pro, for the Race entries page and the fleetsheet.
- PHP's zip extension, for the fleetsheet.

## Documentation

- [Admin guide](docs/admin-guide.md): installing, setting up, and what to do when something goes wrong.
- [API](docs/api.md): the addresses and answers of the feed for Crewmanager.
- [Changelog](CHANGELOG.md): what changed in each version.
- [Tests](tests/README.md): what the automated tests cover and how to run them.

## What is in this repository

| Path | Contents |
|---|---|
| `src/com_isorairc` | The component: admin screens, public pages, import and snapshot logic, fleetsheet template. |
| `src/plg_task_isorairc` | The scheduled-task plugin that runs the daily import. |
| `src/pkg_isorairc` | The package that installs both together. |
| `docs` | The admin guide. |
| `tests` | The automated tests. |
| `build.py` | Builds the installable package. |

The extension's internal name is `com_isorairc`, from its first version, which only handled IRC ratings.

## Building

Needs Python 3, with no extra packages.

```
python build.py
```

This writes `dist/pkg_isorairc.zip`. The `dist` folder is not kept in the repository; each version's build is attached to its release instead.

## Testing

```
php tests/static.php
bash tests/run.sh
```

The first is a quick check of the project's files and runs anywhere. The second builds a throwaway Joomla site, installs the package in it and tests everything; it needs Linux with PHP and MySQL or MariaDB. Both run on GitHub on every push. See [tests/README.md](tests/README.md).

## Releasing a version

1. Set the new version number in the three manifests: `src/com_isorairc/isorairc.xml`, `src/plg_task_isorairc/isorairc.xml` and `src/pkg_isorairc/pkg_isorairc.xml`.
2. If the database changes, add `src/com_isorairc/admin/sql/updates/mysql/<version>.sql` with one change per statement, and make the same change in `install.mysql.utf8.sql`.
3. Add an entry to `CHANGELOG.md` and update the version line in the admin guide.
4. Run the tests (see above) and check they all pass.
5. Commit, tag the commit `v<version>`, and create a GitHub release with the package built from that commit attached as `pkg_isorairc-<version>.zip`.

## Licence

GPL-2.0-or-later, the same as Joomla.
