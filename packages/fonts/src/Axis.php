<?php

declare(strict_types=1);

namespace Phil\Fonts;

/**
 * A design axis of a variable font, from its `fvar` table: `wght`, `wdth`, `slnt`, `ital`, `opsz` or one of its own.
 */
final readonly class Axis
{
    public function __construct(
        public string $tag,
        public float $minimum,
        public float $default,
        public float $maximum,
        public int $nameId,
    ) {
    }
}
