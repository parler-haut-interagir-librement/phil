<?php

declare(strict_types=1);

namespace Phil\PrintCompositor;

/**
 * Where something shorter than its box sits down it.
 */
enum VerticalAlign: string
{
    case Top = 'top';
    case Middle = 'middle';
    case Bottom = 'bottom';

    /**
     * The offset, in pixels from the top edge of a box of the given height, of something of the given height; centred,
     * it leans one pixel to the top when the difference is odd.
     */
    public function offset(int $box, int $content): int
    {
        return match ($this) {
            self::Top => 0,
            self::Middle => intdiv($box - $content, 2),
            self::Bottom => $box - $content,
        };
    }
}
