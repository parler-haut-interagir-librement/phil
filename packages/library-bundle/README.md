# phil/library-bundle

**A game or book library for Symfony: catalogue, search, loans, returns and reminders, its copies stockroom items.**

phil/library-bundle runs a library of games, books or anything else lent. Its catalogue holds titles described by
their records — ISBN, authors, publisher, year, and for a game the players, the length of a play and the youngest age
it suits —, searched by a list filtered with a plain form, paged by links, without JavaScript. Each title has copies,
known by their barcodes, each with its condition and a note — the pieces missing from a box.

Copies are lent at the desk or reserved ahead, on `phil/reservation`: each copy is a resource of one unit and each loan
a booking of it, so that a copy out or reserved over a span is not lent again over it. A loan is due at the end of its
last day on the library's clock, renewed a set number of times when nobody reserved the copy after it, returned with
the condition the copy is in, or recorded lost. A member with a copy overdue, or holding as many loans as allowed,
borrows no more. Reminders before the due date and notices of a loan overdue go out as Messenger messages carrying
identifiers only; no fine is counted.

A member is the application's stable identifier — a card number, an account number —, never an e-mail address. A
member sees their loans on a page of their own; the staff have the desk, the administration of the catalogue and the
list of loans overdue, reached from an EasyAdmin back office if the application has one. The pages are drawn by the
recipes of the PHIL UX kit. The history of a member's loans is part of the access and erasure requests of
`phil/privacy-bundle`, and can be forgotten after a number of days. A copy can be an item of `phil/stockroom`: its
loans then move the item out to the loan and back — with `phil/stockroom-bundle` on the same database, in the loan's
own transaction.

## Status

| Part | State |
|---|---|
| `Title`, `Copy`, `Loan` in Doctrine, moments in UTC, `Record` and its ISBN | Implemented and tested, on SQLite, MySQL 8.4 and PostgreSQL 17 |
| `Catalogue`: titles, records, copies, conditions, stockroom links | Implemented and tested |
| `Loans`: lend, reserve, check out, renew, return, cancel, lose, the member's limits, the history forgotten | Implemented and tested |
| Two loans of one copy made at once | Tested on MySQL and PostgreSQL |
| `DbalTitleSearch`, the catalogue's search with DBAL | Implemented and tested |
| `Reminders`, the Messenger sender, the commands | Implemented and tested |
| The pages: catalogue, title, member's loans, desk, administration, overdue; the kit's recipes | Implemented and tested |
| `LoanHistoryPrivacy`, the module of `phil/privacy-bundle` | Implemented and tested |
| `StockroomLink`, the link between a copy and an item of `phil/stockroom`, in the transactions of `phil/stockroom-bundle` | Implemented and tested |
| `LibraryAdmin`, the links of an EasyAdmin back office | Implemented and tested |
| The Flex recipe | Written in `recipe/` |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering the bundle, the schema, the routes, the recipes of the kit. |
| [Configuration](doc/configuration.md) | Every option of `phil_library`, with an example. |
| [Catalogue and search](doc/catalogue.md) | Titles, records, ISBNs, copies, the search. |
| [Loans](doc/loans.md) | Lending, reserving, renewing, returning, losing, the member's limits, the lock, the stockroom link. |
| [Reminders and privacy](doc/reminders.md) | The reminders and notices, the commands, the history in the privacy requests. |
| [Pages](doc/pages.md) | The routes, the templates, the recipes of the kit, the roles, the EasyAdmin links. |
| [Extension points](doc/extension-points.md) | The ports, events and options an application chooses or replaces. |
| [Testing](doc/testing.md) | The bundle's tests, its integration suite on MySQL and PostgreSQL, and its mutation bar. |
| [Reference](doc/reference.md) | Every type of the bundle, with its role. |

## Quick start

```sh
composer require phil/library-bundle
```

```yaml
# config/packages/phil_library.yaml
phil_library:
    timezone: America/Toronto
    loans: { days: 21, max_renewals: 2, max_open: 10 }

# config/routes/phil_library.yaml
phil_library:
    resource: '@PhilLibraryBundle/config/routes.php'
    prefix: /library
```

```php
use Phil\LibraryBundle\Application\Exception\LoanRefused;
use Phil\LibraryBundle\Application\Loans;

try {
    $loan = $loans->lend('G-0042', $member);   // due at the end of the 21st day
} catch (LoanRefused $refused) {
    $refused->reason;                          // taken, overdue, limit…
}

$loans->giveBack('G-0042', CopyCondition::Incomplete, 'Two tiles missing.');
```

## Requirements

PHP 8.5 or later, Symfony 8.2 or later, Doctrine ORM 3.7 and DBAL 4.5 or later, Twig and Symfony UX Twig Components.
`symfony/console`, `symfony/messenger`, `phil/privacy-bundle`, `phil/stockroom`, `phil/stockroom-bundle` and EasyAdmin
are optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
