# Reference

| Type | Role |
|---|---|
| `PhilReservationBundle` | Wires the services, maps the entities, configures `phil_reservation` |
| `Domain\Model\BookableResource` | A resource and what decides its bookings |
| `Domain\Model\Reservation` | Units of a resource booked for a span |
| `Domain\Status\ReservationStatus` | Held, confirmed, cancelled, expired |
| `Domain\Contract\ResourceRepository` | The port of the resources |
| `Domain\Contract\ReservationRepository` | The port of the reservations and of the resource's lock |
| `Domain\Event\Reservation*` | The events of the changes, with identifiers only |
| `Domain\Exception\InvalidReservation` | A value or a change refused |
| `Application\Reservations` | Checks, holds, books, confirms, moves, cancels, lists slots, expires holds |
| `Application\Reminders` | Sends the reminders due |
| `Application\ReservationDraft` | What the reservation form fills |
| `Application\Contract\ReminderSender` | The port that sends a reminder |
| `Application\Message\SendReservationReminder` | The Messenger message of a reminder |
| `Application\Exception\ReservationRefused` | A refusal, with its decision |
| `Application\Exception\NotFound` | A resource or reservation missing, or a resource inactive |
| `Infrastructure\Doctrine\DoctrineResourceRepository` | The resources with Doctrine |
| `Infrastructure\Doctrine\DoctrineReservationRepository` | The reservations and the lock with Doctrine |
| `Infrastructure\Form\ReservationType` | The reservation form |
| `Infrastructure\Translation\ViolationMessages` | The reasons in the reader's language |
| `Infrastructure\Console\ExpireHoldsCommand` | `phil:reservation:expire-holds` |
| `Infrastructure\Console\SendRemindersCommand` | `phil:reservation:send-reminders` |
| `Infrastructure\Messenger\MessengerReminderSender` | Sends each reminder as a message |
| `Infrastructure\Calendar\ReservationItems` | The confirmed reservations in the calendar's views |
