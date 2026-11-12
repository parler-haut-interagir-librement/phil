<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Normalizer;

use Override;
use Phil\ContentBundle\Application\Pipeline\Normalizer;
use Phil\ContentBundle\Domain\Capability\BodyCapable;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\ValueObject\Body;
use Phil\Markup\HtmlPurifier;
use Phil\Markup\Level;

/**
 * `BodyCapable` as plain data: `bodySource`, `bodyFormat`, and `bodyHtml`, **purified here, on every read**.
 *
 * A draft row gives the source in its author's format; a live row gives the UJO its publication converted the
 * draft into, with the format `ujo` — the JSON a block editor opens —, and the HTML compiled from it.
 *
 * The stored HTML was purified when the data mapper wrote it; it is purified again because a row can be
 * written without the data mapper, and because the policy can tighten after the row was written. What leaves
 * this normalizer is the only HTML of a body a renderer may print unescaped.
 */
final readonly class BodyNormalizer implements Normalizer
{
    public function __construct(
        private HtmlPurifier $purifier,
    ) {
    }

    #[Override]
    public function normalize(DimensionContent $content): array
    {
        if (!$content instanceof BodyCapable) {
            return [];
        }

        $body = $content->getBody();

        return [
            'bodySource' => $body?->source,
            'bodyFormat' => $body?->format,
            'bodyHtml' => $body instanceof Body ? $this->purifier->purify($body->html, Level::All) : null,
        ];
    }
}
