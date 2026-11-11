<?php

declare(strict_types=1);

namespace Phil\PrintPreflight;

/**
 * A side of the trim box, in the order CSS lists them.
 */
enum Edge: string
{
    case Top = 'top';
    case Right = 'right';
    case Bottom = 'bottom';
    case Left = 'left';

    /**
     * The edges as a list for a message: `top, left`.
     *
     * @param list<self> $edges
     */
    public static function describe(array $edges): string
    {
        return implode(', ', array_map(static fn (self $edge): string => $edge->value, $edges));
    }
}
