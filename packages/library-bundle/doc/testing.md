# Testing

| Area | What is covered |
|---|---|
| `Record`, `Title`, `Copy`, `Loan` | Every value and its refusal, ISBN-10 and ISBN-13, the states of a loan and their changes, moments in UTC |
| `Catalogue`, `Loans`, `Reminders`, `LoanPolicy` | Each method on repositories in memory: due dates on the library's clock, reservations, check-outs, renewals, returns, losses, the member's limits, every refusal, the stock moves, the events, reminders and notices sent once, the history forgotten |
| The adapters | Messenger, the stockroom link on a real `Stockroom` in memory and on the tables of `phil/stockroom-bundle` in a loan's transaction that rolls back, the privacy module, the commands, the kit's names, the EasyAdmin links, the pages' roles |
| The bundle | Its services and options, the compiler passes — the stockroom of `phil/stockroom-bundle` and the loan's transaction on a shared connection —, the mapping |
| The pages | A kernel on SQLite with Twig, the recipes of the PHIL UX kit, forms with CSRF and Messenger: the catalogue searched and paged, a title, the desk, the administration, the loans overdue, the member's loans, the reminders handled, a linked copy lent and returned with `phil/stockroom-bundle` on the same database |
| MySQL 8.4 and PostgreSQL 17 | `tests/Integration`: the tables from the mapping, what is kept read back in UTC, the search and its counts, each query of the loans, the bulk changes of the history, the cascade of a title removed, the unique barcode, two loans of one copy made at once |

Infection holds the bundle at 100 % of covered mutants.

```sh
vendor/bin/phpunit packages/library-bundle/tests
```

The integration suite needs the servers of `phil/dev-tools`, in Docker; without their URLs, it is skipped:

```sh
vendor/bin/phil-dev services up
vendor/bin/phil-dev services test -- packages/library-bundle/tests/Integration
vendor/bin/phil-dev services down
```

It drops and creates the bundle's three tables in the test database.
