<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Normalizer;

use Override;
use Phil\ContentBundle\Application\Pipeline\Normalizer;
use Phil\ContentBundle\Domain\Capability\RoutableCapable;
use Phil\ContentBundle\Domain\Content\DimensionContent;

/**
 * `RoutableCapable` as plain data: `slug`, a string or `null`.
 */
final readonly class RoutableNormalizer implements Normalizer
{
    #[Override]
    public function normalize(DimensionContent $content): array
    {
        if (!$content instanceof RoutableCapable) {
            return [];
        }

        return ['slug' => $content->getSlug()?->value];
    }
}
