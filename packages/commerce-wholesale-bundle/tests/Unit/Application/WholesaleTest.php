<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Application\Port\SellableResolver;
use Phil\CommerceBundle\Application\Port\UnitPriceResolver;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceWholesaleBundle\Application\CustomerGroups;
use Phil\CommerceWholesaleBundle\Application\GroupPrices;
use Phil\CommerceWholesaleBundle\Domain\Exception\WholesaleRefused;
use Phil\CommerceWholesaleBundle\Domain\Model\CustomerGroup;
use Phil\CommerceWholesaleBundle\Tests\Unit\Support\InMemoryGroups;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GroupPrices::class)]
#[CoversClass(CustomerGroups::class)]
final class WholesaleTest extends TestCase
{
    private InMemoryGroups $groups;

    protected function setUp(): void
    {
        $this->groups = new InMemoryGroups(
            CustomerGroup::of('wholesale', 'Wholesale', Currency::of('CAD'), 250_000, Money::of(10_000, Currency::of('CAD'))),
            CustomerGroup::of('staff', 'Staff', Currency::of('CAD')),
            CustomerGroup::of('euro', 'Euro', Currency::of('EUR'), 100_000, Money::of(1, Currency::of('EUR'))),
        );
    }

    public function testAProductIsPricedForTheOrdersGroup(): void
    {
        $prices = new GroupPrices($this->groups);
        $dice = $this->sellable('product');

        self::assertSame(750, $prices->priceFor($dice, $this->context('wholesale'))?->minorUnits);
        self::assertNull($prices->priceFor($dice, $this->context(null)));
        self::assertNull($prices->priceFor($dice, $this->context('staff')));
        self::assertNull($prices->priceFor($dice, $this->context('unknown')));
        self::assertNull($prices->priceFor($dice, $this->context('euro')));
        self::assertNull($prices->priceFor($this->sellable('offer'), $this->context('wholesale')));

        $this->groups->findGroup('wholesale')?->withdraw();
        self::assertNull($prices->priceFor($dice, $this->context('wholesale')));
    }

    /**
     * A price book of the catalogue, asked first, wins over the group's share; the group's share otherwise.
     */
    public function testAPriceListOfTheGroupComesFirst(): void
    {
        $book = new class implements UnitPriceResolver {
            #[Override]
            public function priceFor(Sellable $sellable, PricingContext $context): ?Money
            {
                return 'list' === $sellable->reference->id ? Money::of(600, Currency::of('CAD')) : null;
            }
        };
        $modifier = new OrderModifier([$this->resolver()], [$book, new GroupPrices($this->groups)], []);
        $order = Order::open('1', Currency::of('CAD'));
        $modifier->changeAudience($order, null, 'wholesale', $this->context(null));
        $modifier->add($order, SellableReference::of('product', 'list'), 1, $this->context(null));
        $modifier->add($order, SellableReference::of('product', 'dice'), 2, $this->context(null));

        self::assertSame([600, 750], [$order->lines()[0]->unitPrice->minorUnits, $order->lines()[1]->unitPrice->minorUnits]);
    }

    public function testBuyersJoinMoveAndLeaveGroups(): void
    {
        $groups = new CustomerGroups($this->groups);

        self::assertNull($groups->groupOf('tenant-1'));

        $groups->join('tenant-1', 'wholesale');
        self::assertSame('wholesale', $groups->groupOf('tenant-1'));

        $groups->join('tenant-1', 'staff');
        self::assertSame('staff', $groups->groupOf('tenant-1'));
        self::assertCount(1, $this->groups->memberships);

        $this->groups->findGroup('staff')?->withdraw();
        self::assertNull($groups->groupOf('tenant-1'));

        $groups->leave('tenant-1');
        $groups->leave('tenant-1');
        self::assertSame([], $this->groups->memberships);
    }

    public function testAnUnknownGroupIsRefused(): void
    {
        $this->expectException(WholesaleRefused::class);

        new CustomerGroups($this->groups)->join('tenant-1', 'gold');
    }

    public function testAnOrderIsShortOfItsGroupsMinimum(): void
    {
        $groups = new CustomerGroups($this->groups);
        $order = $this->order('wholesale', 6);

        self::assertSame(4_000, $groups->shortOfMinimum($order)->minorUnits);
        self::assertTrue($groups->shortOfMinimum($this->order('wholesale', 10))->isZero());
        self::assertTrue($groups->shortOfMinimum($this->order('wholesale', 12))->isZero());
        self::assertTrue($groups->shortOfMinimum($this->order(null, 1))->isZero());
        self::assertTrue($groups->shortOfMinimum($this->order('unknown', 1))->isZero());
        self::assertTrue($groups->shortOfMinimum($this->order('euro', 1))->isZero());
        self::assertSame('CAD', $groups->shortOfMinimum($this->order(null, 1))->currency->code);

        $this->groups->findGroup('wholesale')?->withdraw();
        self::assertTrue($groups->shortOfMinimum($order)->isZero());
    }

    private function order(?string $group, int $quantity): Order
    {
        $price = Money::of(1_000, Currency::of('CAD'));
        $order = Order::open('1', Currency::of('CAD'));
        $order->changeCustomerGroup($group);
        $order->add($this->sellable('product'), $quantity, $price);

        return $order;
    }

    private function resolver(): SellableResolver
    {
        return new class implements SellableResolver {
            #[Override]
            public function supports(string $type): bool
            {
                return 'product' === $type;
            }

            #[Override]
            public function resolve(SellableReference $reference): Sellable
            {
                return Sellable::of($reference, $reference->id, Money::of(1_000, Currency::of('CAD')), 'goods');
            }
        };
    }

    private function sellable(string $type): Sellable
    {
        return Sellable::of(SellableReference::of($type, 'dice'), 'Dice', Money::of(1_000, Currency::of('CAD')), 'goods');
    }

    private function context(?string $group): PricingContext
    {
        return PricingContext::of(1, new DateTimeImmutable(), $group);
    }
}
