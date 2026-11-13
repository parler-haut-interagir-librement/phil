# Automatic lifting

An incident ends at its end whether or not anything runs: an activation past its end is no longer in force, and
`isSuspended()` stops answering yes. **The automatic lifting records that end** — `liftedAt` set to the end,
`LiftReason::Expired`, no actor — and sends `IncidentLifted`, once.

## The delayed message

`IncidentMode` gives every started and extended activation to its `LiftScheduler`. `Messenger\MessengerLiftScheduler`
sends `Messenger\LiftIncident`, which carries the identifier of the activation and nothing else, delayed until its end
by a `DelayStamp`. `Messenger\LiftIncidentHandler` calls `liftIfDue()`: an incident extended since is not due yet, and
waits for the message its extension sent; one already lifted is left as it is.

Route the message to a transport that supports delays — Doctrine, Redis, AMQP; not `sync`:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            Phil\IncidentMode\Messenger\LiftIncident: async
```

## The recurring sweep

A transport may lose a message. `Messenger\LiftExpiredIncidents` has `liftExpired()` lift every incident past its end,
a hundred at a time; add it to a schedule of Symfony Scheduler:

```php
use Phil\IncidentMode\Messenger\LiftExpiredIncidents;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('incidents')]
final class IncidentSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return new Schedule()->add(RecurringMessage::every('5 minutes', new LiftExpiredIncidents()));
    }
}
```

Without Messenger, `NoLiftScheduler` is the default, and a cron job calls `liftExpired()`.

## In a worker

`IncidentMode` and its adapters keep nothing between two calls; the in-memory store is the exception, emptied by
`reset()`, and is meant for tests.
