# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Keep habits elsewhere | Implement `Contract\HabitStore` |
| Keep check-ins elsewhere | Implement `Contract\CheckInStore` |
| Change how the strength moves | Implement `Contract\StrengthModel`, or give `HalfLifeStrength` another half-life, and pass it to `ProgressCalculator` |
| Choose what is celebrated | Pass `Milestones` of your own to `Habits` |
| Act after a check-in | Listen to the events: see [Events](events.md) |
| Name the tables | Pass the names to `DbalHabitStore` and `DbalCheckInStore` |

## The stores

`HabitStore::ofMember()` lists by identifier; `CheckInStore::between()` lists a range of days, both included, the
earliest first. `save()` replaces what is kept under the same key — the habit's identifier, the habit and day of a
check-in. A store in a database serves `between()` from an index on the habit and the day, as `DbalCheckInStore` does
with its primary key.

To test a store of your own, extend the library's contract the way `InMemoryStoreTest` does, if the library's tests are
available to you, or copy its cases.

## The strength model

`StrengthModel::next($strength, $completion, $days)` gets the strength before a period, the share of it done and the
number of days it stands for, and returns the strength after it, from 0 to 1. It is called once per period met or
missed, in order; it keeps no state of its own.
