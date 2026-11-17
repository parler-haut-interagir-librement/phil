<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Fixture;

use Phil\ContentBundle\Application\Exception\WriteConflict;
use Phil\ContentBundle\Application\Persistence\ContentTransaction;
use RuntimeException;

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
        private readonly ?InMemoryRouteRepository $routes = null,
    ) {
    }

    public function run(callable $operation): mixed
    {
        ++$this->runs;
        $result = $operation();
        $this->flush();

        return $result;
    }

    private ?int $conflictAt = null;

    private int $flushes = 0;

    /**
     * Refuses the `$nth` flush from now as a conflicting write, as a database that finds a row taken does.
     */
    public function conflictOnFlush(int $nth): void
    {
        $this->conflictAt = $nth;
        $this->flushes = 0;
    }

    public function flush(): void
    {
        if (null !== $this->conflictAt && ++$this->flushes === $this->conflictAt) {
            throw WriteConflict::onFlush(new RuntimeException('The database refused the rows.'));
        }

        $this->roots->flush();
        $this->rows->flush();
        $this->routes?->flush();
    }
}
