# Extension points

The library is plain PHP: it declares no service and no port, and its classes are final. What an application
chooses goes through arguments.

| Point | How |
|---|---|
| The time zone of a place | The `$timezone` of `OpeningHours::of()`: every question — open now, next opening — is answered in it, whatever zone the moment asked about is in |
| Holidays and closures | The `$specials` of `OpeningHours::of()`: `SpecialHours` for a day or a period, computed by the application from its own calendar |
| Where hours are kept | `OpeningHoursArray::from()` gives an array for one JSON column, `OpeningHoursArray::to()` reads it back |
| What people type | `HoursText` reads and writes the text form; a form of another shape builds `DayHours` and `TimeRange` itself |
| Structured data | `OpeningHoursSchema::of()` gives schema.org `openingHoursSpecification`; the application puts it in its JSON-LD |
| Catching the library's errors | Every exception implements `Phil\OpeningHours\Exception\OpeningHoursException` |

Day names and the layout of a table of hours are the application's: `groups()` gives the runs of days with the same
hours, and the application writes them in its language.
