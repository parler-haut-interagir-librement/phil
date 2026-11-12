<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Pipeline;

use Phil\ContentBundle\Domain\Content\DimensionContent;

/**
 * Copies the values of one capability from a stored row onto the merged row.
 *
 * Second stage of the pipeline, run by `ContentAggregator`: every merger sees the unlocalized row first and
 * the localized row second, so a value the localized row sets wins over the shared one. A merger copies only
 * what its source actually sets, and ignores rows that lack its capability. Mergers are tagged
 * `phil_content.merger` by autoconfiguration.
 */
interface Merger
{
    public function merge(DimensionContent $target, DimensionContent $source): void;
}
