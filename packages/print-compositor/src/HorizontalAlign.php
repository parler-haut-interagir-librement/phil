<?php

declare(strict_types=1);

namespace Phil\PrintCompositor;

/**
 * Where something narrower than its box sits across it.
 */
enum HorizontalAlign: string
{
    case Left = 'left';
    case Centre = 'centre';
    case Right = 'right';

    /**
     * The offset, in pixels from the left edge of a box of the given width, of something of the given width; centred,
     * it leans one pixel to the left when the difference is odd.
     */
    public function offset(int $box, int $content): int
    {
        return match ($this) {
            self::Left => 0,
            self::Centre => intdiv($box - $content, 2),
            self::Right => $box - $content,
        };
    }
}
