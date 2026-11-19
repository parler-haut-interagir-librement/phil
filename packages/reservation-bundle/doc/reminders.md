# Holds and reminders

## Expired holds

A hold past its moment frees its units whether it is marked or not. `phil:reservation:expire-holds` marks the holds
whose moment came as expired, at most `batch` per run, so that `ReservationExpired` goes out for each:

```sh
php bin/console phil:reservation:expire-holds
```

## Reminders

`phil:reservation:send-reminders` sends the reminders due, each once, at most `batch` reservations per run, the
soonest first. Run it every few minutes, from cron or a Scheduler task:

```sh
php bin/console phil:reservation:send-reminders
```

For each confirmed reservation that starts within the furthest reminder of the resources, `Reminders` asks the
resource's `ReminderSchedule` which reminder is due, records it on the reservation, then sends it. A reminder whose
moment passed unsent is overtaken by the next one; a sender that fails loses that reminder rather than sends it twice.

With Messenger, each reminder is a `SendReservationReminder` message on the default bus, carrying the reservation's
identifier and the reminder's offset in minutes. The application routes it to a transport and handles it — reads the
reservation and its holder, and writes to them by mail, notification or text:

```php
#[AsMessageHandler]
final class RemindHolder
{
    public function __invoke(SendReservationReminder $reminder): void
    {
        // read the reservation $reminder->reservationId, then write to its holder
    }
}
```

Without Messenger, an application that registers its own `ReminderSender` as that interface's service gets the
reminders through it; without either, there is no reminder service and no command.
