<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Publication;

/**
 * Something the conversion of a draft into UJO does not keep: the element, its path in the source, and why —
 * `unknown-element`, `refused-scheme`, `svg-without-store`… —, as `phil/markup`'s readers name it.
 */
final readonly class ConversionLoss
{
    public function __construct(
        public string $element,
        public string $path,
        public string $reason,
    ) {
    }
}
