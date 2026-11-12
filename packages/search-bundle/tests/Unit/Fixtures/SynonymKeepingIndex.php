<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Unit\Fixtures;

use Override;
use Phil\SearchBundle\Application\Contract\SynonymAwareIndex;

/**
 * An index that keeps its synonyms itself, and records those it is given.
 */
final class SynonymKeepingIndex extends QueryRecorder implements SynonymAwareIndex
{
    #[Override]
    public function replaceSynonyms(string $index, array $synonyms): void
    {
        $this->synonyms[] = [$index, $synonyms];
    }
}
