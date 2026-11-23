# ISORA Race Office: API

A feed of ISORA's races and the boats entered for each one, for another system such as Crewmanager. Answers are JSON. Everything is read-only except one call: choosing which IRC certificate a boat uses for a race.

## What you can do with it

| You want to | Use | Section |
|---|---|---|
| List ISORA's races, with their dates and rating deadlines | `api.races` (GET) | [The races](#the-races) |
| List the boats entered for one race | `api.entries` (GET) | [One race and its boats](#one-race-and-its-boats) |
| Get every race with its boats in one request | `api.races` with `entries=1` (GET) | [The races](#the-races) |
| See a boat's rating for a race, and whether the match to the rating list is certain | `rating` and `rating_confirmed` in the entries | [One race and its boats](#one-race-and-its-boats) |
| See which IRC certificate a boat is on, and whether it has a secondary one to choose | `certificate` and `secondary_available` in the entries | [One race and its boats](#one-race-and-its-boats) |
| Put a boat on its secondary IRC certificate for a race, or back on its primary | `api.certificate` (POST) | [Choosing a boat's certificate](#choosing-a-boats-certificate) |
| Contact a boat's person in charge | `person_in_charge` in the entries | [One race and its boats](#one-race-and-its-boats) |

What it cannot do:

- **Enter or withdraw a boat.** Entries are made on ISORA's entry form only.
- **Change anything except the certificate choice**, and that only until the race's rating deadline. After the deadline ISORA's race management can still change it, so read it back.
- **Give a boat's crew list, tracker number or entry type.** Those are not in the feed.
- **Give ratings of boats that are not entered**, or past ratings day by day.
- **Tell you when something changes.** There are no notifications; fetch again (once an hour is plenty).

How it works, in short: every request carries a secret key in a header, answers are JSON, times are in UTC, and a boat is identified by its name and sail number because entries have no ID.

## Key

Every request must carry the API key. ISORA sets the key in the website's administrator area (**Components → ISORA Race Office → Options → API**) and passes it to you privately. Send it in a header, either of:

```
X-Api-Key: YOUR-KEY
Authorization: Bearer YOUR-KEY
```

The key is not accepted in the address, because addresses are kept in server logs. Keep the key on your server; do not put it in a web page or a phone app, where anyone could read it.

The answers include people's names, e-mail addresses and mobile numbers. Hold them as ISORA's privacy notice allows.

## Addresses

Replace `https://www.isora.org` with the site's address if it differs.

| What | Address |
|---|---|
| The races | `https://www.isora.org/index.php?option=com_isorairc&task=api.races&format=json` |
| The races, each with its boats | `https://www.isora.org/index.php?option=com_isorairc&task=api.races&entries=1&format=json` |
| One race and its boats | `https://www.isora.org/index.php?option=com_isorairc&task=api.entries&race=7&format=json` (`7` is the race's `id`) |
| Choose a boat's certificate (POST) | `https://www.isora.org/index.php?option=com_isorairc&task=api.certificate&format=json` |

Use GET for the first three. Example:

```
curl -H "X-Api-Key: YOUR-KEY" "https://www.isora.org/index.php?option=com_isorairc&task=api.races&format=json"
```

## The races

```json
{
  "races": [
    {
      "id": 7,
      "name": "07O2- Offshore Race 2",
      "date": "2027-05-29",
      "rating_deadline": "2027-05-22T22:59:00Z"
    }
  ]
}
```

| Field | Meaning |
|---|---|
| `id` | The race's number on the ISORA site. It does not change when the race is renamed. |
| `name` | The race's name as ISORA typed it. |
| `date` | The day of the race, `YYYY-MM-DD`. There is no start time. |
| `rating_deadline` | The moment after which rating changes no longer count for this race, in UTC. |

Races are in date order. Only races that ISORA have linked to the entry form are listed. With `entries=1` each race also has an `entries` list, laid out as below.

## One race and its boats

```json
{
  "race": { "id": 7, "name": "07O2- Offshore Race 2", "date": "2027-05-29", "rating_deadline": "2027-05-22T22:59:00Z" },
  "entries": [
    {
      "boat": "Sea Breeze",
      "sail_number": "IRL 1234",
      "type": "J/109",
      "class": "IRC",
      "club": "Test Yacht Club",
      "rating_system": "IRC",
      "rating": 1.045,
      "rating_confirmed": true,
      "certificate": "primary",
      "secondary_available": false,
      "person_in_charge": {
        "name": "Pat Skipper",
        "email": "pat.skipper@example.invalid",
        "phone": "+353 85 555 0100"
      },
      "entered_at": "2026-10-01T10:00:00Z"
    }
  ]
}
```

| Field | Meaning |
|---|---|
| `boat`, `sail_number`, `type`, `class`, `club` | As the competitor typed them on the entry form. They are not tidied: the same boat may be "IRL 1234" on one form and "IRL1234" on another. |
| `rating_system` | `IRC` or `YTC`, from the class the boat entered in. |
| `rating` | The boat's rating as a number (an IRC TCC such as `1.045`, or a YTC number), or `null` if the boat is not on the rating list. Before the race's rating deadline this is the current rating; afterwards it is the rating frozen at the deadline. |
| `rating_confirmed` | `true` when the rating list has a boat with this name and this sail number. `false` when only one of the two matched, or there is no rating. |
| `certificate` | `primary` or `secondary`: the IRC certificate the rating is taken from. `null` for a boat that is not on the IRC list (YTC boats included). |
| `secondary_available` | `true` when the IRC list has a secondary certificate for this boat, so it can be chosen. |
| `person_in_charge` | The name, e-mail address and mobile number given on the entry form. |
| `entered_at` | When the boat's latest entry form was sent, in UTC. |

Boats are in name order. A boat appears once per race, taken from the latest entry form it sent. There is no boat number: match boats on sail number and name, or on the person in charge's e-mail address.

Entries change until the race: boats can enter or withdraw by sending the form again, and ratings change until the rating deadline. Fetch again rather than keeping an old copy; once an hour is plenty.

## Choosing a boat's certificate

Some IRC boats have two certificates. A boat is rated on its primary certificate unless its secondary one is chosen for a race. The choice is made per race and can be changed as often as wanted until that race's `rating_deadline`. After the deadline only ISORA's race management can change it.

Send a POST with the key in a header, and these four values either as form fields or as a JSON body:

| Value | Meaning |
|---|---|
| `race` | The race's `id`. |
| `boat` | The boat's name, as in the `boat` field of the entries. |
| `sail_number` | The boat's sail number, as in the `sail_number` field. |
| `certificate` | `primary` or `secondary`. |

Capitals, spaces and punctuation in the boat's name and sail number do not matter.

```
curl -X POST -H "X-Api-Key: YOUR-KEY" -H "Content-Type: application/json" -d '{"race": 7, "boat": "Sea Breeze", "sail_number": "IRL 1234", "certificate": "secondary"}' "https://www.isora.org/index.php?option=com_isorairc&task=api.certificate&format=json"
```

The answer is the race and the boat's entry as it now stands, laid out as above, so `rating` is already the chosen certificate's rating:

```json
{
  "race": { "id": 7, "name": "07O2- Offshore Race 2", "date": "2027-05-29", "rating_deadline": "2027-05-22T22:59:00Z" },
  "entry": { "boat": "Sea Breeze", "sail_number": "IRL 1234", "rating": 1.021, "certificate": "secondary", "secondary_available": true }
}
```

(The entry has all its fields; only some are shown here.)

- Only offer the choice for a boat whose `secondary_available` is `true`. Asking for `secondary` otherwise is refused.
- Choosing `primary` for a boat that is already on it is accepted and changes nothing.
- A choice applies to one race. To use the secondary certificate for several races, send one request per race.
- Race management can also change the choice on the ISORA site, so read `certificate` from the entries; do not rely on what you last sent.

## When something is wrong

| Status | Answer | Meaning |
|---|---|---|
| 401 | `{"error": "A valid API key is required."}` | The key is missing or wrong. |
| 404 | `{"error": "Race not found."}` | No race with that `id` is linked to the entry form. |
| 404 | `{"error": "The API is switched off."}` | ISORA have not set a key. |

When choosing a certificate, also:

| Status | Answer | Meaning |
|---|---|---|
| 400 | `{"error": "certificate must be \"primary\" or \"secondary\"."}` | `certificate` is missing or something else. |
| 400 | `{"error": "Give the boat and its sail_number."}` | Both are missing. |
| 404 | `{"error": "Boat not entered for this race."}` | No boat with that name and sail number is entered for the race. |
| 405 | `{"error": "Use POST to choose a certificate."}` | The request was a GET. |
| 409 | `{"error": "The rating deadline for this race has passed."}` | Too late: ask ISORA's race management. |
| 409 | `{"error": "This boat has no secondary certificate on the IRC list."}` | `secondary` was asked for a boat with one certificate. |
