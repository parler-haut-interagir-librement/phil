# Reference

Every public type of the package, with its role.

## Services

| Type | Role |
|---|---|
| `BulkSelector` | Files a selection after checking the target, the action, the right and the limits; gives back its token. |
| `BulkPreviewer` | Gives the `Preview` of a selection and a change, without using the token. |
| `BulkConfirmer` | Uses the token once, checks everything again, files the run and dispatches it; `start()` does it without a token. |
| `BulkExecutor` | Executes one chunk of a run; says whether the run has more to do. |
| `BulkRetrier` | Makes the failed items of a finished run pending again, and the conflicts when asked, and dispatches the run. |
| `Messenger\ExecuteBulkRun` | The message of a chunk: the run's identifier only. |
| `Messenger\ExecuteBulkRunHandler` | Executes a chunk and dispatches the next while the run has more to do. |
| `Bridge\ExternalSelection` | Builds a selection from a data table's rows or a batch action's identifiers and class. |

## Configuration

| Type | Role |
|---|---|
| `Target\Target` | A kind of item: its name, class, repository and actions. |
| `Target\Targets` | The targets by name; `forClass()` checks a class an outside tool names. |
| `Target\Limits` | The bounds of identifiers, items, chunks, samples and lifetimes. |

## Selection and change

| Type | Role |
|---|---|
| `Selection\Selection` | Identifiers with their versions, or a filter less its unticked items. |
| `Selection\SelectionKind` | `Identifiers` or `Filter`. |
| `Selection\FilterCriteria` | A filter's name and parameters, bounded and sorted. |
| `Selection\SelectionToken` | The secret a page carries; `fingerprint()` is its SHA-256. |
| `Selection\PendingSelection` | A selection waiting for its confirmation: whose, for which action, until when. |
| `Change\ChangeSet` | The fields a bulk edit sets; `fromSubmission()` reads one box per field. |

## Preview

| Type | Role |
|---|---|
| `Preview\Preview` | The count, the sample, the change, the destructive flag and the expiry. |
| `Preview\SampleItem` | A sampled item: its label, whether the voter grants it, and its value changes. |
| `Preview\ValueChange` | A field's old and new value; `changes()` tells whether it differs. |

## Runs

| Type | Role |
|---|---|
| `Run\BulkRun` | A confirmed action and the outcome of each item, stored between chunks through `toArray()` and `fromArray()`. |
| `Run\RunId` | 16 random bytes in hexadecimal. |
| `Run\RunStatus` | `Pending`, `Running`, `Finished` or `Stopped`. |
| `Run\ItemOutcome` | `Pending`, `Succeeded`, `Denied`, `Conflict`, `Refused`, `Failed` or `Missing`, with its translation key. |
| `Run\ItemResult` | An item, its outcome and the translation key of its reason. |
| `Run\Report` | The results of a run, counted by outcome, and how many a retry would handle. |
| `Run\ItemContext` | What an action knows while it changes an item, with the item's idempotency key. |
| `Run\AuditEvent` | `Confirmed`, `Finished`, `Stopped` or `Retried`. |

## Ports and memory adapters

| Type | Role |
|---|---|
| `Contract\BulkAction` | One action of a target, written by the application. |
| `Contract\ItemRepository` | Reads a target's items by identifiers and by filters. |
| `Contract\Authorizer` | Says whether a person may run an action and apply it to an item. |
| `Contract\SelectionStore` | Keeps the selections waiting; `take()` is atomic. |
| `Contract\RunStore` | Keeps the runs between chunks. |
| `Contract\Transactions` | Runs a chunk in one transaction. |
| `Contract\RunDispatcher` | Asks for the next chunk of a run. |
| `Contract\AuditTrail` | Records the events of a run. |
| `Memory\InMemorySelectionStore` | A selection store in an array; `forgetExpired()` drops the expired selections. |
| `Memory\InMemoryRunStore` | A run store in an array. |

## Exceptions

Every exception implements `Exception\BulkActionsException`.

| Type | Thrown when |
|---|---|
| `Exception\InvalidInput` | an identifier, a filter, a change, a setting or a stored row is out of bounds |
| `Exception\UnknownAction` | the configuration declares no such target or action |
| `Exception\AccessDenied` | the person may not run the action |
| `Exception\SelectionUnavailable` | a token is unknown, used, expired, another person's or malformed |
| `Exception\RunNotFound` | a retry names a run the store does not know |
| `Exception\NothingToRetry` | a retry finds the run still running, or nothing to retry |
| `Exception\ItemRefused` | an action will not change one item; carries the reason's translation key |
| `Exception\VersionConflict` | an action finds the item changed since it was read |

## Translation keys

| Key | Meaning |
|---|---|
| `bulk_actions.outcome.<outcome>` | the outcome of an item, from `ItemOutcome::translationKey()` |
| `bulk_actions.reason.access_lost` | the person lost the right to run the action while the run was executing |
| `bulk_actions.reason.unknown_action` | the action left the configuration while the run was executing |
| `bulk_actions.reason.filter_refused` | the repository no longer accepts the filter |
| `bulk_actions.reason.error` | the action stopped with an error, rolled back |
