<?php

declare(strict_types=1);

namespace Phil\Stockroom;

/**
 * Whether an item may leave the stockroom: usable, waiting for a repair, or retired for good. A lost item is not a
 * condition: its loss is a movement, and what is left of it stays usable.
 */
enum ItemCondition: string
{
    case Usable = 'usable';
    case NeedsRepair = 'needs_repair';
    case Retired = 'retired';

    public function mayLeave(): bool
    {
        return self::Usable === $this;
    }
}
