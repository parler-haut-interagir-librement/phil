<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Pipeline;

use Phil\ContentBundle\Domain\Content\DimensionContent;

/**
 * Writes submitted data onto the two rows of one dimension: the unlocalized row and the localized one.
 *
 * First stage of the pipeline. Each mapper handles one capability, ignores rows that lack it, and decides
 * which of its values are shared and which are per locale. Mappers are tagged `phil_content.data_mapper`
 * by autoconfiguration; the application runs them all, in any order, since no two write the same values.
 */
interface DataMapper
{
    /**
     * @param array<string, mixed> $data the submitted values, already validated by the application
     */
    public function map(DimensionContent $unlocalized, DimensionContent $localized, array $data): void;
}
