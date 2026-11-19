# Storage

## The tables

`phil_error_group`, one row per fingerprint:

| Column | Type |
|---|---|
| `fingerprint` | 64 characters, primary key |
| `title`, `culprit` | text, the culprit nullable |
| `status` | `open`, `resolved`, `ignored` or `regressed` |
| `times_seen` | big integer |
| `first_seen_at`, `last_seen_at` | date and time in UTC |
| `last_release`, `resolved_release` | 128 characters, nullable |

`phil_error_event`, the scrubbed events of each group:

| Column | Type |
|---|---|
| `id` | big integer, in the order the events came |
| `event_id` | the event's 32 hexadecimal digits |
| `fingerprint` | its group |
| `occurred_at` | date and time in UTC |
| `payload` | the event as JSON, from `ErrorEvent::toArray()` |

The instants are columns of the type `datetime_utc_immutable` of DBAL: written in UTC and read back in UTC, whatever the
time zone of PHP or of the database, so that two servers in different zones read the same moment and the purge
compares like with like.

With the ORM, `SchemaListener` adds both tables to the schema of their connection, so that `doctrine:migrations:diff`
writes their migration. Without it:

```php
use Doctrine\DBAL\Schema\Schema;
use Phil\ErrorTrackingBundle\Infrastructure\Doctrine\ErrorTables;

foreach (ErrorTables::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $sql) {
    $connection->executeStatement($sql);
}
```

## The cap of events

Each event saved is appended, then the events of its group past `storage.events_per_group` are deleted, the oldest
first, in one statement. The group keeps counting every event, kept or not.

## Concurrency

A group is updated when it has a row, and inserted otherwise; when two first events race, the second insert meets
the unique key and updates instead. Two processes that read a group at once and save it one after the other lose one
count: the count is an order of magnitude, not a ledger.

## The purge

```sh
bin/console phil:error-tracking:purge                  # the retention of the configuration
bin/console phil:error-tracking:purge --older-than=7   # days
```

The command removes the events older than the retention and the groups not seen since, and prints how many rows went.
Run it every night, from the scheduler or a cron.
