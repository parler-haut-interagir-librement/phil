<?php

declare(strict_types=1);

namespace Phil\BulkActions\Bridge;

use Phil\BulkActions\Exception\InvalidInput;
use Phil\BulkActions\Selection\Selection;
use Phil\BulkActions\Target\Targets;

/**
 * Builds a selection from what another tool hands over: the rows of a data table's bulk action, or the identifiers and
 * entity class of an administration's batch action. Neither tool is a dependency.
 */
final readonly class ExternalSelection
{
    public function __construct(private Targets $targets)
    {
    }

    /**
     * The rows a data table's action receives, read again from its data source: each must be of the target's class,
     * and keeps the version it has now when the target keeps one.
     *
     * @param iterable<mixed> $rows
     */
    public function fromRows(string $target, iterable $rows): Selection
    {
        $resolved = $this->targets->get($target);
        $identifiers = [];
        $versions = [];

        foreach ($rows as $row) {
            if (!\is_object($row) || !$resolved->holds($row)) {
                throw InvalidInput::because('row', 'must be an item of target "'.$resolved->name.'"');
            }

            $identifier = $resolved->items->identify($row);
            $identifiers[] = $identifier;
            $version = $resolved->items->version($row);

            if (null !== $version) {
                $versions[$identifier] = $version;
            }
        }

        return Selection::identifiers($identifiers, $versions);
    }

    /**
     * The identifiers of a batch action and the entity class it names: the class is compared with the target's, never
     * used to find the items.
     *
     * @param list<int|string> $identifiers
     */
    public function fromBatch(string $target, string $entityClass, array $identifiers): Selection
    {
        $this->targets->forClass($target, $entityClass);

        return Selection::identifiers($identifiers);
    }
}
