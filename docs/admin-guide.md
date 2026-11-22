# ISORA Race Office: admin guide

How to install the ISORA Race Office extension on the ISORA Joomla site and set it up. Written for version 1.4.3.

## What it does

- **Rating lookup.** Downloads the published IRC and YTC rating lists once a day and gives the site a page where anyone can look up a boat by name or sail number.
- **Ratings frozen at each race's deadline.** For every race you add, it keeps a copy of all ratings as they stood at that race's rating deadline. A certificate reissued after the deadline does not change the frozen rating.
- **Race entries.** Reads the RSForm! Pro entry form and lists the boats entered for each race, with each boat's rating filled in from the lists.
- **YB fleetsheet.** Lets race management download the YB Tracking fleet information sheet for a race, already filled in apart from the tracker numbers.

The extension never changes the entry form or its submissions. It only reads them.

## Before you start

| Need | Detail |
|---|---|
| Joomla | Version 5. Tested on 5.4.10 (and on 4.3.4 and 4.4.14 before that). |
| Database | MySQL or MariaDB. |
| Login | A Super User account for the administrator area. |
| Outgoing web access | The web server must be able to reach `www.topyacht.com.au` and `docs.google.com`. |
| RSForm! Pro | Only needed for the Race entries page and the YB fleetsheet. Already installed on the ISORA site. |
| PHP zip extension | Only needed for the YB fleetsheet. Present on most hosts. |
| The package | `pkg_isorairc.zip`. It is not stored in the GitHub repository; get it from whoever maintains the extension, or build it from the source with `python build.py`, which writes it to the `dist` folder. |

Take a backup of the site first, as for any extension install.

## 1. Install

1. In the administrator area go to **System → Install → Extensions**.
2. On the **Upload Package File** tab, upload `pkg_isorairc.zip`.
3. Wait for "Installation of the package was successful".

This installs two things: the **ISORA Race Office** component (under the **Components** menu) and a scheduled-task plugin called **Task - ISORA Race Office**, which is switched on automatically.

To update later, upload the newer `pkg_isorairc.zip` the same way. Races, snapshots and settings are kept.

## 2. Check the rating sources

1. Go to **Components → ISORA Race Office**, then click **Options** (top right).
2. On the **Rating sources** tab, check the two addresses. They are already filled in:

| Setting | Default |
|---|---|
| IRC ratings source | `https://www.topyacht.com.au/rorc/data/ClubListing.csv` |
| YTC ratings source | The ISORA YTC Google Sheet |

- Either system can be switched off with its **Import … ratings** switch.
- The YTC source can be an ordinary Google Sheets link, as long as the sheet is shared so that anyone with the link can view it. It can also be the address of a CSV file.
- The IRC file must have the columns `Boat Name`, `Sail No` and `TCC`. The YTC sheet must have `Boat Name`, `Sail Number` and `YTC`.

3. On the **Rating deadline** tab, set the defaults:

| Setting | Default | Meaning |
|---|---|---|
| Default rating deadline (days before race) | 7 | Used for new races. Each race can have its own number. |
| Deadline time of day | 23:59 | Time on the deadline day, in the site's time zone. |

4. Click **Save & Close**.

## 3. Do the first import

1. On **Components → ISORA Race Office**, click **Import ratings now**.
2. The message should read something like "IRC: 3817 boats imported; YTC: 2105 boats imported".
3. The blue box at the top of the page now shows when each list was last imported.

If one list fails, the message says which one and why; the other is still imported.

## 4. Set up the daily import

The daily import is a Joomla scheduled task. Without it the lists go stale and no snapshots are taken.

1. Go to **System → Manage → Scheduled Tasks** and click **New**.
2. Choose **ISORA Race Office: import boat ratings**.
3. Give it a title, for example "ISORA ratings import (IRC and YTC)".
4. Set it to run once a day and save.

Two things to know about timing:

- **"Every 1 day" counts from the last run**, not from a fixed time of day. If the import must happen at a set time, choose the cron-expression type of schedule.
- **Joomla only runs tasks when someone visits the site**, unless a real cron job has been set up on the server (Scheduled Tasks → Options explains both). On a busy site this makes no difference.

The snapshot rule allows for a late import: the snapshot is built from the first download after the deadline, and any boat whose certificate is dated after the deadline is put back to its pre-deadline rating.

To test the task, tick it in the list and use **Run Test**.

## 5. Add the races

For each race:

1. On **Components → ISORA Race Office**, click **New**.
2. Fill in:

| Field | What to enter |
|---|---|
| Race name | As it should appear on the site. |
| Race date | The day of the race. |
| Rating deadline (days before race) | Usually 7. |
| Entry form question | The question on the entry form that asks about this race. See step 7; leave empty until the entry form is linked. |

3. Click **Save & Close**.

The list shows each race's deadline and, per rating system, whether its snapshot is pending, due or taken. Once a snapshot is taken there is a **Download CSV** link for it.

If you change a race's date or number of days after its snapshot was taken, tick the race and click **Rebuild snapshot**. This works from the archived downloads, so it gives the same result as if the race had been set up correctly from the start.

Deleting a race also deletes its snapshots.

## 6. Add the rating lookup page

1. Go to **Menus** and open the menu the page should sit in.
2. Click **New**, then **Select** next to Menu Item Type.
3. Choose **ISORA Race Office → Rating lookup (IRC and YTC)**.
4. Give it a title, for example "Boat Ratings", and save.

Visitors can search current ratings or, from the "Ratings as at" list, the ratings frozen for a past race.

## 7. Link the entry form (Race entries page)

### Tell the extension which form to read

1. Find the entry form's ID: **Components → RSForm! Pro → Manage Forms**, in the ID column.
2. Go to **Components → ISORA Race Office → Options → Entry form**.
3. Put that number in **Entry form ID**.
4. Check the field names. They are filled in for the 2027 entry form:

| Setting | 2027 form field name |
|---|---|
| Answer that means entered | `Yes` |
| Boat name | `Boat_Name` |
| Sail number | `Sail_Number` |
| Boat type | `Manufacturer_Model` |
| Class | `Class_Racing` |
| Club | `Sailing_Club` |
| Declared rating | `Latest_IRC_TCC` |
| Person in charge | `FullName` |
| E-mail | `Email` |
| Mobile number | `Mobile_Number_for_Main_Contact` |
| Entry type | `Race_Entry_Categoy` |

These are the fields' names in RSForm, not their captions. If a field is renamed on the form, change it here too.

5. Click **Save & Close**.

### Link each race to its question

1. Open each race (**Components → ISORA Race Office**, click the race name).
2. In **Entry form question**, pick the question for that race. The list shows only the form's yes/no race questions.
3. Save.

A race with no question chosen does not appear on the Race entries page.

### Add the page to a menu

Same as step 6, but choose **ISORA Race Office → Race entries**.

### How the page behaves

- A boat that sent the form more than once is counted once. Its most recent form decides which races it is in.
- The class chosen on the form decides the rating shown: classes starting "YTC" use the YTC list, everything else uses IRC, and non-spinnaker classes use the non-spinnaker rating.
- Until a race's rating deadline the current rating is shown; afterwards the frozen one.
- A rating followed by **?** was matched on boat name or sail number only. **Not listed** means no boat with that name or sail number is on the list.
- Every entered boat is listed. The form's optional publication-consent box is not taken into account.

## 8. Who sees what

The public version of the Race entries page shows boat, sail number, type, class, club and rating.

People who are allowed to manage the component also see the rating note, the rating typed on the form, person in charge, mobile, e-mail, entry type and when the form was sent, and get two orange buttons above the table: **Download CSV** and **Download YB fleetsheet** (step 9).

- That permission is **Access Administration Interface** for ISORA Race Office, set under **Components → ISORA Race Office → Options → Permissions**. Super Users always have it.
- They must be logged in on the public site, for example through the site's "Admin Log In" page. Being logged in to the administrator area alone is not enough.

## 9. YB Tracking fleetsheet

Race management can download YB's fleet information sheet for any race from the Race entries page: choose the race, then click **Download YB fleetsheet** (next to **Download CSV**). The button only appears for people with the permission described in step 8.

The file is YB's own workbook with one row per entered boat.

| Column | Filled with |
|---|---|
| Serial, Tracker ID | Left empty. Add these by hand when the trackers are allocated. |
| Yacht Name, Model, Sail Number | From the entry form. |
| Handicap (TCF 1) | The boat's IRC rating: the current one before the race's rating deadline, the frozen one after. Empty if the boat is not on the IRC list, and for YTC boats. |
| Owner, Skipper | Both take the form's "Full Name(s)". |
| Type | The same for every boat (a setting). |
| Country/Flag | From the owner's country on the form, using the flag codes setting. |
| Image | Link to the photo uploaded with the entry, if there was one. |
| Line Honours | X for every boat. |
| IRC Overall | X for every IRC boat. |
| IRC Class columns | X in the class whose band the boat's rating falls in. |
| IRC 2-Handed | X for boats entered in the 2-handed class. |

YTC boats are listed so they can be given a tracker, but only get Line Honours.

Download the sheet after the rating deadline if you want the frozen ratings in it. Check any boat with an empty handicap before sending the sheet to YB; the Race entries page says why it has no rating.

### Fleetsheet settings

**Components → ISORA Race Office → Options → YB fleetsheet**:

| Setting | Default | Notes |
|---|---|---|
| Type for every boat | `MEDIUM MONOHULL` | Must be one of the types on the sheet's Lookups tab. |
| IRC class bands | `IRC Class 0=1.070`, `IRC Class 1=1.010`, `IRC Class 2=0` | One line per class: column heading, equals sign, lowest TCC in the class. The defaults are the limits in the 2026 Notice of Race (section 7.1); change them if a later Notice of Race does. Adding or removing a line adds or removes a column on the sheet. |
| Flag codes | `Ireland=IE`, `Northern Ireland=GB`, `England=GB`, `Wales=XW` | One line per country, spelled as on the entry form. Countries not listed are left blank on the sheet. |
| Field name: Owner's country | `Country` | The field's name on the entry form. |
| Field name: Yacht photograph | `Upload_Yacht_Photograph_` | The field's name on the entry form. |

## Checks after setup

- [ ] **Components → ISORA Race Office** shows a recent "last imported" time for IRC and YTC.
- [ ] The scheduled task appears under **System → Manage → Scheduled Tasks** and **Run Test** succeeds.
- [ ] The rating lookup page finds a boat you know.
- [ ] The Race entries page lists the races and the boats entered.
- [ ] Logged in on the public site as a manager, the extra columns and **Download CSV** appear.
- [ ] **Download YB fleetsheet** gives a workbook that opens in Excel and lists the race's boats.
- [ ] **System → Maintenance → Database** shows no problems for ISORA Race Office.

## If something goes wrong

| What you see | Likely cause | What to do |
|---|---|---|
| "Import failed" or one list reports an error | The web server cannot reach the source, or the file's columns changed | Open the source address in a browser. Check the column names listed in step 2. |
| YTC import fails, IRC works | The Google Sheet is no longer shared by link | Set the sheet's sharing to "Anyone with the link – Viewer". |
| A list imports far fewer boats than usual | The source was incomplete | Nothing: a download with fewer than 100 boats is rejected and the previous ratings are kept. |
| A snapshot stays "Due" | No import has run since the deadline | Click **Import ratings now**, and check the scheduled task is running. |
| Race entries page says the entry form has not been chosen | Entry form ID is empty | Step 7. |
| Race entries page says no races are linked | No race has an entry form question | Step 7, "Link each race to its question". |
| The question list on a race is empty | Wrong Entry form ID, or the form's race questions do not offer the answer set in "Answer that means entered" | Check both in Options → Entry form. |
| A boat shows "Not listed" | The name and sail number on the form match nothing on the list | Compare the form with the rating lookup page; the entrant may have mistyped the sail number or not yet have a certificate. |
| A boat's Country/Flag is empty on the fleetsheet | Its country is not in the flag codes setting | Add a line for it in Options → YB fleetsheet. |
| A boat is in no IRC class column | It has no rating, or no band starts low enough | Check the boat's rating; make the lowest band start at 0. |
| No manager columns | Not logged in on the public site, or no permission | Step 8. |

## Removing the extension

**System → Manage → Extensions**, find the **ISORA Race Office** package and uninstall it. This deletes the races, snapshots and archived rating downloads. The entry form and its submissions are not touched.

## Next season

1. Put the new entry form's ID in **Options → Entry form** and check the field names, including the two on the **YB fleetsheet** tab.
2. Add the new season's races and link each to its question.
3. Check the IRC class bands on the **YB fleetsheet** tab against the new Notice of Race.
4. Old races and their snapshots can stay; they remain available in the rating lookup's "Ratings as at" list.
