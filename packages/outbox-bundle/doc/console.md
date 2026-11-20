# Console

## `phil:outbox:relay`

Relays the outbox batch after batch.

| Option | Default | Effect |
|---|---|---|
| `--batch-size` | `relay.batch_size` | The most records of a batch, 1 to 1,000 |
| `--limit` | 0 | The most batches; none when 0 |
| `--stop-when-empty` | off | Ends the run at the first batch that finds nothing |
| `--time-limit` | 0 | The seconds after which the run ends; none when 0 |
| `--sleep` | 1 | The seconds to wait after a batch that found nothing, 0 to 3,600 |

Without `--stop-when-empty`, `--limit` or `--time-limit`, the run goes on until it is stopped. SIGINT and SIGTERM end
it after the batch under way. A run that finds the lock held by another run gives way at once and ends successfully.
With `-v`, each batch that claimed records is told; the run ends with the sum:

```text
[OK] Claimed 3: 3 delivered, 0 skipped, 0 to try again, 0 dead, 0 left to their lease, 0 taken over.
```

## `phil:outbox:dead:show`

Lists the dead records, the oldest first, with the count of the records by state: `--limit` (50) and `--offset`
(0). Given an identifier, shows that record: its type, state, payload, keys, occurrence, attempts, settlement and last
error, the class and code of the exception of its last attempt.

## `phil:outbox:dead:retry`

Puts the named dead records back to pending, relayed at once with their attempts back to 0, or the oldest ones with
`--all`, up to `--limit` (100). It fails when a named record is not dead.

## `phil:outbox:dead:remove`

Removes the named dead records for good, or the oldest ones with `--all`, up to `--limit` (100), which lets the later
records of their ordering keys go. Nothing is removed without `--force`.

## `phil:outbox:purge`

Removes the delivered and skipped records settled more than `retention` seconds ago, the oldest first, up to `--limit` (1,000);
`--retention` chooses another number of seconds. The pending and dead records stay. Run it on a schedule, such as
once an hour.

## `phil:outbox:errors:clear`

Removes the message from the errors the table keeps, and keeps their class: `RuntimeException: 550 …` becomes
`RuntimeException`. An error keeps the class and code of an exception, and the message of the outbox's own exceptions
only; this command rewrites the errors written with the message of any exception, which can quote personal data such
as an address a mail server refused. The errors of the outbox's own exceptions are left as they are, and running it
again rewrites nothing. Registered with the `dbal` store.

## `phil:outbox:schema`

Shows the SQL that creates the table when its connection lacks it, and runs it with `--force`. A table that is there
is left as it is. Registered with the `dbal` store.
