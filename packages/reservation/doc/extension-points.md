# Extension points

The library is plain PHP: it declares no service, and its classes are final. What an application chooses goes through
two ports and through arguments.

| Point | How |
|---|---|
| When a resource is open | Implement `Phil\Reservation\Contract\Availability`: the open spans that meet a period, in order, those that touch joined; holidays, opening hours kept elsewhere, a shared schedule |
| What a booking must follow | Implement `Phil\Reservation\Contract\Rule`: a holder's quota, a role, a price; it returns a `Violation` or `null` |
| What a booking is checked against | The `Booking`s passed to `check()`, `free()` and `slots()`: the application decides which count, confirmed ones and holds not expired |
| Moving a booking | The `$replacing` identifier of `BookingRequest` and `free()` |
| The clock | The `$requestedAt` of `BookingRequest` and the `$now` of `slots()` and `ReminderSchedule::due()`: the library never reads the time itself |
| Messages, in any language | Written by the application from `ViolationCode`'s values and each violation's parameters; the library writes none |
| How many slots are read | The `$limit` of `slots()`, within `Bookable::MAX_SLOTS` |
| Catching the library's errors | Every exception implements `Phil\Reservation\Exception\ReservationException` |

A rule of its own receives the request and the resource's time zone, and keeps no state between calls, so that one
`Bookable` serves every request of a long-running worker.
