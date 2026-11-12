<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Fixture;

use Phil\ContentBundle\Application\Pipeline\Merger;
use Phil\ContentBundle\Domain\Content\DimensionContent;

/**
 * Records the dimension of every source it is given, in order.
 */
final class RecordingMerger implements Merger
{
    /**
     * @var list<string>
     */
    public array $seen = [];

    public function merge(DimensionContent $target, DimensionContent $source): void
    {
        $this->seen[] = $source->dimension()->toString();
    }
}
