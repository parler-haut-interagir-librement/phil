<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Check;

use Override;
use Phil\TranslationMemory\Check\Contract\TextWidth;
use Phil\TranslationMemory\Exception\InvalidConfiguration;

/**
 * The width of a line from the advance of each character in a font: the advances in font units, the units per em of
 * the font, and the size the template draws it at, in pixels. A character without an advance takes the fallback one.
 *
 * Kerning and ligatures are left out: the width is the sum of the advances, which a proportional font draws within a
 * few percent.
 */
final readonly class AdvanceWidths implements TextWidth
{
    /**
     * @param array<int, int> $advances the advance of each code point, in font units
     *
     * @throws InvalidConfiguration when the units per em are not from 16 to 16384, the size is not above 0 and at most
     *                              1000 pixels, or an advance is below 0
     */
    public function __construct(
        private int $unitsPerEm,
        private float $size,
        private array $advances,
        private int $fallback,
    ) {
        if ($unitsPerEm < 16 || $unitsPerEm > 16_384) {
            throw InvalidConfiguration::because(\sprintf('A font has 16 to 16384 units per em; got %d.', $unitsPerEm));
        }

        if ($size <= 0.0 || $size > 1_000.0) {
            throw InvalidConfiguration::because(\sprintf('A text is drawn above 0 and at most 1000 pixels high; got %s.', $size));
        }

        if ($fallback < 0 || array_any($advances, static fn (int $advance): bool => $advance < 0)) {
            throw InvalidConfiguration::because('An advance is 0 or more font units.');
        }
    }

    #[Override]
    public function width(string $text): float
    {
        $units = 0;

        foreach (mb_str_split($text) as $character) {
            $units += $this->advances[mb_ord($character)] ?? $this->fallback;
        }

        return $units * $this->size / $this->unitsPerEm;
    }
}
