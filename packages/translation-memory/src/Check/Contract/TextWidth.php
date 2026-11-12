<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Check\Contract;

/**
 * How wide a line of plain text is drawn, in pixels, in the font and size a template gives it.
 */
interface TextWidth
{
    /**
     * The width of one line of plain text, in pixels.
     */
    public function width(string $text): float;
}
