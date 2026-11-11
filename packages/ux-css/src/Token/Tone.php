<?php

declare(strict_types=1);

namespace Phil\UxCss\Token;

use Phil\UxCss\Color\Oklch;

/**
 * A tone of the palette: its hue's name, its number — the lightness times 100 — and its colour.
 */
final readonly class Tone
{
    public function __construct(
        public string $hue,
        public int $number,
        public Oklch $color,
    ) {
    }

    public function name(): string
    {
        return \sprintf('--phil-%s-%02d', $this->hue, $this->number);
    }
}
