# Changelog

Changes to ISORA Race Office (Joomla package `pkg_isorairc`), newest first.

## 1.4.3 (22 November 2026)

- The **Download CSV** and **Download YB fleetsheet** links on the Race entries page are now solid buttons. The site template does not style standard buttons, so they were showing as plain text.

## 1.4.2 (22 November 2026)

- Renamed from "ISORA Ratings" to **ISORA Race Office** everywhere an admin sees the name: the Components menu, page headings, options, the package and the scheduled task ("ISORA Race Office: import boat ratings").
- The internal name (`com_isorairc`) is unchanged, so races, snapshots and settings carry over.

## 1.4.1 (22 November 2026)

- Default IRC class bands for the fleetsheet set to the limits in the 2026 Notice of Race: Class 0 from 1.070, Class 1 from 1.010, Class 2 below that.

## 1.4.0 (22 November 2026)

- New: **YB Tracking fleetsheet**. Race management can download YB's fleet information sheet for a race from the Race entries page, filled in from the entry form and the ratings. Serial and Tracker ID are left empty.
- New options tab "YB fleetsheet": type for every boat, IRC class bands, flag codes, and the entry form's country and photograph field names.

## 1.3.1 (22 November 2026)

- The "Entry form question" list on a race now shows only the form's race questions, not its other single-choice questions.

## 1.3.0 (22 November 2026)

- New: **Race entries** page. Lists the boats entered for each race, read from the RSForm! Pro entry form, with each boat's IRC or YTC rating. A boat that sent the form more than once counts once, by its latest form.
- Race management sees contact details, the rating typed on the form and a CSV download.
- New options tab "Entry form": the form's ID and field names.
- Each race has a new "Entry form question" setting. Database: adds `entry_field` to the races table.

## 1.2.1 (22 November 2026)

- Database update files rewritten with one change per statement so that Joomla's database check (and the pre-update check before a Joomla upgrade) passes.
- Import log index renamed.

## 1.2.0 (21 November 2026)

- The second rating system is named **YTC** throughout (it was first added under the name NHC). Existing records are converted.
- Menu item type and scheduled task renamed to cover both IRC and YTC.

## 1.1.1 (21 November 2026)

- Fixed: the YTC import failed on a very long sail number. Text columns are now 190 characters.
- An import now either completes or leaves the previous ratings untouched, and a failure while taking snapshots no longer hides the import result.

## 1.1.0 (21 November 2026)

- New: a second rating system alongside IRC, read from a Google Sheet, with the same lookup and per-race snapshots.
- Rating sources are now settings, with the current addresses as defaults. A normal Google Sheets link is accepted.
- Snapshots are tracked per race and per rating system.

## 1.0.2 (21 November 2026)

- Fixed: searching from the lookup page's menu item gave a "not found" error. The page now has a clean address.

## 1.0.1 (21 November 2026)

- The rating deadline is set as a number of days before the race (default 7) instead of a date. The time of day stays 23:59 in the site's time zone.

## 1.0.0 (21 November 2026)

- First version: daily import of the IRC rating list, a public lookup page by boat name or sail number, races with a rating deadline, and a snapshot of every boat's rating at each deadline. A certificate reissued after the deadline keeps its pre-deadline rating in the snapshot.
- Scheduled task plugin for the daily import.
