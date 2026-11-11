<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Tests\Unit\Fixture;

use Override;
use Phil\BlockEditorBundle\Application\Reference\ReferenceSearch;
use Phil\BlockEditorBundle\Application\Reference\Suggestion;

/**
 * Keeps the queries it receives, and finds a number of people named after the query.
 */
final class RecordingSearch implements ReferenceSearch
{
    /** @var list<array{string, int}> */
    public array $queries = [];

    public function __construct(
        private readonly int $count = 3,
    ) {
    }

    #[Override]
    public function search(string $query, int $limit): iterable
    {
        $this->queries[] = [$query, $limit];

        for ($index = 1; $index <= $this->count; ++$index) {
            yield new Suggestion('user:'.$index, $query.$index);
        }
    }
}
