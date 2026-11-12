<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Fixture;

use Phil\ContentBundle\Application\Persistence\ContentTransaction;

/**
 * Flushes the in-memory repositories when asked, and at the end of each operation, as the Doctrine adapter
 * flushes the entity manager.
 */
final class InMemoryContentTransaction implements ContentTransaction
{
    public int $runs = 0;

    public function __construct(
        private readonly InMemoryDimensionContentRepository $rows,
        private readonly InMemoryContentRootRepository $roots,
    ) {
    }

    public function run(callable $operation): mixed
    {
        ++$this->runs;
        $result = $operation();
        $this->flush();

        return $result;
    }

    public function flush(): void
    {
        $this->roots->flush();
        $this->rows->flush();
    }
}
