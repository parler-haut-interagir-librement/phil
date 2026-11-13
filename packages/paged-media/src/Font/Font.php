<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Font;

/**
 * A font text is written and measured in: one of the standard fonts every PDF reader has, or a TrueType font the
 * document embeds.
 */
interface Font
{
    /**
     * The width of a text at a size, in points, as the document prints it.
     */
    public function width(string $text, float $size): float;
}
