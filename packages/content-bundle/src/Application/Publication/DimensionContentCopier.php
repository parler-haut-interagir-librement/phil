<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Publication;

use Phil\ContentBundle\Application\Pipeline\Merger;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;

/**
 * Copies one row onto a new row of another dimension: publishing copies a draft row to its live twin, forking
 * copies a version to the next one.
 *
 * **The copy runs through the mergers**, onto a fresh row the root builds: a merger copies every value its
 * source sets, and a fresh row has none, so every capability that has a merger is copied whole. A capability
 * of the application without a merger is not copied — which it would not be read through the aggregator
 * either.
 */
final readonly class DimensionContentCopier
{
    /**
     * @var array<Merger>
     */
    private array $mergers;

    /**
     * @param iterable<Merger> $mergers read once, here
     */
    public function __construct(iterable $mergers)
    {
        $this->mergers = [...$mergers];
    }

    /**
     * A new row of `$dimension`, under the same root, holding the values of `$source`. Not persisted.
     */
    public function copy(DimensionContent $source, DimensionKey $dimension): DimensionContent
    {
        $target = $source->root()->createDimensionContent($dimension);

        foreach ($this->mergers as $merger) {
            $merger->merge($target, $source);
        }

        return $target;
    }
}
