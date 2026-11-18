<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Tests\Fixtures;

use Override;
use Phil\AccessBundle\Application\Contract\TuplePartitions;

/**
 * The partitions a test names, in its order.
 */
final readonly class ListedPartitions implements TuplePartitions
{
    /**
     * @param list<string> $partitions
     */
    public function __construct(
        private array $partitions,
    ) {
    }

    #[Override]
    public function partitions(): array
    {
        return $this->partitions;
    }
}
