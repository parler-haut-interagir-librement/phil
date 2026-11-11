# Reference

| Type | Kind | Role |
|---|---|---|
| `OpeningHours` | `final readonly class` | The week, special hours and time zone; open now, next opening, runs |
| `DayHours` | `final readonly class` | A day's ranges |
| `TimeRange` | `final readonly class` | When a place opens and closes |
| `TimeOfDay` | `final readonly class` | A time to the minute |
| `SpecialHours` | `final readonly class` | Hours replacing the week's for a period |
| `DayOfWeek` | int-backed enum | ISO days, and their schema.org names |
| `HoursText` | `final class` | The text form |
| `OpeningHoursSchema` | `final class` | schema.org's `openingHoursSpecification` |
| `OpeningHoursArray` | `final class` | Hours to and from an array, for storage |
| `Exception\OpeningHoursException` | interface | Every exception of the library |
| `Exception\InvalidOpeningHours` | `final class`, `InvalidArgumentException` | Hours that cannot be |
| `Exception\UnreadableOpeningHours` | `final class`, `UnexpectedValueException` | Stored hours that cannot be read back |
