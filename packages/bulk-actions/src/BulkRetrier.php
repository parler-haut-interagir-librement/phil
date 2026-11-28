<?php

declare(strict_types=1);

namespace Phil\BulkActions;

use Phil\BulkActions\Contract\AuditTrail;
use Phil\BulkActions\Contract\Authorizer;
use Phil\BulkActions\Contract\RunDispatcher;
use Phil\BulkActions\Contract\RunStore;
use Phil\BulkActions\Exception\RunNotFound;
use Phil\BulkActions\Internal\Guard;
use Phil\BulkActions\Run\AuditEvent;
use Phil\BulkActions\Run\BulkRun;
use Phil\BulkActions\Run\RunId;
use Phil\BulkActions\Target\Limits;
use Phil\BulkActions\Target\Targets;

/**
 * Runs the failed items of a finished run again, and its conflicts when asked; the items that succeeded, or that a voter
 * or the action refused, are left as they are.
 */
final readonly class BulkRetrier
{
    private Guard $guard;

    public function __construct(
        Targets $targets,
        Authorizer $authorizer,
        private RunStore $runs,
        private RunDispatcher $dispatcher,
        Limits $limits = new Limits(),
        private ?AuditTrail $audit = null,
    ) {
        $this->guard = new Guard($targets, $authorizer, $limits);
    }

    /**
     * @param string $actor     the person retrying, who runs the items from now on
     * @param bool   $conflicts whether the items changed by someone else are applied again, to their version of now
     */
    public function retry(string $actor, RunId $id, bool $conflicts = false): BulkRun
    {
        $run = $this->runs->get($id) ?? throw RunNotFound::withId($id->value);
        $this->guard->resolve($actor, $run->target, $run->action);
        $run->retry($actor, $conflicts);
        $this->runs->save($run);
        $this->audit?->record(AuditEvent::Retried, $run);
        $this->dispatcher->dispatch($run->id);

        return $run;
    }
}
