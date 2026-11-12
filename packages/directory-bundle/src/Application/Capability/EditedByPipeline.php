<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Application\Capability;

use Phil\ContentBundle\Application\DataMapper\SubmittedData;
use Phil\ContentBundle\Application\Pipeline\DataMapper;
use Phil\ContentBundle\Application\Pipeline\Merger;
use Phil\ContentBundle\Application\Pipeline\Normalizer;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\DirectoryBundle\Domain\Capability\EditedByCapable;

/**
 * Who wrote a version through the pipeline of content-bundle: `editedBy`, on the row of the locale, carried by
 * publishing, and normalized.
 */
final readonly class EditedByPipeline implements DataMapper, Merger, Normalizer
{
    public function map(DimensionContent $unlocalized, DimensionContent $localized, array $data): void
    {
        if ($localized instanceof EditedByCapable && SubmittedData::has($data, 'editedBy')) {
            $localized->setEditedBy(SubmittedData::string($data, 'editedBy'));
        }
    }

    public function merge(DimensionContent $target, DimensionContent $source): void
    {
        if ($target instanceof EditedByCapable && $source instanceof EditedByCapable && null !== $source->getEditedBy()) {
            $target->setEditedBy($source->getEditedBy());
        }
    }

    public function normalize(DimensionContent $content): array
    {
        return $content instanceof EditedByCapable ? ['editedBy' => $content->getEditedBy()] : [];
    }
}
