# Automatic lifting

An incident ends at its end whether or not anything runs: past its end, it suspends nothing, and every checkpoint lets
the request through. The lifting **records** that end in the journal — lifted at its end, without an actor — and sends
the library's `IncidentLifted`, once.

## The delayed message

With Messenger installed and the bus of `lifting.bus` registered, each started or extended incident sends the
library's `Phil\IncidentMode\Messenger\LiftIncident`, which carries the identifier of the incident and nothing else,
delayed until its end. Its handler, registered by the bundle, records the end once it is due; an incident extended
since waits for the message of its extension. Route it to a transport that supports delays:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async: '%env(MESSENGER_TRANSPORT_DSN)%'
        routing:
            Phil\IncidentMode\Messenger\LiftIncident: async
```

`lifting.messenger: false` sends no message, and the sweep alone records the ends.

## The recurring sweep

A transport may lose a message. With Symfony Scheduler installed, the bundle registers `IncidentSchedule`, the schedule
`incident_mode`, which sends the library's `LiftExpiredIncidents` every five minutes: its handler records the end of
every incident past it, a hundred at a time. A worker consumes it:

```sh
php bin/console messenger:consume async scheduler_incident_mode
```

`lifting.schedule.name` and `lifting.schedule.frequency` change its name and its frequency; `lifting.schedule: false`
leaves it out. Two workers that record the same end send its event once: the library lifts by a conditional update.

## Without Messenger

When the bus is not there, the bundle registers neither the message nor the schedule, and a cron job records the ends:

```cron
*/5 * * * * php /srv/app/bin/console phil:incident:lift --expired
```

## In a worker

The bundle's services keep nothing between requests: every answer reads the incidents in force at that moment, so
that a long-running worker sees an incident end at its end.
