# Execution with Messenger

## Messages

The confirmation dispatches the run through `Contract\RunDispatcher`. With Symfony Messenger the adapter sends an
`ExecuteBulkRun`, which carries the run's identifier and nothing else: no item, no change, no personal data waits in a
queue.

```php
use Phil\BulkActions\Contract\RunDispatcher;
use Phil\BulkActions\Messenger\ExecuteBulkRun;
use Phil\BulkActions\Run\RunId;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MessengerRunDispatcher implements RunDispatcher
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    public function dispatch(RunId $run): void
    {
        $this->bus->dispatch(new ExecuteBulkRun($run->value));
    }
}
```

`ExecuteBulkRunHandler` executes one chunk and dispatches the run again while it has more to do, so a run of 10,000
items is 100 short messages rather than one long one, and other work interleaves with it.

## A chunk

`BulkExecutor::execute()` reads the run and returns at once when it is unknown or over: a message delivered twice
changes nothing more. Then:

1. It asks the right to run the action again; a person who lost it stops the run, and the items left are denied with
   the reason `bulk_actions.reason.access_lost`. An action left out of the configuration stops the run too, its items
   failed with `bulk_actions.reason.unknown_action`.
2. For a filter, once the items read before are handled, it asks the repository whether it still accepts the filter,
   then reads the next identifiers after the cursor.
3. It loads the next `Limits::chunkSize` pending items and checks each: missing or of another class, refused by the
   voter, or at another version than the one the person saw. Those are recorded, not applied.
4. It applies the others in one transaction, saving the run inside it, so that with a run store in the same database
   the outcomes commit with the changes.
5. When the transaction fails, it is rolled back, and each item is read again and applied in a transaction of its own:
   `ItemRefused` gives a refused item with its reason, `VersionConflict` a conflict, any other throwable a failed item
   with the reason `bulk_actions.reason.error`.
6. It saves the run, ends it when nothing is pending and the filter has nothing more, and records
   `AuditEvent::Finished`.

## Effects outside the database

An action never sends a mail or calls a service from `apply()`: a rolled-back chunk would have sent it anyway. It
records the effect in an outbox, such as `phil/outbox`, in the same transaction, under `ItemContext::idempotencyKey()`.
The key is the same for every attempt on the item, so an item applied again after a lost connection records its effect
once.

```php
$this->outbox->record('member.segment_changed', ['member' => $context->item], idempotencyKey: $context->idempotencyKey());
```

## Transactions

`Contract\Transactions::run()` commits the work, or rolls it back and forgets what the ORM holds in memory before
throwing again: the items applied one by one after a failed chunk are read anew.

```php
use Closure;
use Doctrine\Persistence\ManagerRegistry;
use Phil\BulkActions\Contract\Transactions;
use Throwable;

final readonly class DoctrineTransactions implements Transactions
{
    public function __construct(private ManagerRegistry $doctrine)
    {
    }

    public function run(Closure $work): void
    {
        $manager = $this->doctrine->getManager();

        try {
            $manager->wrapInTransaction(static function () use ($work): void {
                $work();
            });
        } catch (Throwable $throwable) {
            $this->doctrine->resetManager();

            throw $throwable;
        }
    }
}
```

An ORM's optimistic lock exception at the flush fails the chunk; the item applied alone then fails again, and an
action that catches it and throws `VersionConflict` reports a conflict instead of a failure.

## Reports

```php
$report = $runStore->get(RunId::fromString($id))?->report();

$report->status;                         // RunStatus::Pending, Running, Finished or Stopped
$report->counts();                       // ['pending' => 0, 'succeeded' => 9_812, 'denied' => 3, ...]
$report->with(ItemOutcome::Failed);      // list<ItemResult>, each with its item and reason
$report->truncated;                      // whether a filter grew past Limits::maxItems
```

`ItemOutcome::translationKey()` gives `bulk_actions.outcome.succeeded` and the like; the reasons are translation keys
too, those of the library or those an action gave to `ItemRefused`.

## Retries

```php
$retrier->retry($user, RunId::fromString($id));                 // the failed items
$retrier->retry($user, RunId::fromString($id), conflicts: true); // and the conflicts
```

A retry needs a run that is over and at least one failed item, or conflict when asked; it throws `NothingToRetry`
otherwise. The right is asked of the person retrying, who runs the items from then on. A retry's attempt applies to the
items as they are now, without the versions of the selection. Succeeded, denied, refused and missing items are left as
they are. `AuditEvent::Retried` is recorded and the run dispatched again.
