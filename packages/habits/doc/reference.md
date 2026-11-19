# Reference

Every public type of the package, with its role.

| Type | Kind | Role |
|---|---|---|
| `Phil\Habits\Habits` | `final readonly class` | The entry point: start, change, archive, check in, freeze, take back, read progress and history, list and forget a member's habits |
| `Phil\Habits\Habit` | `final readonly class` | A habit: its member, name, schedule, time zone, first day, target and last day; `archive()`, `change()` |
| `Phil\Habits\Schedule` | `final readonly class` | When a habit is due: `parse()`, the builders, `toString()`, `periods()`, `streakUnit()` |
| `Phil\Habits\Frequency` | enum | `daily`, `every`, `on`, `week`, `month` |
| `Phil\Habits\Period` | `final readonly class` | A period of a schedule: its days, the check-ins it asks, the days it stands for |
| `Phil\Habits\Day` | `final readonly class` | A calendar day: `fromString()`, `of()`, `plus()`, comparisons, its week and month |
| `Phil\Habits\CheckIn` | `final readonly class` | What a member did on a day, recorded in UTC |
| `Phil\Habits\CheckInStatus` | enum | `done`, `partial`, `skipped`, `frozen` |
| `Phil\Habits\ProgressCalculator` | `final readonly class` | Works out streaks and strength from the check-ins |
| `Phil\Habits\Progress` | `final readonly class` | A habit's streaks, strength and current period; `strengthPercent()` |
| `Phil\Habits\PeriodProgress` | `final readonly class` | Where a period stands: units done and asked, excused days |
| `Phil\Habits\Streak` | `final readonly class` | The current and best streaks, and their unit |
| `Phil\Habits\StreakUnit` | enum | `day`, `interval`, `week`, `month` |
| `Phil\Habits\HalfLifeStrength` | `final readonly class` | The default strength model, an exponentially smoothed average with a half-life in days |
| `Phil\Habits\Milestones` | `final readonly class` | The streak lengths and strengths celebrated by events |
| `Phil\Habits\Contract\HabitStore` | interface | Where habits are kept |
| `Phil\Habits\Contract\CheckInStore` | interface | Where check-ins are kept |
| `Phil\Habits\Contract\StrengthModel` | interface | How the strength moves with each period |
| `Phil\Habits\Memory\InMemoryHabitStore`, `InMemoryCheckInStore` | `final class` | The stores in memory |
| `Phil\Habits\Dbal\DbalHabitStore`, `DbalCheckInStore` | `final readonly class` | The stores on Doctrine DBAL, with `configureSchema()` |
| `Phil\Habits\Event\HabitChecked`, `HabitUnchecked`, `StreakReached`, `StrengthReached` | `final readonly class` | The events, naming the habit and its member |
| `Phil\Habits\Exception\HabitsException` | interface | Every exception of the package |
| `Phil\Habits\Exception\InvalidHabit` | `final class` | A habit, a table name or a setting out of bounds |
| `Phil\Habits\Exception\InvalidSchedule` | `final class` | A schedule rule or count out of bounds |
| `Phil\Habits\Exception\InvalidCheckIn` | `final class` | A check-in or a freeze on a day the habit does not take |
| `Phil\Habits\Exception\InvalidDay` | `final class` | A day not written as `YYYY-MM-DD`, or not in the calendar |
| `Phil\Habits\Exception\HabitNotFound` | `final class` | No habit has the identifier |

`Phil\Habits\Dbal\TableName` is internal to the database stores.
