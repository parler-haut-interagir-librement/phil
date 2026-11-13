<?php

declare(strict_types=1);

namespace Phil\Citations;

/**
 * One reference cited at one place of a text: its id, where in it (« p. 12 »), and words before or after it.
 */
final readonly class Cite
{
    public function __construct(
        public string $id,
        public ?string $locator = null,
        public Locator $label = Locator::Page,
        public ?string $prefix = null,
        public ?string $suffix = null,
    ) {
    }
}
