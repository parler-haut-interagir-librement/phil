<?php

declare(strict_types=1);

namespace Phil\SocialCards\Contract;

use Phil\SocialCards\Card\CardFont;

/**
 * Measures text as the renderer draws it, so that the lines of a card are cut where its image library would overflow.
 */
interface TextMeasure
{
    /**
     * The width in pixels of one line of text, drawn in the font at a size in pixels; 0 for the empty text.
     */
    public function width(string $text, CardFont $font, int $size): int;
}
