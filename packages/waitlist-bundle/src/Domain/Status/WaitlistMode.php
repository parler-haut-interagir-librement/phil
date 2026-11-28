<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Domain\Status;

/**
 * How a waiting list orders its entries. In a queue, the first to join is the first offered a place. In a draw, the
 * entries made before the draw are ordered by a published seed when it is made, nobody is offered a place before it,
 * and those who join afterwards wait behind them, in the order they join.
 */
enum WaitlistMode: string
{
    case Queue = 'queue';
    case Draw = 'draw';
}
