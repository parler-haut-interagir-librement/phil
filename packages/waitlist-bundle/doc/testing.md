# Testing

| Area | What is covered |
|---|---|
| `Waitlist`, `Entry` | Every value and its refusal, the bounds of the window, the places held, the seed, the rank in a queue and in a draw, each status and its changes, the deadline to the second |
| `Waitlists` | Each method on repositories in memory: the lock, each refusal, joining twice, the position, places held until someone fits, offers and their deadline, accepting, declining, leaving, lapsing list by list, the draw, the batch, the policy's pick checked, the events and their order |
| `FirstInLine`, `FirstThatFits` | The order kept, or a group passed over |
| Doctrine repositories | SQLite in memory: what is kept, the subject unique, each query's filters, order and limit, the reads that see what another request saved, the lock committed, rolled back and reading the list again |
| `WaitlistNotifier` | The notification of each event: its recipient, subject, data and duplicate key; the types not named or not declared |
| `phil:waitlist:lapse` | The offers lapsed and the count |
| The bundle | Its services and options, their bounds, the mapping, the notifier wired or removed, the recipe, and a kernel on SQLite with the notifications bundle and a listener of the events |

Infection holds the bundle at 100 % of mutants killed.

```sh
vendor/bin/phpunit packages/waitlist-bundle/tests
```

The tests need no server: SQLite runs in memory.
