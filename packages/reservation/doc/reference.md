# Reference

| Type | Role |
|---|---|
| `Interval` | A span of time, its end excluded, read to the second; `from` and `until` are its Unix timestamps |
| `Booking` | A booking that holds units of a resource for a span |
| `BookingRequest` | A booking asked for: span, moment asked, units, booking replaced |
| `Bookable` | A resource: capacity, buffers, availability, rules, time zone; checks, units free, slots |
| `Decision` | The answer to a request: its violations and the bookings it meets |
| `Violation` | A reason a request is refused, with its parameters |
| `ViolationCode` | The reasons, with stable string values |
| `LoadProfile` | The units held at each moment, and the peak of a span |
| `ReminderSchedule` | The offsets of a booking's reminders, their moments and the one due |
| `Contract\Availability` | The port of the open spans of a resource |
| `Contract\Rule` | The port of a rule a request follows |
| `Availability\AlwaysAvailable` | Open at every moment |
| `Availability\WeeklyAvailability` | Open by weekday on a clock, with days closed |
| `Rule\MinimumNotice` | A notice before the start |
| `Rule\MaximumAdvance` | A booking window that slides |
| `Rule\DurationLimits` | A shortest and a longest length |
| `Rule\SlotGrid` | Starts and lengths on a grid of minutes |
| `Exception\ReservationException` | The marker of every exception of the library |
| `Exception\InvalidInterval` | A span that cannot be, or one longer than the library reads |
| `Exception\InvalidDefinition` | A resource, rule, booking or schedule defined with a value it cannot take |
