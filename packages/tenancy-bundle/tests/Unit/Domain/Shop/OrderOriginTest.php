<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Domain\Shop;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\TenancyBundle\Domain\Shop\OrderOrigin;
use Phil\TenancyBundle\Domain\Shop\ShopNetwork;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrderOrigin::class)]
final class OrderOriginTest extends TestCase
{
    public function testAnOrderIsTaggedWithItsShopAndItsDistinctCatalogues(): void
    {
        $origin = OrderOrigin::placedIn($this->id('general'), $this->network(), [$this->id('rose'), $this->id('general'), $this->id('rose')]);

        self::assertSame('general', $origin->shop->value);
        self::assertSame(['rose', 'general'], array_map(static fn (TenantId $catalogue): string => $catalogue->value, $origin->catalogues));
        self::assertTrue($origin->spansCatalogues());
    }

    public function testAnOrderOfTheShopsOwnCatalogueSpansNoOther(): void
    {
        self::assertFalse(OrderOrigin::placedIn($this->id('rose'), $this->network(), [$this->id('rose')])->spansCatalogues());
        self::assertFalse(OrderOrigin::placedIn($this->id('rose'), $this->network())->spansCatalogues());
    }

    public function testACatalogueTheShopDoesNotSellIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The shop "rose" does not sell the catalogue of "general".');

        OrderOrigin::placedIn($this->id('rose'), $this->network(), [$this->id('rose'), $this->id('general')]);
    }

    private function network(): ShopNetwork
    {
        return ShopNetwork::fromReferences(['general' => ['rose']]);
    }

    private function id(string $reference): TenantId
    {
        return TenantId::fromString($reference);
    }
}
