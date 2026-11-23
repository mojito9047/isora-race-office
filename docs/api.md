# ISORA Race Office: API

A read-only feed of ISORA's races and the boats entered for each one, for another system such as Crewmanager. Answers are JSON.

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

Use GET. Example:

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
| `person_in_charge` | The name, e-mail address and mobile number given on the entry form. |
| `entered_at` | When the boat's latest entry form was sent, in UTC. |

Boats are in name order. A boat appears once per race, taken from the latest entry form it sent. There is no boat number: match boats on sail number and name, or on the person in charge's e-mail address.

Entries change until the race: boats can enter or withdraw by sending the form again, and ratings change until the rating deadline. Fetch again rather than keeping an old copy; once an hour is plenty.

## When something is wrong

| Status | Answer | Meaning |
|---|---|---|
| 401 | `{"error": "A valid API key is required."}` | The key is missing or wrong. |
| 404 | `{"error": "Race not found."}` | No race with that `id` is linked to the entry form. |
| 404 | `{"error": "The API is switched off."}` | ISORA have not set a key. |
