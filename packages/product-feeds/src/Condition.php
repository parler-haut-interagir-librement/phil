<?php

declare(strict_types=1);

namespace Phil\ProductFeeds;

/**
 * The state an item is sold in.
 */
enum Condition: string
{
    case New = 'new';
    case Refurbished = 'refurbished';
    case Used = 'used';
}
