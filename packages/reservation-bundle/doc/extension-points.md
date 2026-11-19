# Extension points

| Point | How |
|---|---|
| Where resources and reservations are kept | Implement `Domain\Contract\ResourceRepository` and `Domain\Contract\ReservationRepository` and alias them; `exclusively()` must make the requests on one resource run one at a time |
| How reminders are sent | Implement `Application\Contract\ReminderSender` and alias it, or handle the `SendReservationReminder` message |
| Reacting to a change | Listen to `ReservationHeld`, `ReservationConfirmed`, `ReservationRescheduled`, `ReservationCancelled` and `ReservationExpired` |
| The words of the reasons and the labels | The messages of the `phil_reservation` domain, in the application's translations |
| Rules of the application's own | Check them before calling `Reservations`, or build a `phil/reservation` `Bookable` with a `Rule` of its own |
| How much each run reads | The `batch` option |
| The calendar | The `calendar` option, and the tags of the items for the calendar's filters |
| The clock | The `clock` service: the bundle never reads the time itself |

## What is built on the bundle

The bundle is the core of the bookings an application offers; three uses are planned as packages of their own, each
on top of it without changing it:

| Use | What it adds | On |
|---|---|---|
| Appointments with a person or a service | Services and their durations, the providers who give them, « any provider », a booking page | One `BookableResource` per provider, of capacity one; `slots()` for the free times; `ReservationConfirmed` for the confirmation |
| Equipment rental | Items lent out and given back, accessories booked with a room | One resource per kind of item, its capacity the number of items; `quantity` for several; the buffers for the cleaning or the check between two loans |
| Lodging | Nights instead of hours, arrivals and departures at set times | Resources of whole days, the slot grid and the duration limits for the nights; the hours for the arrival and departure times |

Each keeps in its own tables what it adds — a service, a provider's skills, an item's state — by the identifiers of
the resources and reservations, and books through `Reservations`, so that the lock and the rules stay the bundle's.
The holder of a reservation is an identifier the application chooses, never an e-mail address: see
[Reservations](reservations.md#moments-and-holders).
