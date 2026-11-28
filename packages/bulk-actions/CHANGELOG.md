# Changelog

All notable changes to `phil/bulk-actions` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `Selection`, `SelectionKind` and `FilterCriteria`: a bounded list of identifiers with the version each item had on
  the page, or every item of a filter the repository declares, less the unticked items, read again when the run
  executes.
- `SelectionToken` and `PendingSelection`: a selection filed on the server under the SHA-256 fingerprint of a token of
  32 random bytes, for one person, once, until it expires; every failure is the same `SelectionUnavailable`.
- `ChangeSet`: the fields a bulk edit sets, read from one box per field, so that a field emptied is told apart from a
  field left alone.
- `Target`, `Targets` and `Limits`: targets declared by the configuration, whose class is never read from a request,
  and the bounds of identifiers, items, chunks, samples and lifetimes.
- `BulkSelector`, `BulkPreviewer` and `BulkConfirmer`: the selection, a preview with the count, a sample of old and new
  values, the items the voter refuses and the destructive warning, then a confirmation that uses the token atomically
  and asks the access rules again; `BulkConfirmer::start()` for a caller that showed its own confirmation.
- `BulkExecutor`, `ExecuteBulkRun` and `ExecuteBulkRunHandler`: chunks on a bus whose messages carry the run's
  identifier only, the right asked again, each item's class, voter and version checked, one transaction per chunk with
  the run saved inside it, and each item applied in a transaction of its own when the chunk fails.
- `BulkRun`, `RunId`, `RunStatus`, `ItemOutcome`, `ItemResult` and `Report`: the outcome and reason of each item —
  succeeded, denied, conflict, refused, failed, missing — stored between chunks as JSON-ready arrays, and a run
  stopped when the person loses the right or the action leaves the configuration.
- `ItemContext`: the item's idempotency key, the same for every attempt, for effects recorded in an outbox.
- `BulkRetrier`: the failed items of a finished run run again, and the conflicts when asked, by the person retrying.
- `ExternalSelection`: selections from a data table's rows, with their versions, and from an administration's batch
  action, whose class is compared with the target's.
- The ports `BulkAction`, `ItemRepository`, `Authorizer`, `SelectionStore`, `RunStore`, `Transactions`,
  `RunDispatcher` and `AuditTrail`, and the memory stores `InMemorySelectionStore` and `InMemoryRunStore`.
- The documentation in `doc/`: installation, configuration, selection, preview and confirmation, execution with
  Messenger, security, bridges, extension points, testing and reference.
- The unit tests, a functional test of the whole flow on 1,200 items, and a benchmark of the executor.
- The package skeleton, `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
