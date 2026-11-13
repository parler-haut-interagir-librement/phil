<?php

declare(strict_types=1);

namespace Phil\Stockroom;

/**
 * How an item is counted: as a quantity of interchangeable pieces — forty foam swords, a box of candles — or as one
 * unit followed on its own — the costume with its label, the projector with its serial number.
 */
enum Tracking: string
{
    /** Interchangeable pieces, counted by place. */
    case Quantity = 'quantity';

    /** One object, in one place at a time. */
    case Unit = 'unit';
}
