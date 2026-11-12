<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Pipeline;

use Phil\ContentBundle\Domain\Content\DimensionContent;

/**
 * Turns the values of one capability of a merged row into plain data.
 *
 * Last stage of the pipeline: what a renderer, an API response or a search index reads. Each normalizer
 * returns the fragment of its own capability, an empty array for a row that lacks it, and the application
 * decides how fragments combine. Normalizers are tagged `phil_content.normalizer` by autoconfiguration.
 */
interface Normalizer
{
    /**
     * @return array<string, mixed>
     */
    public function normalize(DimensionContent $content): array;
}
