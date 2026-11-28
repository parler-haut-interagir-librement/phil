<?php

declare(strict_types=1);

namespace Phil\BulkActions\Internal;

use Phil\BulkActions\Change\ChangeSet;
use Phil\BulkActions\Contract\Authorizer;
use Phil\BulkActions\Contract\BulkAction;
use Phil\BulkActions\Exception\AccessDenied;
use Phil\BulkActions\Exception\InvalidInput;
use Phil\BulkActions\Selection\Selection;
use Phil\BulkActions\Selection\SelectionKind;
use Phil\BulkActions\Target\Limits;
use Phil\BulkActions\Target\Target;
use Phil\BulkActions\Target\Targets;

/**
 * @internal the checks every step makes again: the target and the action declared, the right to run it, the selection
 *           within the limits and the change within the action's fields
 */
final readonly class Guard
{
    public function __construct(
        private Targets $targets,
        private Authorizer $authorizer,
        public Limits $limits,
    ) {
    }

    /**
     * @return array{Target, BulkAction}
     */
    public function resolve(string $actor, string $target, string $action): array
    {
        $resolved = $this->targets->get($target);
        $bulkAction = $resolved->action($action);

        if (!$this->authorizer->mayRun($actor, $resolved->name, $bulkAction)) {
            throw AccessDenied::toRun($resolved->name, $bulkAction->name());
        }

        return [$resolved, $bulkAction];
    }

    /**
     * Checks the selection against the limits and the repository, and gives the number of items it names now.
     */
    public function count(Target $target, Selection $selection): int
    {
        if (\count($selection->excluded) > $this->limits->maxIdentifiers) {
            throw InvalidInput::because('selection', \sprintf('must untick %d items at most', $this->limits->maxIdentifiers));
        }

        if (SelectionKind::Identifiers === $selection->kind) {
            $count = \count($selection->identifiers);

            if ($count > $this->limits->maxIdentifiers) {
                throw InvalidInput::because('selection', \sprintf('must name %d items at most; select by filter instead', $this->limits->maxIdentifiers));
            }

            return $count;
        }

        $filter = $selection->filter ?? throw InvalidInput::because('selection', 'must have its filter');

        if (!$target->items->supports($filter)) {
            throw InvalidInput::because('filter "'.$filter->name.'"', 'must be one the target "'.$target->name.'" supports');
        }

        $count = $target->items->count($filter, $selection->excluded);

        if ($count > $this->limits->maxItems) {
            throw InvalidInput::because('selection', \sprintf('must match %d items at most; narrow the filter', $this->limits->maxItems));
        }

        return $count;
    }

    public function checkChanges(BulkAction $action, ChangeSet $changes): void
    {
        $fields = $action->fields();
        $unknown = array_diff($changes->fields(), $fields);

        if ([] !== $unknown) {
            throw InvalidInput::because('field "'.reset($unknown).'"', 'must be one the action "'.$action->name().'" sets');
        }

        if ([] !== $fields && $changes->isEmpty()) {
            throw InvalidInput::because('change', 'must set at least one field');
        }
    }
}
