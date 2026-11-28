<?php

declare(strict_types=1);

namespace Phil\BulkActions;

use DateTimeImmutable;
use Phil\BulkActions\Change\ChangeSet;
use Phil\BulkActions\Contract\Authorizer;
use Phil\BulkActions\Contract\BulkAction;
use Phil\BulkActions\Contract\SelectionStore;
use Phil\BulkActions\Exception\SelectionUnavailable;
use Phil\BulkActions\Internal\Guard;
use Phil\BulkActions\Preview\Preview;
use Phil\BulkActions\Preview\SampleItem;
use Phil\BulkActions\Preview\ValueChange;
use Phil\BulkActions\Selection\PendingSelection;
use Phil\BulkActions\Selection\Selection;
use Phil\BulkActions\Selection\SelectionKind;
use Phil\BulkActions\Selection\SelectionToken;
use Phil\BulkActions\Target\Limits;
use Phil\BulkActions\Target\Target;
use Phil\BulkActions\Target\Targets;

/**
 * The second step: what the confirmation shows, read without using the token, with every check made again. Nothing
 * changes here; a GET may show it.
 */
final readonly class BulkPreviewer
{
    private Guard $guard;

    public function __construct(
        Targets $targets,
        private Authorizer $authorizer,
        private SelectionStore $selections,
        Limits $limits = new Limits(),
    ) {
        $this->guard = new Guard($targets, $authorizer, $limits);
    }

    public function preview(string $actor, SelectionToken $token, ChangeSet $changes, DateTimeImmutable $now): Preview
    {
        $pending = $this->selections->peek($token->fingerprint());

        if (!$pending instanceof PendingSelection || !$pending->isUsableBy($actor, $now)) {
            throw SelectionUnavailable::create();
        }

        [$target, $action] = $this->guard->resolve($actor, $pending->target, $pending->action);
        $this->guard->checkChanges($action, $changes);
        $count = $this->guard->count($target, $pending->selection);

        return new Preview(
            $target->name,
            $action->name(),
            $count,
            $this->sample($actor, $target, $action, $pending->selection, $changes),
            $changes,
            $action->isDestructive(),
            $pending->expiresAt,
        );
    }

    /**
     * @return list<SampleItem>
     */
    private function sample(string $actor, Target $target, BulkAction $action, Selection $selection, ChangeSet $changes): array
    {
        $size = $this->guard->limits->sampleSize;

        if (0 === $size) {
            return [];
        }

        $identifiers = SelectionKind::Identifiers === $selection->kind
            ? \array_slice($selection->identifiers, 0, $size)
            : $target->items->matching($selection->filter ?? throw SelectionUnavailable::create(), $selection->excluded, null, $size);
        $items = $target->items->find($identifiers);
        $sample = [];

        foreach ($identifiers as $identifier) {
            $item = $items[$identifier] ?? null;

            if (null === $item || !$target->holds($item)) {
                continue;
            }

            $values = $action->values($item);
            $fieldChanges = [];

            foreach ($changes->values as $field => $new) {
                $fieldChanges[] = new ValueChange($field, $values[$field] ?? null, $new);
            }

            $sample[] = new SampleItem($identifier, $action->label($item), $this->authorizer->mayApply($actor, $action, $item), $fieldChanges);
        }

        return $sample;
    }
}
