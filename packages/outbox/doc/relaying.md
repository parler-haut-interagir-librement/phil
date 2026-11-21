# Relaying

`Relay::relay()` relays one batch of records:

1. It claims up to `$limit` records — 100 by default, at most 1,000 — under a token of its own and a lease of `$lease`
   seconds, 60 by default.
2. For each record, in the order they were recorded, it rebuilds the event through the record's codec and dispatches it
   through the PSR-14 dispatcher.
3. It marks the record delivered once the dispatch returned, and only then.
4. A failure gives the record back to be tried again later, or declares it dead after its last attempt.

```php
$relay = new Relay($store, $codecs, $dispatcher, $clock, new Backoff(), lease: 60);
$report = $relay->relay(100);
```

The relay keeps no state between runs: one instance serves every run of a worker. A Symfony application runs it with
the `phil:outbox:relay` command of phil/outbox-bundle; another application calls `relay()` from its own worker loop or
scheduled task.

## At least once

A record is delivered at least once, never exactly once: a process that stops after the dispatch and before the mark
delivers the record again on the next run. **Listeners are idempotent.** Each record has a stable identifier, which a
codec puts in its event and which `RecordedEvent::$id` carries: a listener keeps the identifiers it handled, or makes
its effect idempotent — an upsert, a unique constraint, a state that does not move twice.

## Leases

A claim leases its records until the end of its lease: no other claim gets them meanwhile, so that concurrent relays
never deliver the same record while its lease runs. The relay stops trying the records of its batch once the lease
ran out; they become free when the lease ends and the next claim takes them, with no attempt counted.

The lease is longer than the slowest listener. A listener that outlasts it lets another relay claim the record and
deliver it again; the mark of the first relay is then refused, which its report counts as `lost`.

## The order of a key

The records of an ordering key go one after the other: a store holds back a record while an earlier record of its key
is pending — leased, waiting for its next attempt — or dead. A batch holds at most one record of a key; the next one
goes in a later batch, once the earlier one is delivered or skipped. A dead record holds back its key until it is tried again or
discarded: see [Housekeeping](housekeeping.md). Records without a key never wait for each other.

## Retries and dead records

A failed attempt gives the record back with its attempts and the class and message of the exception, cut to 500
bytes. `Backoff` decides when it is tried again: its first delay after the first failure, doubled after each failure
that follows, never above its longest delay. After its last attempt, the record is dead.

| Setting of `Backoff` | Default |
|---|---|
| `$firstDelay` | 60 seconds |
| `$longestDelay` | 86,400 seconds |
| `$maxAttempts` | 10 |

A record is dead at once, after one attempt, when its payload is not one, or when its codec throws
`UndeliverableRecord`.

## Unknown types

A record whose type no codec rebuilds is tried again while the grace of unknown types runs, counted from when the
record happened: a web process deployed with a new codec can record a type before the workers that relay it are
deployed. Its attempts follow the backoff, never later than the end of the grace and whatever their number; once the
grace is over, the next attempt declares it dead. The grace is the last argument of `Relay`: one hour by default
(`Relay::UNKNOWN_TYPE_GRACE`), from 0, which buries the record at once, to seven days
(`Relay::MAX_UNKNOWN_TYPE_GRACE`).

## Skipped records

A codec throws `ForgottenRecord` when what the record names was erased on purpose. The relay marks the record skipped,
with no attempt counted and no error: no listener receives it, it is never dead, it lets the later records of its key
go, and the purge removes it with the delivered records.

## The report

`RelayReport` tells what a run did:

| Property | Counts the records |
|---|---|
| `claimed` | the run claimed |
| `delivered` | delivered and marked so |
| `retried` | given back to be tried again |
| `dead` | declared dead |
| `expired` | left untried because the lease ran out |
| `lost` | whose mark another claim had taken over |
| `skipped` | skipped because what they name was erased |

`isEmpty()` tells a run that found nothing; `plus()` adds two runs.
