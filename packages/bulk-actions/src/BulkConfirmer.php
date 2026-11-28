<?php

declare(strict_types=1);

namespace Phil\BulkActions;

use DateTimeImmutable;
use Phil\BulkActions\Change\ChangeSet;
use Phil\BulkActions\Contract\AuditTrail;
use Phil\BulkActions\Contract\Authorizer;
use Phil\BulkActions\Contract\RunDispatcher;
use Phil\BulkActions\Contract\RunStore;
use Phil\BulkActions\Contract\SelectionStore;
use Phil\BulkActions\Exception\SelectionUnavailable;
use Phil\BulkActions\Internal\Guard;
use Phil\BulkActions\Run\AuditEvent;
use Phil\BulkActions\Run\BulkRun;
use Phil\BulkActions\Run\RunId;
use Phil\BulkActions\Selection\PendingSelection;
use Phil\BulkActions\Selection\Selection;
use Phil\BulkActions\Selection\SelectionToken;
use Phil\BulkActions\Target\Limits;
use Phil\BulkActions\Target\Targets;

/**
 * The third step: uses the token once, checks everything again, files the run and asks for its first chunk.
 */
final readonly class BulkConfirmer
{
    private Guard $guard;

    public function __construct(
        Targets $targets,
        Authorizer $authorizer,
        private SelectionStore $selections,
        private RunStore $runs,
        private RunDispatcher $dispatcher,
        Limits $limits = new Limits(),
        private ?AuditTrail $audit = null,
    ) {
        $this->guard = new Guard($targets, $authorizer, $limits);
    }

    public function confirm(string $actor, SelectionToken $token, ChangeSet $changes, DateTimeImmutable $now): BulkRun
    {
        $fingerprint = $token->fingerprint();
        $peeked = $this->selections->peek($fingerprint);

        // Read first, so that another person's attempt does not use up the owner's token.
        if (!$peeked instanceof PendingSelection || !$peeked->isUsableBy($actor, $now)) {
            throw SelectionUnavailable::create();
        }

        $pending = $this->selections->take($fingerprint);

        if (!$pending instanceof PendingSelection || !$pending->isUsableBy($actor, $now)) {
            throw SelectionUnavailable::create();
        }

        return $this->start($actor, $pending->target, $pending->action, $pending->selection, $changes, $now);
    }

    /**
     * Starts a run without a token, for a caller that showed its own confirmation, such as a data table's dialog. Every
     * check of a confirmation is made.
     */
    public function start(string $actor, string $target, string $action, Selection $selection, ChangeSet $changes, DateTimeImmutable $now): BulkRun
    {
        [$resolved, $bulkAction] = $this->guard->resolve($actor, $target, $action);
        $this->guard->checkChanges($bulkAction, $changes);
        $this->guard->count($resolved, $selection);

        $run = BulkRun::confirm(RunId::generate(), $resolved->name, $bulkAction->name(), $actor, $changes, $selection, $now);
        $this->runs->save($run);
        $this->audit?->record(AuditEvent::Confirmed, $run);
        $this->dispatcher->dispatch($run->id);

        return $run;
    }
}
