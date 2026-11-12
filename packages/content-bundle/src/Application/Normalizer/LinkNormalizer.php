<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Normalizer;

use Override;
use Phil\ContentBundle\Application\Pipeline\Normalizer;
use Phil\ContentBundle\Domain\Capability\LinkCapable;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\ValueObject\Link;

/**
 * `LinkCapable` as plain data, under `link`: `null`, or one shape for both kinds of target, with the keys of
 * the other kind set to `null` — `{resourceKey, resourceId, locale, url}`. The shape `LinkDataMapper` reads.
 */
final readonly class LinkNormalizer implements Normalizer
{
    #[Override]
    public function normalize(DimensionContent $content): array
    {
        if (!$content instanceof LinkCapable) {
            return [];
        }

        $link = $content->getLink();

        return [
            'link' => $link instanceof Link ? [
                'resourceKey' => $link->resourceKey,
                'resourceId' => $link->resourceId,
                'locale' => $link->locale?->value,
                'url' => $link->url?->toString(),
            ] : null,
        ];
    }
}
