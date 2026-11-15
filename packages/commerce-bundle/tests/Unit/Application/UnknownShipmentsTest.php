<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Application;

use Phil\CommerceBundle\Application\UnknownShipments;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UnknownShipments::class)]
final class UnknownShipmentsTest extends TestCase
{
    public function testNoOrderNorLineIsKnownToHaveLeft(): void
    {
        $shipments = new UnknownShipments();

        self::assertNull($shipments->shippedAt('2026-0001'));
        self::assertNull($shipments->shippedAt('2026-0001', SellableReference::of('product', 'dice')));
    }
}
