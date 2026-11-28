<?php

declare(strict_types=1);

namespace Phil\BulkActions;

use DateTimeImmutable;
use Phil\BulkActions\Contract\AuditTrail;
use Phil\BulkActions\Contract\Authorizer;
use Phil\BulkActions\Contract\BulkAction;
use Phil\BulkActions\Contract\RunStore;
use Phil\BulkActions\Contract\Transactions;
use Phil\BulkActions\Exception\AccessDenied;
use Phil\BulkActions\Exception\ItemRefused;
use Phil\BulkActions\Exception\UnknownAction;
use Phil\BulkActions\Exception\VersionConflict;
use Phil\BulkActions\Internal\Guard;
use Phil\BulkActions\Run\AuditEvent;
use Phil\BulkActions\Run\BulkRun;
use Phil\BulkActions\Run\ItemContext;
use Phil\BulkActions\Run\ItemOutcome;
use Phil\BulkActions\Run\RunId;
use Phil\BulkActions\Selection\FilterCriteria;
use Phil\BulkActions\Target\Limits;
use Phil\BulkActions\Target\Target;
use Phil\BulkActions\Target\Targets;
use Throwable;

/**
 * Executes one chunk of a run, as a worker does on each message. The right to run the action, the item's class, its
 * voter and its version are checked again; the chunk is one transaction, and when it fails each item is applied again
 * in a transaction of its own, so that one bad item never takes the others with it.
 */
final readonly class BulkExecutor
{
    /** The reason of the items left when the action is no longer granted to the person. */
    public const string REASON_ACCESS_LOST = 'bulk_actions.reason.access_lost';

    /** The reason of the items left when the configuration no longer declares the target or the action. */
    public const string REASON_UNKNOWN_ACTION = 'bulk_actions.reason.unknown_action';

    /** The reason of the items left when the repository no longer accepts the filter. */
    public const string REASON_FILTER_REFUSED = 'bulk_actions.reason.filter_refused';

    /** The reason of an item the action stopped with an error. */
    public const string REASON_ERROR = 'bulk_actions.reason.error';

    private Guard $guard;

    public function __construct(
        Targets $targets,
        private Authorizer $authorizer,
        private RunStore $runs,
        private Transactions $transactions,
        Limits $limits = new Limits(),
        private ?AuditTrail $audit = null,
    ) {
        $this->guard = new Guard($targets, $authorizer, $limits);
    }

    /**
     * @return bool whether the run has more to do: ask for another chunk
     */
    public function execute(RunId $id, DateTimeImmutable $now): bool
    {
        $run = $this->runs->get($id);

        if (!$run instanceof BulkRun || $run->status->isOver()) {
            return false;
        }

        try {
            [$target, $action] = $this->guard->resolve($run->actor, $run->target, $run->action);
        } catch (UnknownAction) {
            return $this->stop($run, ItemOutcome::Failed, self::REASON_UNKNOWN_ACTION, $now);
        } catch (AccessDenied) {
            return $this->stop($run, ItemOutcome::Denied, self::REASON_ACCESS_LOST, $now);
        }

        if (!$this->discover($run, $target)) {
            return $this->stop($run, ItemOutcome::Failed, self::REASON_FILTER_REFUSED, $now);
        }

        $eligible = $this->screen($run, $target, $action, $run->pending($this->guard->limits->chunkSize));

        if ([] !== $eligible && !$this->applyChunk($run, $action, $eligible)) {
            foreach (array_keys($eligible) as $identifier) {
                $this->applyOne($run, $target, $action, (string) $identifier);
            }
        }

        $over = $run->finishIfDone($now);
        $this->runs->save($run);

        if ($over) {
            $this->audit?->record(AuditEvent::Finished, $run);
        }

        return !$over;
    }

    /**
     * Reads the next identifiers of a filter, while it has more.
     *
     * @return bool false when the repository no longer accepts the filter
     */
    private function discover(BulkRun $run, Target $target): bool
    {
        $filter = $run->nextFilter();

        if (!$filter instanceof FilterCriteria) {
            return true;
        }

        if (!$target->items->supports($filter)) {
            return false;
        }

        $chunk = $this->guard->limits->chunkSize;
        $found = $target->items->matching($filter, $run->selection->excluded, $run->cursor, $chunk);
        $run->discover($found, \count($found) < $chunk, $this->guard->limits->maxItems);

        return true;
    }

    /**
     * Records the items that cannot be applied — gone, of another class, refused by the voter, changed since they were
     * seen — and gives back the others.
     *
     * @param list<string> $identifiers
     *
     * @return array<array-key, object>
     */
    private function screen(BulkRun $run, Target $target, BulkAction $action, array $identifiers): array
    {
        $found = [] === $identifiers ? [] : $target->items->find($identifiers);
        $eligible = [];

        foreach ($identifiers as $identifier) {
            $item = $found[$identifier] ?? null;
            $outcome = $this->check($run, $target, $action, $identifier, $item);

            if (!$outcome instanceof ItemOutcome) {
                $eligible[$identifier] = $item;
            } else {
                $run->record($identifier, $outcome);
            }
        }

        return $eligible;
    }

    /**
     * @phpstan-assert-if-false !null $item
     */
    private function check(BulkRun $run, Target $target, BulkAction $action, string $identifier, ?object $item): ?ItemOutcome
    {
        if (null === $item || !$target->holds($item)) {
            return ItemOutcome::Missing;
        }

        if (!$this->authorizer->mayApply($run->actor, $action, $item)) {
            return ItemOutcome::Denied;
        }

        $expected = $run->expectedVersion($identifier);

        if (null !== $expected && $target->items->version($item) !== $expected) {
            return ItemOutcome::Conflict;
        }

        return null;
    }

    /**
     * @param array<array-key, object> $eligible
     *
     * @return bool false when the chunk was rolled back
     */
    private function applyChunk(BulkRun $run, BulkAction $action, array $eligible): bool
    {
        try {
            $this->transactions->run(function () use ($run, $action, $eligible): void {
                foreach ($eligible as $identifier => $item) {
                    $action->apply($item, $run->changes, new ItemContext($run->id, (string) $identifier, $run->actor, $run->attempt));
                }

                foreach (array_keys($eligible) as $identifier) {
                    $run->record((string) $identifier, ItemOutcome::Succeeded);
                }

                $this->runs->save($run);
            });
        } catch (Throwable) {
            // Each item is recorded again by applyOne(), whatever the closure recorded before the rollback.
            return false;
        }

        return true;
    }

    /**
     * Applies one item of a rolled-back chunk again, read anew, in a transaction of its own.
     */
    private function applyOne(BulkRun $run, Target $target, BulkAction $action, string $identifier): void
    {
        $item = $target->items->find([$identifier])[$identifier] ?? null;
        $outcome = $this->check($run, $target, $action, $identifier, $item);

        if ($outcome instanceof ItemOutcome) {
            $run->record($identifier, $outcome);

            return;
        }

        try {
            $this->transactions->run(function () use ($run, $action, $identifier, $item): void {
                $action->apply($item, $run->changes, new ItemContext($run->id, $identifier, $run->actor, $run->attempt));
                $run->record($identifier, ItemOutcome::Succeeded);
                $this->runs->save($run);
            });
        } catch (ItemRefused $refused) {
            $run->record($identifier, ItemOutcome::Refused, $refused->reason);
        } catch (VersionConflict) {
            $run->record($identifier, ItemOutcome::Conflict);
        } catch (Throwable) {
            $run->record($identifier, ItemOutcome::Failed, self::REASON_ERROR);
        }
    }

    private function stop(BulkRun $run, ItemOutcome $outcome, string $reason, DateTimeImmutable $now): bool
    {
        $run->stop($outcome, $reason, $now);
        $this->runs->save($run);
        $this->audit?->record(AuditEvent::Stopped, $run);

        return false;
    }
}
