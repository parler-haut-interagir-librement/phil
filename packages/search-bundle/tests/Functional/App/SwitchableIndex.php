<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Functional\App;

use Override;
use Phil\SearchBundle\Application\SearchIndex;
use Phil\SearchBundle\Domain\ValueObject\Document;
use Phil\SearchBundle\Domain\ValueObject\SearchQuery;
use Phil\SearchBundle\Domain\ValueObject\SearchResult;
use RuntimeException;

/**
 * The index of the application, which a test takes down: every write then fails, as an engine out of reach does.
 */
final class SwitchableIndex implements SearchIndex
{
    public bool $down = false;

    public function __construct(
        private readonly SearchIndex $inner,
    ) {
    }

    #[Override]
    public function index(Document ...$documents): void
    {
        $this->check();
        $this->inner->index(...$documents);
    }

    #[Override]
    public function remove(string $index, array $ids): void
    {
        $this->check();
        $this->inner->remove($index, $ids);
    }

    #[Override]
    public function clear(string $index): void
    {
        $this->check();
        $this->inner->clear($index);
    }

    #[Override]
    public function rebuild(string $index, iterable $batches): void
    {
        $this->check();
        $this->inner->rebuild($index, $batches);
    }

    #[Override]
    public function search(string $index, SearchQuery $query): SearchResult
    {
        return $this->inner->search($index, $query);
    }

    private function check(): void
    {
        if ($this->down) {
            throw new RuntimeException('The search engine is out of reach.');
        }
    }
}
