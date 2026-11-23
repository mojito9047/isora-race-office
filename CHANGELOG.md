# Changelog

Changes to ISORA Race Office (Joomla package `pkg_isorairc`), newest first.

## 1.7.1 (23 November 2026)

- Fixed: after swapping a boat between its primary and secondary certificate, the confirmation ("Mojito is now on its secondary certificate for …") did not appear on the ISORA site, because the site's template has no place for Joomla's messages on that page. The Race entries page now shows it itself, in a green box above the race list (orange when nothing was changed). It is shown once, to the person who made the swap. The swap itself always worked.

## 1.7.0 (23 November 2026)

- New: **primary or secondary IRC certificate, per race**. A boat is rated on its primary certificate unless its secondary one is chosen for that race. The choice is for one race and one boat, and it stays if the boat sends its entry form again.
- Race entries page, race management view: an IRC boat that has a secondary certificate on the list gets a **Use secondary certificate** link under its rating (**Use primary certificate** once it is on the secondary). Race management can swap at any time, also after the rating deadline; after the deadline the frozen rating of the chosen certificate is used. The page shows when the secondary was chosen and by whom.
- Everyone sees **Secondary certificate** under the rating of a boat that is on it. The rating, the IRC data pop-up, the Boat data link, the CSV download and the YB fleetsheet all follow the chosen certificate. The CSV download has a new column, **IRC certificate** (Primary or Secondary).
- API: each boat now comes with `certificate` (`primary` or `secondary`) and `secondary_available`. A new call, `api.certificate`, lets Crewmanager choose a boat's certificate for a race. It is refused once the race's rating deadline has passed. The API is therefore no longer read-only: the key now allows this one change. Details in `docs/api.md`.
- If the secondary certificate was chosen but the list no longer has one for the boat, the primary rating is used and the entry is flagged for checking.

## 1.6.0 (23 November 2026)

- New: **IRC data pop-up** on the Race entries page. Each IRC boat that is on the rating list has an **IRC data** link under its rating. It opens a small window with the boat's certificate details: issue date, crew, headsails, flying headsails, spinnakers and SSS base value. Everyone who can see the page gets it; the values are the ones RORC publish. For a race whose ratings are frozen, the values are the frozen ones.
- The CSV download has six new columns after the declared rating: **IRC issue date**, **IRC crew**, **IRC headsails**, **IRC flying headsails**, **IRC spinnakers** and **IRC SSS base value**. They are empty for YTC boats and for boats not on the IRC list.
- The import now keeps the headsail, flying headsail and spinnaker counts and the SSS base value from the IRC list (issue date and crew were kept already). **After updating, run the import once** (Components → ISORA Race Office → Import ratings now) or wait for the daily import; until then the four new values show as a dash. A race frozen before this version shows them as a dash until its snapshot is rebuilt. The frozen-ratings CSV download in the administrator area has the four new columns too.

## 1.5.0 (23 November 2026)

- New: **API for Crewmanager**. Another system can read the races and the boats entered for each one as JSON, so that crew lists and declarations can be tied to the entry list. It is read-only and needs a key, set on the new **API** tab of the options; with no key set the API is off. Each boat comes with its sail number, type, class, club, rating and its person in charge (name, e-mail, mobile). The addresses and the layout of the answers are in `docs/api.md`.
- Race entries page, race management view: a boat using its own tracker now has **Own tracker:** and the number under its entry type, the number that goes in the fleetsheet's Tracker ID column. A boat that answered Yes but gave no number is flagged "Own tracker: no number given". The public and ordinary members do not see it.

## 1.4.6 (23 November 2026)

- New: **boats using their own tracker**. The entry form now asks "Do you wish to use your own tracker" (field `OwnTracker`) and, if yes, for the tracker's serial number (field `TrackerSerialNumber`). The YB fleetsheet puts that number in the **Tracker ID** column for those boats; Serial is left empty, and boats without their own tracker still have both empty. A serial number is only used when the boat answered Yes. The two field names are new settings on the YB fleetsheet tab.
- The CSV download has a new column, **Own tracker serial**.
- The own-tracker question is not offered as a race question when linking a race, although it is answered Yes or No like one.

## 1.4.5 (22 November 2026)

- New: race management get a **Boat data** button under each IRC boat's rating on the Race entries page. It opens RORC's "Boat data for valid IRC certificates" page in a new tab, searched for that boat's certificate number, where the boat's data sheet can be downloaded. The page's address is a new setting on the Rating sources tab; emptying it hides the button.
- Fixed: on the Race entries page, a boat entered with only the digits of its sail number (for example "1234" for IRL1234) and a name that differs from the rating list was shown as not found. It is now found by its sail number and flagged for checking, like other name mismatches.
- New: an automated test set (the `tests` folder) that installs the extension in a throwaway Joomla site and checks imports, rating deadlines, entries, the fleetsheet, the public pages, the downloads and the admin screens. It runs on GitHub on every push. No change to the installed extension apart from the fix above.

## 1.4.4 (22 November 2026)

- Race entries page, race management view: the table now fits the page without scrolling sideways. The rating note and the rating typed on the form sit under the rating, phone and e-mail sit under the person in charge, and the date the form was sent sits under the entry type. The CSV download still has every value in its own column.

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
