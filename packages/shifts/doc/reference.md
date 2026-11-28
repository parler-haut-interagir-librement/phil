# Reference

## `Phil\Shifts\Shift`

| Type | Role |
|---|---|
| `Shift` | A shift: identifier, title, kind, window, minutes, places, mode, reserve, role, critical, weight, swap approval, location, description; `weightedMinutes()`, `overlaps()`, `isOpenAt()`, `isOverAt()`, `admits()`. |
| `ShiftKind` | `Fixed`, `Task`, `Duration`. |
| `AssignmentMode` | `SelfSignUp`, `Assigned`, `Interest`. |
| `Identifier` | The rules of the identifiers of shifts and offers, of volunteers, of roles and of the texts. |

## `Phil\Shifts\Roster`

| Type | Role |
|---|---|
| `Roster` | The volunteers of a shift, immutable: `open()`, `signUp()`, `expressInterest()`, `assign()`, `withdraw()`, `allocate()`, `transfer()`, `withShift()`, `statusOf()`, `confirmed()`, `reserve()`, `interested()`, `placesLeft()`, `isUnderstaffed()`; the events of the change in `$changes`. |
| `Place` | A volunteer on a roster: status, instant, the coordinator who assigned it; `with()`. |
| `PlaceStatus` | `Confirmed`, `Reserve`, `Interested`. |
| `Conflicts` | The fixed shifts a new one overlaps, with a rest between them; `of()`. |

## `Phil\Shifts\Allocation`

| Type | Role |
|---|---|
| `InterestAllocator` | Chooses who gets the free places among the interested; `choose()`. |
| `FairAllocator` | The fewest minutes held first, then the earliest interest, then by identifier. |

## `Phil\Shifts\Swap`

| Type | Role |
|---|---|
| `SwapOffer` | A place on the swap board: `offer()`, `take()`, `approve()`, `refuse()`, `cancel()`, `apply()`, `isStale()`. |
| `SwapStatus` | `Open`, `Pending`, `Completed`, `Cancelled`; `isActive()`. |

## `Phil\Shifts\Hours`

| Type | Role |
|---|---|
| `ServedHours` | The minutes served and weighted on the shifts that are over; `of()`, and the minutes each volunteer holds, `held()`. |

## `Phil\Shifts\Event`

| Type | Role |
|---|---|
| `ShiftEvent` | What changed, by the identifiers of the shift and of the volunteer. |
| `VolunteerConfirmed` | A place taken; the coordinator who assigned it in `assignedBy`. |
| `VolunteerReserved` | A sign-up waits in the reserve. |
| `InterestExpressed` | A volunteer is interested. |
| `VolunteerWithdrew` | A volunteer left the shift. |
| `ReservePromoted` | The first of the reserve took a freed place. |
| `PlaceTransferred` | A place passed through the swap board; the volunteer who gave it in `fromVolunteerId`. |
| `SwapOffered` | A place put on the swap board; the offer in `offerId`. |
| `SwapTaken` | An offer taken, waiting for a coordinator; the offer in `offerId`. |

## `Phil\Shifts\Format`

| Type | Role |
|---|---|
| `ShiftPlan` | The shifts of an event in the JSON format `phil-shifts/1`; `fromJson()`, `toJson()`. |
| `ShiftFormat` | A shift as fields, and back. |
| `RosterFormat` | The places of a roster as fields, and back with their shift. |
| `SwapFormat` | An offer as fields, and back. |
| `Fields` | Internal: the reader of the fields of a decoded document. |

## `Phil\Shifts\Exception`

| Type | Role |
|---|---|
| `ShiftsException` | Every exception the library throws. |
| `InvalidShift` | A value outside its bounds, named by its field. |
| `ShiftRefused` | An operation refused in its state, with a `reason` a screen translates. |
| `InvalidDocument` | A document that cannot be read, named by its path. |
