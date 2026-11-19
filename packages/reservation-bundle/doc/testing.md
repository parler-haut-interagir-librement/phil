# Testing

| Area | What is covered |
|---|---|
| `BookableResource`, `Reservation` | Every value and its refusal, the states and their changes, moments on the resource's clock |
| `Reservations`, `Reminders` | Each method on repositories in memory: the lock, refusals, expired holds, events, slots, the buffers of two neighbours added up, a person booked like a room, reminders sent once |
| Doctrine repositories | SQLite in memory: what is kept, moments stored and read in UTC in a process of another zone, each query's filters and order, the lock committed and rolled back |
| Form, messages, commands, Messenger, calendar | Each on its own, with the bundle's translations |
| The bundle | Its services and options, the compiler pass, the mapping, and a kernel on SQLite with the calendar, Messenger and a listener of the events |
| MySQL 8.4 and PostgreSQL 17 | `tests/Integration`: the tables from the mapping, what is kept, each query, the version of a reservation, the cascade of a resource deleted, the buffers of neighbours added, and six processes racing for the last unit |

Infection holds the bundle at 100 % of mutants killed.

```sh
vendor/bin/phpunit packages/reservation-bundle/tests
```

The integration suite needs the servers of `phil/dev-tools`, in Docker; without their URLs, it is skipped:

```sh
vendor/bin/phil-dev services up
vendor/bin/phil-dev services test -- packages/reservation-bundle/tests/Integration
vendor/bin/phil-dev services down
```

It drops and creates the bundle's two tables in the test database.
