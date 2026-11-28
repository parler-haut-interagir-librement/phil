<?php

declare(strict_types=1);

namespace Phil\BulkActions;

use DateTimeImmutable;
use Phil\BulkActions\Contract\Authorizer;
use Phil\BulkActions\Contract\SelectionStore;
use Phil\BulkActions\Internal\Guard;
use Phil\BulkActions\Selection\PendingSelection;
use Phil\BulkActions\Selection\Selection;
use Phil\BulkActions\Selection\SelectionToken;
use Phil\BulkActions\Target\Limits;
use Phil\BulkActions\Target\Targets;

/**
 * The first step: files a selection on the server and gives back the token the page carries instead of the items.
 */
final readonly class BulkSelector
{
    private Guard $guard;

    public function __construct(
        Targets $targets,
        Authorizer $authorizer,
        private SelectionStore $selections,
        Limits $limits = new Limits(),
    ) {
        $this->guard = new Guard($targets, $authorizer, $limits);
    }

    /**
     * @return SelectionToken single-use, valid for the lifetime of the limits, for this person only
     */
    public function select(string $actor, string $target, string $action, Selection $selection, DateTimeImmutable $now): SelectionToken
    {
        [$resolved, $bulkAction] = $this->guard->resolve($actor, $target, $action);
        $this->guard->count($resolved, $selection);
        $token = SelectionToken::generate();

        $this->selections->save(new PendingSelection(
            $token->fingerprint(),
            $actor,
            $resolved->name,
            $bulkAction->name(),
            $selection,
            $now->modify(\sprintf('+%d seconds', $this->guard->limits->lifetimeSeconds)),
        ));

        return $token;
    }
}
