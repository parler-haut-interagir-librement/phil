# Shifts and rosters

## The kinds of shifts

| Kind | Asks a volunteer | Counts | Closes |
|---|---|---|---|
| `Fixed` | to be there from the start to the end | the minutes of its window, 1,440 at most | at its start |
| `Task` | to do a task some time within the window | the minutes it is estimated at, 5 to 1,440 | at the end of its window |
| `Duration` | to serve a number of minutes when they choose within the window | those minutes, 5 to 1,440 | at the end of its window |

A window lasts 5 minutes to 31 days. The minutes of a task or a duration fit in its window. A shift built outside its
bounds throws `InvalidShift`, whose message names the field, never its value.

| Field | Bounds | Default |
|---|---|---|
| `id` | 1 to 64 lowercase letters, digits, `_`, `.` and `-`, starting with a letter or a digit | — |
| `title` | 1 to 200 characters | — |
| `capacity` | 1 to 1,000 places | 1 |
| `reserve` | 0 to 100 volunteers | 0 |
| `mode` | `SelfSignUp`, `Assigned`, `Interest` | `SelfSignUp` |
| `requiredRole` | 1 to 64 letters, digits, `_`, `.`, `:` and `-` | none |
| `critical` | shown first while it lacks volunteers | `false` |
| `weight` | 10 to 1,000 percent of its minutes | 100 |
| `swapApproval` | a swap waits for a coordinator | `false` |
| `location`, `description` | 200 and 2,000 characters | empty |

The instants are kept in UTC, whatever the time zone they were given in.

## The three modes

| Mode | `signUp()` | `expressInterest()` | `assign()` | `allocate()` |
|---|---|---|---|---|
| `SelfSignUp` | a place, then the reserve | refused | a coordinator | refused |
| `Assigned` | refused | refused | a coordinator | refused |
| `Interest` | refused | the list of the interested | a coordinator | the allocator |

A coordinator's `assign()` works in every mode and does not check the roles: the coordinator decides. A volunteer of
the reserve or of the interested who is assigned moves to a place. A refused operation throws `ShiftRefused` with its
`reason`:

| Reason | When |
|---|---|
| `shift.wrong_mode` | the operation is not one of the shift's mode |
| `shift.closed` | a fixed shift started, or the window of a task or a duration ended |
| `shift.role_required` | the volunteer lacks the role the shift requires |
| `shift.already_on_roster` | the volunteer stands on the roster already |
| `shift.full` | no place, and no room in the reserve or among the 1,000 interested |
| `shift.not_on_roster` | a withdrawal of a volunteer who is not there |
| `shift.not_confirmed` | a transfer or a swap offer of a volunteer who holds no place |

## The reserve

A sign-up on a full shift waits in its reserve, in the order of arrival, while the reserve has room. When a volunteer
who holds a place withdraws — or a coordinator takes them off —, the first of the reserve takes the place, and the
change holds `VolunteerWithdrew` then `ReservePromoted`. A volunteer of the reserve who withdraws leaves it, and nobody
moves.

## Allocation among the interested

`allocate()` gives the free places of a shift in the `Interest` mode to the volunteers an `InterestAllocator` chooses.
The roster keeps only the volunteers it returns that are interested, once each, and no more than the places; the others
stay interested. `FairAllocator` chooses those who hold the fewest minutes first — the minutes an application reads
from the other rosters with `ServedHours::held()` —, then those who expressed their interest first, then by
identifier, so that the same roster always allocates the same way.

```php
use Phil\Shifts\Allocation\FairAllocator;
use Phil\Shifts\Hours\ServedHours;

$roster = $roster->allocate(new FairAllocator(), ServedHours::held($otherRosters), $now);
```

## Conflicts

`Conflicts` lists the shifts a volunteer holds that a new one would overlap: two fixed shifts whose windows meet, or
come closer than the rest asked between them, 0 to 1,440 minutes. A shift that ends at 12:00 and one that starts at
12:00 do not overlap without rest. A task or a duration is served when the volunteer chooses, and overlaps nothing. The
roster does not see the other shifts; the application asks before a sign-up, an assignment or a swap:

```php
use Phil\Shifts\Roster\Conflicts;

if ([] !== new Conflicts(restMinutes: 30)->of($shift, $shiftsTheVolunteerHolds)) {
    // refuse, or tell the coordinator
}
```

## A corrected shift

`withShift()` gives a roster its corrected shift — a new title, more places, another window — with the same
volunteers. A capacity or a reserve smaller than those who stand in it, or another identifier, throws `InvalidShift`.

## Hours served

`ServedHours::of()` reads the rosters of the shifts that are over — once their window ended — on which the volunteer
held a place: the minutes, the weighted minutes, rounded half up to the minute, and the shifts counted. A certificate of
volunteer hours or a reward by thresholds reads them.

## The events

Each operation returns the next roster with the events of that change in `$changes`, which carry the identifiers of
the shift and of the volunteer only:

| Event | When |
|---|---|
| `VolunteerConfirmed` | a place taken: signed up, assigned — with the coordinator in `assignedBy` —, or allocated |
| `VolunteerReserved` | a sign-up waits in the reserve |
| `InterestExpressed` | a volunteer is interested |
| `VolunteerWithdrew` | a volunteer left the shift |
| `ReservePromoted` | the first of the reserve took a freed place |
| `PlaceTransferred` | a place passed through the swap board, with the volunteer who gave it in `fromVolunteerId` |
