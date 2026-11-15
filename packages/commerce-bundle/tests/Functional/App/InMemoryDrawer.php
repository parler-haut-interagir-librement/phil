<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Functional\App;

use Override;
use Phil\CommerceBundle\Application\Port\OptionDrawer;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;

/**
 * The drawer of the mystery box, in memory: each unit draws the item of its number.
 */
final readonly class InMemoryDrawer implements OptionDrawer
{
    #[Override]
    public function supports(string $drawer): bool
    {
        return 'box' === $drawer;
    }

    #[Override]
    public function draw(string $drawer, Order $order, OrderLine $line, string $option, int $unit): string
    {
        return 'item '.$unit;
    }
}
