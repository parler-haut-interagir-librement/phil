<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions;

use Override;
use Phil\ResourcePermissions\Contract\TupleScope;
use Phil\ResourcePermissions\Exception\InvalidRelation;

/**
 * Every tuple in one named partition: a store built for one tenant — a test, an import, a job that runs for one
 * tenant at a time.
 */
final readonly class FixedPartition implements TupleScope
{
    private string $partition;

    /**
     * @throws InvalidRelation when the name breaks the rule of `Partition`
     */
    public function __construct(string $partition)
    {
        $this->partition = Partition::check($partition);
    }

    #[Override]
    public function partition(): string
    {
        return $this->partition;
    }
}
