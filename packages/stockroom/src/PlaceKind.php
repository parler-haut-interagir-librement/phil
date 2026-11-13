<?php

declare(strict_types=1);

namespace Phil\Stockroom;

/**
 * Where stock can be: in a container of the stockroom, deployed at an event, or lent to a holder.
 */
enum PlaceKind: string
{
    case Container = 'container';
    case Event = 'event';
    case Holder = 'holder';
}
