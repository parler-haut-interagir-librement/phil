# Plan format

## The plan of an event

The shifts of an event travel in the JSON format `phil-shifts/1` — what a coordinator imports, or duplicates from one
edition to the next:

```json
{
    "format": "phil-shifts/1",
    "shifts": [
        {
            "id": "desk-sat-am",
            "title": "Info desk",
            "kind": "fixed",
            "starts_at": "2026-11-28T09:00:00-05:00",
            "ends_at": "2026-11-28T12:00:00-05:00",
            "capacity": 2,
            "reserve": 1
        },
        {
            "id": "cloakroom-sat",
            "title": "Cloakroom",
            "kind": "duration",
            "starts_at": "2026-11-28T08:00:00-05:00",
            "ends_at": "2026-11-28T20:00:00-05:00",
            "minutes": 120,
            "capacity": 5,
            "mode": "interest",
            "critical": true,
            "weight": 150
        }
    ]
}
```

| Field | Type | Default |
|---|---|---|
| `id`, `title` | string | required |
| `kind` | `fixed`, `task` or `duration` | required |
| `starts_at`, `ends_at` | RFC 3339 instant with its offset, such as `2026-11-28T09:00:00Z` | required |
| `minutes` | whole number | the window, for a fixed shift; required otherwise |
| `capacity`, `reserve`, `weight` | whole number | 1, 0, 100 |
| `mode` | `self_sign_up`, `assigned` or `interest` | `self_sign_up` |
| `required_role` | string | none |
| `critical`, `swap_approval` | `true` or `false` | `false` |
| `location`, `description` | string | empty |

`ShiftPlan::fromJson()` reads a document within 1 MiB, a depth of 16 and 2,000 shifts, each identifier once; each
shift keeps the bounds of `Shift`. A fault throws `InvalidDocument`, whose message names the path of the field — such
as `$.shifts[3].starts_at: must be an instant such as 2026-11-28T09:00:00Z` —, never its value. An instant must exist:
February 30th, 24:00 or an offset of 24 hours are refused. `toJson()` writes the instants in UTC, and a plan read back
writes the same.

## The stored rosters and offers

A storage keeps a shift as `ShiftFormat::toArray()` writes it, its places as `RosterFormat::toArray()` writes them, and
an offer as `SwapFormat::toArray()` writes it, and reads them back with the `fromArray()` of each. A place is its
volunteer, its status — `confirmed`, `reserve` or `interested` —, its instant and the coordinator who assigned it:

```json
[
    {"volunteer": "ada", "status": "confirmed", "at": "2026-11-20T15:00:00Z", "assigned_by": "grace"},
    {"volunteer": "cy", "status": "reserve", "at": "2026-11-20T15:10:00Z"}
]
```

A roster read back keeps the bounds of its shift: each volunteer once, no more places, reserve or interested than it
allows.
