# Workers

The relay keeps no state between batches or runs: the store, the relay and the runner are stateless services, and a
long-running process holds nothing from one batch to the next. Each run is bounded: a batch holds at most
`relay.batch_size` records, and a run ends after its batches, its time or a signal.

## Under a process manager

```ini
; supervisord
[program:outbox-relay]
command=php /srv/app/bin/console phil:outbox:relay --time-limit=3600
autorestart=true
```

The run sleeps `--sleep` seconds after a batch that found nothing, and ends after an hour so that the process manager
starts a fresh one. SIGTERM ends it after the batch under way.

## One run at a time

With `relay.lock` and a lock factory — FrameworkBundle's `lock.factory`, present when `symfony/lock` is installed —,
one run at a time holds the lock `phil_outbox.relay`, refreshed after each batch, for twice the lease. A run started
while another holds it gives way at once. Use a store of locks that every host shares when the relay runs on several
hosts.

Without the lock, several runs share the outbox safely: each batch is leased, so that two runs never deliver the same
record while its lease runs, and the records of an ordering key still go one after the other. Turn `relay.lock` off to
relay with several processes at once.

## On a schedule

```yaml
phil_outbox:
    scheduler:
        enabled: true
        frequency: 60
        max_batches: 10
```

The bundle adds a schedule `phil_outbox` of Symfony Scheduler: every `frequency` seconds, a `RelayOutbox` message,
which carries nothing, reaches `RelayOutboxHandler`, which relays until the outbox is empty or `max_batches` batches
are spent. A Messenger worker consumes it:

```sh
php bin/console messenger:consume scheduler_phil_outbox
```

The scheduled trigger needs `symfony/scheduler` and `symfony/messenger`; a tick that finds another run holding the
lock does nothing.

## The purge

Run `phil:outbox:purge` on a schedule of its own, such as a recurring message of the application's schedule or a
cron entry, to remove the delivered records past their retention.
