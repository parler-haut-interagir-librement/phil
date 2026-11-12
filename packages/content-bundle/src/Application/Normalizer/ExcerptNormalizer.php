<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Normalizer;

use Override;
use Phil\ContentBundle\Application\Pipeline\Normalizer;
use Phil\ContentBundle\Domain\Capability\ExcerptCapable;
use Phil\ContentBundle\Domain\Content\DimensionContent;

/**
 * `ExcerptCapable` as plain data: `excerptTitle`, `excerptDescription`, and `excerptImage`, the reference as
 * stored, for the application to resolve.
 */
final readonly class ExcerptNormalizer implements Normalizer
{
    #[Override]
    public function normalize(DimensionContent $content): array
    {
        if (!$content instanceof ExcerptCapable) {
            return [];
        }

        return [
            'excerptTitle' => $content->getExcerptTitle(),
            'excerptDescription' => $content->getExcerptDescription(),
            'excerptImage' => $content->getExcerptImage(),
        ];
    }
}
