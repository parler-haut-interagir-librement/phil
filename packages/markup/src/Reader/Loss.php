<?php

declare(strict_types=1);

namespace Phil\Markup\Reader;

/**
 * Something a reader did not keep: the element, its path in the source — `body > div[2] > p[1]`, each element counted
 * among its siblings of the same name —, and why.
 */
final readonly class Loss
{
    public function __construct(
        public string $element,
        public string $path,
        public LossReason $reason,
    ) {
    }
}
