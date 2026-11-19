# Events

`Habits` dispatches events through the PSR-14 dispatcher it is given, when there is one. Each names the habit and its
member — two opaque identifiers —, and the day or the number reached; never the habit's name or a value checked in.

| Event | Dispatched when | Carries |
|---|---|---|
| `HabitChecked` | A day was checked in: done, in part, skipped or frozen. | `habit`, `member`, `day` as `YYYY-MM-DD` |
| `HabitUnchecked` | The check-in of a day was taken back. | `habit`, `member`, `day` |
| `StreakReached` | A check-in brought the current streak to one of the lengths of `Milestones`. | `habit`, `member`, `length`, `unit` |
| `StrengthReached` | A check-in raised the strength to or past one of the percentages of `Milestones`. | `habit`, `member`, `percent` |

All are in `Phil\Habits\Event`. After a check-in, `HabitChecked` comes first, then the streaks reached from the
shortest, then the strengths from the lowest. A check-in that replaces another of the same day dispatches nothing for a
milestone already passed; one taken back and recorded again reaches it again, as the streak does. A fall in strength
dispatches nothing: the library never tells a member they lost something.

## Milestones

`Milestones` holds the streak lengths and the strength percentages to celebrate — by default 3, 7, 14, 30, 60, 100, 200
and 365 periods, and 25, 50, 75 and 100 % —, up to fifty of each:

```php
new Milestones(streaks: [7, 21, 66], strengths: [50, 90]);
```

A streak length counts periods of the habit's schedule: 7 is a week of days for a daily habit, seven weeks for a habit
due three times a week. The event's `unit` says which.

## Gamification

The library awards nothing. A gamification package listens to these events and turns them into points, badges or
celebrations, under its own rules; a bridge to a points ledger lives in that package, never here. A listener that needs
more than the identifiers reads the habit with `Habits::get()` and its progress with `progress()`.
