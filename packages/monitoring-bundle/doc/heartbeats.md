# Heartbeats

## Declaring the tasks

```yaml
phil_monitoring:
    heartbeats:
        tasks:
            backup: { every: 86400, grace: 3600 }   # once a day, an hour of grace
            invoices: { every: 300 }                # every five minutes
```

A task is expected to beat every `every` seconds. It is late once its last beat is older than `every` and `grace`
together; a task that never beat since the beats are kept is `never`, and is not alerted, so that a deployment does not
alert every task at once. Only a declared task beats: the store holds one entry per task of the configuration.

## Beating

From Symfony Scheduler, give the message the `HeartbeatTask` interface:

```php
use Phil\MonitoringBundle\Application\Contract\HeartbeatTask;

final readonly class BackUpDatabase implements HeartbeatTask
{
    public function heartbeatTask(): string
    {
        return 'backup';
    }
}
```

The task beats once the message's handler has returned, so a run that throws does not beat. A message naming a task
that is not declared is logged as a warning.

From a cron job, beat once the work succeeded:

```sh
0 3 * * * cd /srv/app && ./backup.sh && bin/console phil:monitoring:heartbeat backup
```

The command fails for a task that is not declared. From the application's code, `Heartbeats::beat()` answers whether
the task is declared.

## The dead-man switch

A round of the switch alerts every task late since its last alert, once: the task is marked, and stays quiet until it
beats again. Run a round every minute, on one schedule or one cron job only:

```php
use Phil\MonitoringBundle\Application\Message\WatchHeartbeats;
use Symfony\Component\Scheduler\RecurringMessage;

$schedule->add(RecurringMessage::every('1 minute', new WatchHeartbeats()));
```

```sh
* * * * * cd /srv/app && bin/console phil:monitoring:watch
```

The command prints every task and fails while one is late, for a cron that mails its failures.

## One alert for several workers

Each round alerts a task under a lock of Symfony Lock named `phil_monitoring.heartbeat.<task>`, taken without waiting
and held `heartbeats.lock.ttl` seconds at most, 30 by default. A round that finds the lock taken skips the task; the
round holding it reads the alert mark again before notifying, so two workers, or two servers, never alert the same
lateness twice — even when a second schedule runs by mistake.

```yaml
framework:
    lock: '%env(LOCK_DSN)%'        # a store every server shares: Redis, Valkey, a database…

phil_monitoring:
    heartbeats:
        lock:
            factory: lock.factory  # the application's LockFactory
            ttl: 30
```

The guarantee holds across servers only when the store of the lock factory is shared: the default `flock` store of
FrameworkBundle covers the workers of one server. A lock store that fails is logged at the error level and the task
waits for the next round; a lock that cannot be released is logged as a warning and expires on its own.

## The alerts

Each alert is a `Notification` of Symfony Notifier, on the channels and with the importance of `heartbeats.alerts`:

> Scheduled task "backup" is late
>
> It was expected to run again by 2026-11-09T04:00:00+00:00.

It goes to the admin recipients of the notifier — `framework.notifier.admin_recipients` —, and to the channel's
default destination for a chat channel. It names the task and the moment it was due only. An alert that cannot be sent is
logged at the error level, and the task stays unmarked: the next round tries again. Without Notifier, the late tasks
are marked and returned to the command, which prints them.

The late tasks also make the `heartbeats` check degraded, so that a monitor reading the detailed view sees them.
