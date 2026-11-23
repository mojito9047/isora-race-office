# ISORA Race Office: admin guide

How to install the ISORA Race Office extension on the ISORA Joomla site and set it up. Written for version 1.8.0.

For the people who run the races there is a shorter [guide for race organisers](race-office-guide.md), about using the Race entries page once everything here is set up.

## What it does

- **Rating lookup.** Downloads the published IRC and YTC rating lists once a day and gives the site a page where anyone can look up a boat by name or sail number.
- **Ratings frozen at each race's deadline.** For every race you add, it keeps a copy of all ratings as they stood at that race's rating deadline. A certificate reissued after the deadline does not change the frozen rating.
- **Race entries.** Reads the RSForm! Pro entry form and lists the boats entered for each race, with each boat's rating filled in from the lists and, for IRC boats, details from the certificate. Race management also see contact details, can download the list, and can put a boat on its secondary IRC certificate for a race.
- **YB fleetsheet.** Lets race management download the YB Tracking fleet information sheet for a race, already filled in. Boats using their own tracker have its serial number filled in; the other tracker numbers are left for you.
- **API.** Lets another system, such as Crewmanager, read the races and entries with a key, and choose a boat's IRC certificate for a race until its rating deadline.

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
| The package | `pkg_isorairc-<version>.zip` from the **Releases** page of the GitHub repository (`mojito9047/isora-race-office`), which has the build for each version. |

Take a backup of the site first, as for any extension install.

## 1. Install

1. In the administrator area go to **System → Install → Extensions**.
2. On the **Upload Package File** tab, upload the package zip.
3. Wait for "Installation of the package was successful".

This installs two things: the **ISORA Race Office** component (under the **Components** menu) and a scheduled-task plugin called **Task - ISORA Race Office**, which is switched on automatically.

To update later, upload the newer package zip the same way. Races, snapshots, certificate choices and settings are kept. Then click **Import ratings now** (step 3) once: a new version sometimes keeps more of each rating list than the one before, and the import fills that in. The changelog says when it matters.

## 2. Check the rating sources

1. Go to **Components → ISORA Race Office**, then click **Options** (top right).
2. On the **Rating sources** tab, check the two addresses. They are already filled in:

| Setting | Default |
|---|---|
| IRC ratings source | `https://www.topyacht.com.au/rorc/data/ClubListing.csv` |
| IRC boat data page | `https://ircrating.org/boat-data-for-valid-irc-certificates/` |
| YTC ratings source | The ISORA YTC Google Sheet |

- The IRC boat data page is RORC's page for looking up a boat's measurement data. It is used for the **Boat data on RORC** link that race management get in a boat's Details window on the Race entries page (step 8). Empty the setting to hide the button.
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

Deleting a race also deletes its snapshots and any certificate choices made for it (step 8).

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
- An IRC boat with two certificates is rated on its primary one, unless its secondary one has been chosen for that race (step 8).
- A rating followed by **?** was matched on boat name or sail number only. **Not listed** means no boat with that name or sail number is on the list.
- Every entered boat is listed. The form's optional publication-consent box is not taken into account.

## 8. Who sees what

The public version of the Race entries page shows boat, sail number, type, class, club and rating. Under the rating of each IRC boat that is on the rating list there is a **Details** link, which opens a small window with details from the boat's certificate: issue date, crew, headsails, flying headsails, spinnakers and SSS base value, and which certificate is in use. These are the values RORC publish in the IRC list, the current ones until the race's rating deadline and the frozen ones afterwards. A dash means the list has no value. A boat with two IRC certificates has a tag next to its rating, **2 certs: primary** or **2 certs: secondary** (the second in orange), saying which one is used for the race.

People who are allowed to manage the component also see, in the same table, a note under any rating that needs checking, the rating typed on the form when it differs from the list, a Contact column (person in charge, mobile, e-mail) and an Entry column (entry type, when the form was sent and, for a boat using its own tracker, the tracker number; "no number given" means the boat answered Yes but left the number empty). The CSV download also has the serial number of a boat's own tracker and, for IRC boats, the six certificate details from the Details window, each in its own column. For them every boat has a **Details** link, and the window also holds the rating typed on the form and the two items below. They also get two orange buttons above the table: **Download CSV** and **Download YB fleetsheet** (step 9).

- In the Details window of each IRC boat they get a **Boat data on RORC** link. It opens RORC's "Boat data for valid IRC certificates" page in a new tab, already searched for that boat's certificate number, with a Download link for the boat's data sheet. RORC only list certificates that are valid now, so for a race whose ratings are frozen, a boat whose certificate has since been reissued may give no result; search for its sail number on that page instead.
- **Primary or secondary certificate.** Some IRC boats have two certificates. A boat is rated on its primary one unless its secondary one is chosen for the race. A boat that has a secondary certificate on the list has the **2 certs** tag, and a **Use secondary certificate** button in its Details window; click it to put the boat on that certificate for the race being shown, and **Use primary certificate** to put it back. A green box at the top of the page confirms each change. The choice is for that race only. You can swap at any time, also after the rating deadline, when the frozen rating of the chosen certificate is used. The boat's crew can make the same choice in Crewmanager, but only until the rating deadline (step 10). The Details window shows when the secondary was chosen and by whom ("the API" means Crewmanager). The rating, the Details window, the Boat data link, the CSV download (column **IRC certificate**) and the YB fleetsheet all follow the choice. If a boat chose its secondary certificate and the list later has none for it, its primary rating is shown with a note to check.
- That permission is **Access Administration Interface** for ISORA Race Office, set under **Components → ISORA Race Office → Options → Permissions**. Super Users always have it.
- They must be logged in on the public site, for example through the site's "Admin Log In" page. Being logged in to the administrator area alone is not enough.

## 9. YB Tracking fleetsheet

Race management can download YB's fleet information sheet for any race from the Race entries page: choose the race, then click **Download YB fleetsheet** (next to **Download CSV**). The button only appears for people with the permission described in step 8.

The file is YB's own workbook with one row per entered boat.

| Column | Filled with |
|---|---|
| Serial | Left empty. |
| Tracker ID | The number of the boat's own tracker, for boats that answered Yes to "Do you wish to use your own tracker" on the entry form and gave a number. Empty for every other boat; add these by hand when the trackers are allocated. |
| Yacht Name, Model, Sail Number | From the entry form. |
| Handicap (TCF 1) | The boat's IRC rating: the current one before the race's rating deadline, the frozen one after, from its secondary certificate if that was chosen for the race. Empty if the boat is not on the IRC list, and for YTC boats. |
| Owner, Skipper | Both take the form's "Full Name(s)". |
| Type | The same for every boat (a setting). |
| Country/Flag | From the owner's country on the form, using the flag codes setting. |
| Image | Link to the photo uploaded with the entry, if there was one. |
| Line Honours | X for every boat. |
| IRC Overall | X for every IRC boat. |
| IRC Class columns | X in the class whose band the boat's rating falls in. |
| IRC 2-Handed | X for boats entered in the 2-handed class. |

YTC boats are listed so they can be given a tracker, but only get Line Honours.

The tracker number is copied as the competitor typed it and is not checked. If a boat answered No, or did not answer, any number left in the serial box is ignored. A boat that sends the form again replaces its earlier answer, so it can add, change or withdraw its own tracker that way.

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
| Field name: Using own tracker? | `OwnTracker` | The name of the yes/no question on the entry form. |
| Field name: Own tracker serial number | `TrackerSerialNumber` | The field's name on the entry form. Its answer goes in the Tracker ID column. |

## 10. API for Crewmanager

Another system, such as Crewmanager, can read the races and the boats entered for each one straight from the site, with a key that you set. With the same key it can make one change: choose whether a boat uses its primary or its secondary IRC certificate for a race, until that race's rating deadline. It cannot change anything else.

1. Make a long random key of at least 20 characters, for example with a password manager's generator. Use letters and digits.
2. Go to **Components → ISORA Race Office → Options → API**, put it in **API key** and click **Save & Close**.
3. Give the key and the file `docs/api.md` (addresses and the layout of the answers) to the people who run the other system. Send the key privately, not in a group e-mail.

What the other system gets for each race: its name, date and rating deadline, and for each boat entered its name, sail number, type, class, club, rating, which IRC certificate the rating is from, whether the boat has a secondary certificate, and the person in charge with their e-mail address and mobile number. It does not get the entry type, the tracker number or anything else from the form.

- Because the answers hold contact details, give the key only to a system that ISORA's privacy notice lets hold them.
- To cut a system off, change the key. To switch the API off, empty the field. A key shorter than 20 characters also leaves it off.
- Anyone who can open the options can read the key.

## Checks after setup

- [ ] **Components → ISORA Race Office** shows a recent "last imported" time for IRC and YTC.
- [ ] The scheduled task appears under **System → Manage → Scheduled Tasks** and **Run Test** succeeds.
- [ ] The rating lookup page finds a boat you know.
- [ ] The Race entries page lists the races and the boats entered.
- [ ] Logged in on the public site as a manager, the extra columns and **Download CSV** appear.
- [ ] An IRC boat on the list has a **Details** link that opens a window whose first six values are none of them a dash.
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
| The Details window shows dashes | The extension was updated and no import has run since, or the race was frozen before version 1.6.0 | Click **Import ratings now**. For a frozen race, tick it and click **Rebuild snapshot**. |
| No **2 certs** tag or **Use secondary certificate** button for a boat | The IRC list has no secondary certificate with that boat's sail number (in a frozen race: had none at the deadline), or the boat is not matched to the list at all | Look the sail number up on the rating lookup page; a secondary certificate is listed as "NAME - SEC". |
| A boat's rating has the note "Secondary certificate chosen, but the list has none for this boat" | Its secondary certificate has lapsed or been withdrawn since it was chosen | Its primary rating is being used. Click **Use primary certificate** to clear the note, or check with the boat. |
| Crewmanager reports "The rating deadline for this race has passed" | It tried to change a boat's certificate after the deadline | Change it yourself on the Race entries page (step 8) if it should be allowed. |
| Crewmanager reports "The API is switched off" or "A valid API key is required" | No key is set, it is shorter than 20 characters, or Crewmanager has a different one | Step 10. |

## Removing the extension

**System → Manage → Extensions**, find the **ISORA Race Office** package and uninstall it. This deletes the races, snapshots, certificate choices and archived rating downloads. The entry form and its submissions are not touched.

## Next season

1. Put the new entry form's ID in **Options → Entry form** and check the field names, including the two on the **YB fleetsheet** tab.
2. Add the new season's races and link each to its question.
3. Check the IRC class bands on the **YB fleetsheet** tab against the new Notice of Race.
4. Old races, their snapshots and their certificate choices can stay; they remain available in the rating lookup's "Ratings as at" list.
