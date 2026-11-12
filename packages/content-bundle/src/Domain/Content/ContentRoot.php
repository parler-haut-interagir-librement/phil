<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Content;

use Phil\ContentBundle\Domain\Dimension\DimensionKey;

/**
 * The root of a piece of content: what stays the same across every locale, stage and version of it.
 *
 * **The root holds no collection of its dimension rows.** Each row points to its root, and the rows are
 * loaded through `DimensionContentRepository`, one dimension at a time. A root that held them all would load
 * every version of every locale to serve one page, and would make a mapped collection part of the domain.
 *
 * The application's root is an entity of its own context. This interface is all the pipeline needs of it; a
 * root stored by the bundle's Doctrine adapter extends `AbstractContentRoot`.
 */
interface ContentRoot
{
    /**
     * A new, empty dimension row of the application's own row class, attached to this root.
     *
     * The aggregator uses it as the target it merges into, so the merged result is the same class as the
     * stored rows and carries the same capabilities.
     */
    public function createDimensionContent(DimensionKey $dimension): DimensionContent;
}
