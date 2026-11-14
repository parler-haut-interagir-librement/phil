<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Application\Shop;

use Phil\TenancyBundle\Application\Shop\ShopContext;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\Exception\NoActiveTenant;
use Phil\TenancyBundle\Domain\Shop\ShopNetwork;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShopContext::class)]
final class ShopContextTest extends TestCase
{
    public function testTheCurrentShopIsTheActiveTenant(): void
    {
        $context = new TenantContext();
        $context->enter($this->id('general'));

        $shop = new ShopContext($context, ShopNetwork::fromReferences(['general' => ['rose']]));

        self::assertSame('general', $shop->current()?->value);
        self::assertSame(['general', 'rose'], array_map(static fn (TenantId $catalogue): string => $catalogue->value, $shop->catalogues()));
        self::assertTrue($shop->sells($this->id('rose')));
        self::assertFalse($shop->sells($this->id('tavern')));
        self::assertSame(['rose'], array_map(static fn (TenantId $catalogue): string => $catalogue->value, $shop->origin([$this->id('rose')])->catalogues));
        self::assertSame('general', $shop->origin()->shop->value);
    }

    public function testOutsideAShopNothingIsSold(): void
    {
        $shop = new ShopContext(new TenantContext(), ShopNetwork::fromReferences(['general' => ['rose']]));

        self::assertNull($shop->current());
        self::assertSame([], $shop->catalogues());
        self::assertFalse($shop->sells($this->id('rose')));

        $this->expectException(NoActiveTenant::class);
        $shop->origin();
    }

    private function id(string $reference): TenantId
    {
        return TenantId::fromString($reference);
    }
}
