<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application\Port;

use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;

/**
 * Draws the value of an option once its order is paid: a set of dice, an entry of a list. The drawer names it
 * supports are those its options declare; how it draws, and whether a buyer may verify the draw, is its own.
 *
 * A service implementing it is tagged `phil_commerce.option_drawer` by autoconfiguration.
 */
interface OptionDrawer
{
    public function supports(string $drawer): bool;

    /**
     * The draw for one unit of the line, numbered from 1: the text a receipt shows and the composer reads, 1 to 1000
     * characters.
     */
    public function draw(string $drawer, Order $order, OrderLine $line, string $option, int $unit): string;
}
