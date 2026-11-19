# Storage

## The ports

| Port | Keeps | Asked for |
|---|---|---|
| `Contract\HabitStore` | Habits | `get()`, `ofMember()` by identifier, `save()` in place of the one kept, `remove()` |
| `Contract\CheckInStore` | Check-ins, at most one per habit and day | `get()`, `between()` the earliest first, `save()` in place of the day's, `remove()`, `removeHabit()` |

A store keeps no state between requests other than what it stores, so that `Habits` stays safe in a worker.

## In memory

`Memory\InMemoryHabitStore` and `Memory\InMemoryCheckInStore` keep everything in arrays, lost with the object: for
tests, demonstrations and a single process.

## On Doctrine DBAL

`Dbal\DbalHabitStore` and `Dbal\DbalCheckInStore` keep habits and check-ins in two tables of a Doctrine DBAL connection.
They run on SQLite, MySQL and PostgreSQL, and are tested on SQLite, MySQL 8.4 and PostgreSQL 17.

| Table | Columns | Key and index |
|---|---|---|
| `phil_habit` | `id`, `member`, `name`, `schedule` (its rule), `time_zone` (its name), `starts_on`, `target`, `archived_on` | Primary key on `id`; index on `member` |
| `phil_habit_check_in` | `habit`, `day`, `status`, `value`, `recorded_at` (a Unix timestamp, in UTC) | Primary key on `habit` and `day` |

Days are kept as dates, without a time or a zone. Identifiers are kept as visible ASCII and compared byte for byte: on
MySQL, whose default collation is blind to case, their columns take the `ascii_bin` collation. The primary key of the
check-ins serves the progress of a habit in one range scan.

Each store adds its table to a schema that lacks it, under the name it was given — `phil_habit` and
`phil_habit_check_in` by default:

```php
use Doctrine\DBAL\Schema\Schema;

$habits = new DbalHabitStore($connection);
$checkIns = new DbalCheckInStore($connection);

$schema = $checkIns->configureSchema($habits->configureSchema(new Schema()));
```

A Doctrine Migrations migration calls them on the schema it is given; an application on the ORM calls them from a
`postGenerateSchema` listener, so that `doctrine:schema:update` and `make:migration` see the tables. A table name is
checked before it reaches SQL: up to 63 lowercase letters, digits or `_`, not starting with a digit.

Saving a habit or a check-in replaces the row of the same key in one transaction. A row the stores did not write — a
column of the wrong type, an unknown status — is refused with `UnexpectedValueException` rather than read as something
else.
