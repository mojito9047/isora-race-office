# ISORA Race Office: guide for race organisers

How to use the Race entries page on the ISORA website in the run-up to a race: checking the entries and their ratings, changing a boat's certificate, and downloading the entry list and the YB fleetsheet. Written for version 1.9.0.

Setting the extension up (races, the entry form, permissions) is in the [admin guide](admin-guide.md).

## Signing in

The public sees the entry list without contact details. To see the race management view:

1. Sign in on the public website, through its **Admin Log In** page. Being signed in to Joomla's administrator area is not enough.
2. Open **Notice Board → Race Entries**.

You are in the race management view when there are two orange buttons above the table, **Download CSV** and **Download YB fleetsheet**, and the table has **Contact** and **Entry** columns. If they are missing, your account does not have the permission; ask the site administrator (admin guide, step 8).

## Choosing a race

The page opens on the next race to be sailed. Pick another from the **Race** list at the top. The line under the race name gives the number of boats entered per class, the race date and the rating deadline.

## Reading the entry list

Each entered boat has one row. A boat that sent the entry form more than once is listed once, from its latest form. A boat withdraws by sending the form again with No for the race.

| Column | What it shows |
|---|---|
| Boat, Sail no, Type, Class, Club | As the competitor typed them on the entry form. |
| Rating | The boat's rating from the IRC or YTC list, with a tag and notes where needed, and a **Details** link (all below). |
| Contact | The person in charge, mobile number and e-mail address. Only race management see this. |
| Entry | The entry type, when the form was sent, and the boat's own tracker number if it is using one. Only race management see this. |

The class decides which rating is shown: a class starting "YTC" uses the YTC list, every other class uses IRC, and a non-spinnaker class uses the boat's non-spinnaker rating.

### In the Rating column

| You see | Meaning | What to do |
|---|---|---|
| A rating and only **Details** under it | The list has a boat with this name and this sail number. | Nothing. |
| A rating followed by **?** | Only the name or only the sail number matched. The note says which, and what the list has. | Check it is the right boat. Usually the form has a typing mistake; ask the boat to send the form again, corrected. |
| "Listed with sail number …" | The form has only the digits of the sail number (1234 for IRL1234). The boat was found. | Nothing. |
| **Not listed**, "Not found in the IRC list" | No boat with that name or that sail number is on the list. | Look the boat up on the Rating Lookup page. It may have no valid certificate yet, or the form may be wrong. |
| "Listed as … but with no non-spinnaker rating" | The boat entered a non-spinnaker class but its certificate has no non-spinnaker rating. | Check the class with the boat. |
| A grey tag, **2 certs: primary** | The boat has two IRC certificates and is on its primary one for this race (see below). | Nothing. |
| An orange tag, **2 certs: secondary** | The boat has two IRC certificates and is on its secondary one for this race. | Nothing. |
| "Secondary certificate chosen, but the list has none for this boat" | The secondary was chosen, but the list no longer has one. The primary rating is shown. | Check with the boat, or click **Use primary certificate** to clear the note. |
| "On form: …" | The rating the competitor typed on the entry form differs from the one on the list. It is for comparison only and is never used. When the two agree, this line is left out. | Usually nothing: the certificate has been reissued since the form was sent. (If the form fills the rating in from the list, this is the rating when the boat entered.) Ask the boat if the difference is large. |

Next to the orange buttons the page says how many entries need their rating checked.

### The Details window

**Details** under a rating opens a small window for that boat. Close it with **Close**, the Esc key, or a click outside it.

| In the window | Who sees it |
|---|---|
| The certificate's issue date, crew number, headsails, flying headsails, spinnakers and SSS base value. A dash means the list has no value. These are the frozen values once the race is frozen. | Everyone |
| **Certificate in use**: Primary or Secondary, with "(of 2)" when the boat has both | Everyone |
| **Secondary chosen**: when, and by whom. "the API" means it came from Crewmanager. | Race management |
| **Rating on entry form**: what the competitor typed | Race management |
| The **Use secondary certificate** or **Use primary certificate** button, for a boat with two certificates | Race management |
| **Boat data on RORC ↗** | Race management |

**Boat data on RORC** opens RORC's "Boat data for valid IRC certificates" page in a new tab, searched for the boat's certificate number, where the data sheet can be downloaded. RORC only list certificates valid today, so a boat whose certificate has been reissued since the deadline may give no result; search for its sail number there instead.

The public sees the Details link only for IRC boats that are on the list.

### Under the entry type

| You see | Meaning |
|---|---|
| "Own tracker: 0412345" | The boat is using its own tracker; this is its number, as typed. |
| "Own tracker: no number given" | The boat answered Yes but left the number empty. Ask for it. |
| Nothing | The boat needs one of ISORA's trackers. |

## The rating deadline

Ratings are frozen at each race's rating deadline, normally seven days before the race at 23:59.

- **Before the deadline** the page shows today's ratings, and they can still change.
- **After the deadline** the page shows the ratings as they stood at the deadline. A certificate reissued later does not change them.
- The line above the table says which you are looking at: "current list" or "as frozen at the rating deadline".
- The freeze happens at the first daily import after the deadline, so the page can say "current list" for up to a day after the deadline has passed. The frozen ratings are still the ones from the deadline.
- A boat that got its first certificate after the deadline is **Not listed** for that race.

Anyone can see the frozen ratings of a past race on the Rating Lookup page, under **Ratings as at**.

## Primary and secondary certificates

Some IRC boats hold two certificates. A boat is rated on its primary certificate unless its secondary one is chosen for the race.

- A boat's crew chooses in Crewmanager, and can change their mind until the rating deadline.
- You can change it at any time, also after the deadline.

To change it:

1. Choose the race.
2. Find the boat. A boat that has a choice has a **2 certs** tag next to its rating.
3. Click **Details** under its rating, then the orange **Use secondary certificate** button. The page reloads with a green confirmation at the top, the secondary rating, and the tag now orange and reading **2 certs: secondary**.
4. To go back, open **Details** again and click **Use primary certificate**.

Things to know:

- The choice is for the race being shown only. For several races, change it in each.
- The tag and the button only appear for a boat that has a secondary certificate on the list.
- The Details window shows when the secondary was chosen and by whom. "the API" means it came from Crewmanager.
- After the deadline the frozen rating of the chosen certificate is used.
- The rating, the Details window, the CSV and the YB fleetsheet all follow the choice. If you change a boat after downloading the fleetsheet, download it again.

## Downloading the entry list (CSV)

Click **Download CSV**. The file opens in Excel and has one row per boat, with every value in its own column:

Boat, Sail No, Type, Class, Club, Rating, Rating note, Declared rating, IRC issue date, IRC crew, IRC headsails, IRC flying headsails, IRC spinnakers, IRC SSS base value, IRC certificate (Primary or Secondary), Person in charge, Mobile, E-mail, Entry type, Own tracker serial, Form sent (UTC).

The file holds names, phone numbers and e-mail addresses. Keep it to race management and delete copies you no longer need.

## Downloading the YB fleetsheet

Click **Download YB fleetsheet**. You get YB Tracking's own workbook with one row per boat, named after the race.

Filled in for you: yacht name, model, sail number, IRC rating, owner and skipper, type, flag, photo link, and an X in Line Honours, IRC Overall, the boat's IRC class and IRC 2-Handed where they apply. A boat using its own tracker has its number in **Tracker ID**.

Before sending it to YB:

1. Download it after the rating deadline, so it has the frozen ratings.
2. Fill in **Tracker ID** for the boats that are getting one of ISORA's trackers.
3. Look for an empty **Handicap**. A YTC boat has none on purpose. An IRC boat without one is not on the list; the entry list says why.
4. Look for an empty **Country/Flag** and fill it in. (Ask the site administrator to add that country to the settings for next time.)
5. Look for an IRC boat with no X in any IRC class column; it has no rating.

The sheet does not contain e-mail addresses or phone numbers.

## The week before a race

- [ ] **Before the rating deadline:** open the race and deal with every entry that has a **?**, **Not listed** or a note. Boats have until the deadline to get a certificate or correct their form.
- [ ] **Before the rating deadline:** remind boats with two certificates that Crewmanager closes the choice at the deadline.
- [ ] **The day after the deadline:** check the line above the table says "as frozen at the rating deadline".
- [ ] **After the deadline:** make any certificate changes that have been agreed.
- [ ] **After the deadline:** download the YB fleetsheet, fill in the tracker numbers and send it to YB.
- [ ] **After the deadline:** download the CSV if you need the list for scoring or the crew limit check.
- [ ] **A late entry:** it appears on the page as soon as the form is sent. Download the fleetsheet again if it has already gone to YB.

## If something looks wrong

| What you see | What to do |
|---|---|
| No orange buttons and no Contact column | Sign in on the public site (not only the administrator area). If you are signed in, ask for the permission. |
| A boat that entered is missing | Check it answered Yes for this race; its latest form decides. Check you have the right race selected. |
| A boat is listed twice | It sent two forms with a different name or sail number. Ask it to send one corrected form; the entry under the old spelling stays until the site administrator removes that submission in RSForm! Pro. |
| The page still says "current list" two days after the deadline | The daily import has not run. Tell the site administrator. |
| No **2 certs** tag or **Use secondary certificate** button for a boat you know has two certificates | Look up its sail number on the Rating Lookup page. The secondary is listed as "NAME - SEC" with the same sail number; if it is not there, the list does not have it. |
| The Details window is all dashes except the date and crew | The site was updated and the ratings have not been imported since. Tell the site administrator. |
