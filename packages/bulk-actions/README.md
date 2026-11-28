# phil/bulk-actions

**Change thousands of records safely from an admin list: server-side selection, per-field edits, preview, per-item
voters and versions, chunked runs, retries.**

phil/bulk-actions is a plain PHP library. It gives an administration's list page the four steps of a bulk action —
select, preview, confirm, execute — and checks everything again at each of them.

**Selection on the server.** A page sends a bounded list of identifiers, or "every item of this filter" less the rows
the person unticked. `BulkSelector` files it on the server and gives back a **single-use token that expires**; the page
carries the token, never 50,000 identifiers. A filter is a name the target's repository declares, with its parameters,
and the repository reads it again when the run executes.

**The class comes from the configuration.** A request names a target, such as `products`; the `Target` declared by the
application says which class its items are, which repository reads them and which actions apply. Every item read is
checked against that class.

**One box per field.** `ChangeSet::fromSubmission()` reads a bulk edit form where each field has a box: an unticked
field is unchanged, while a ticked field emptied sets `null`, `false`, `""` or `0`.

**Preview, then confirmation.** `BulkPreviewer` gives the number of items, a sample with each field's old and new value,
which sampled items the voter refuses, and whether the action is destructive — without using the token. `BulkConfirmer`
uses the token once, asks the access rules again and files a `BulkRun`.

**Execution in chunks.** A worker handles `ExecuteBulkRun` messages, which carry the run's identifier and nothing else.
`BulkExecutor` asks the right to run the action again, then checks each item's class, its **voter** and its **version**
when the person saw one, and applies the chunk in **one transaction**. When the chunk fails, each item is applied again
in a transaction of its own, so that one bad item never takes the others with it. Effects outside the database go
through an outbox under each item's idempotency key.

**A report per item.** Each item ends succeeded, denied by its voter, in conflict with a newer version, refused by the
action with its reason, failed, or missing. `BulkRetrier` runs the failed items again, and the conflicts when asked;
nothing else is touched twice.

The library reads and writes only through **ports** — the items, the access rules, the stores, the transactions, the
dispatcher and the audit trail — and keeps no state between calls, so its services are safe in a long-running worker.

## Status

| Part | State |
|---|---|
| `Selection`, `FilterCriteria`, `SelectionToken`, `BulkSelector`: selection on the server under a single-use token | Implemented and tested |
| `ChangeSet`: one box per field | Implemented and tested |
| `BulkPreviewer`, `Preview`: count, sample, old and new values, destructive warning | Implemented and tested |
| `BulkConfirmer`: token used once, access asked again, run filed and dispatched | Implemented and tested |
| `BulkExecutor`, `ExecuteBulkRunHandler`: chunks, transactions, voters, versions, per-item fallback | Implemented and tested |
| `BulkRun`, `Report`, `BulkRetrier`: outcome per item, retry of the failures | Implemented and tested |
| `ExternalSelection`: selections from a data table's rows or an administration's batch action | Implemented and tested |
| `InMemorySelectionStore`, `InMemoryRunStore` | Implemented and tested |
| Adapters to a database, a bus, the access rules and the pages | Written by the application |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, and where each service fits. |
| [Configuration](doc/configuration.md) | Targets, actions and limits. |
| [Selection](doc/selection.md) | Identifiers, filters, unticked rows, tokens, and the per-field boxes. |
| [Preview and confirmation](doc/confirmation.md) | The preview, the native dialog, the confirmation and the requests a page sends. |
| [Execution with Messenger](doc/messenger.md) | Chunks, transactions, the per-item fallback, effects through an outbox, reports and retries. |
| [Security](doc/security.md) | What is checked at each step, and why a bulk action bypasses no access rule. |
| [Bridges](doc/bridges.md) | Data tables, administration batch actions, the trash and the audit trail. |
| [Extension points](doc/extension-points.md) | The ports an application implements, with adapters. |
| [Testing](doc/testing.md) | The tests, the functional flow and the benchmark. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/bulk-actions
```

```php
use Phil\BulkActions\Change\ChangeSet;
use Phil\BulkActions\Selection\FilterCriteria;
use Phil\BulkActions\Selection\Selection;
use Phil\BulkActions\Selection\SelectionToken;

// The list page: every draft product but one; the page keeps only the token.
$token = $selector->select($user, 'products', 'edit', Selection::filter(FilterCriteria::of('status', ['status' => 'draft']), ['p42']), new DateTimeImmutable());

// The form: the price box ticked, the field emptied, the status left alone.
$changes = ChangeSet::fromSubmission($request->request->all('values'), $request->request->all('apply'));
$preview = $previewer->preview($user, SelectionToken::fromString($tokenFromThePage), $changes, new DateTimeImmutable());

// The confirmation, a POST: the run is filed and its first chunk dispatched to a worker.
$run = $confirmer->confirm($user, SelectionToken::fromString($tokenFromThePage), $changes, new DateTimeImmutable());
```

## Requirements

PHP 8.5 or later, with `ext-sodium`. No other package: Symfony Messenger, an outbox and an audit trail are reached
through ports.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
