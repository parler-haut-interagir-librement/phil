<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Application\Port\OrderProcessor;
use Phil\CommerceBundle\Application\Port\SellableResolver;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceLogisticsBundle\Application\DeliveryOptions;
use Phil\CommerceLogisticsBundle\Application\ShippingProcessor;
use Phil\CommerceLogisticsBundle\Domain\Delivery\DeliveryKind;
use Phil\CommerceLogisticsBundle\Domain\Model\DeliveryMethod;
use Phil\CommerceLogisticsBundle\Tests\Unit\Support\InMemoryLogistics;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeliveryOptions::class)]
#[CoversClass(ShippingProcessor::class)]
final class DeliveryTest extends TestCase
{
    private InMemoryLogistics $methods;

    protected function setUp(): void
    {
        $this->methods = new InMemoryLogistics();
        $this->methods->addMethod(DeliveryMethod::of('post', 'Postes Canada', DeliveryKind::Shipping, $this->cad(1_200), $this->cad(7_500), ['QC', 'ON'], 2, 5, priority: 5));
        $this->methods->addMethod(DeliveryMethod::of('pickup', 'Ramassage', DeliveryKind::Pickup, Money::zero(Currency::of('CAD')), pickupLocation: 'mtl', priority: 10));
        $this->methods->addMethod(DeliveryMethod::of('courier', 'Coursier', DeliveryKind::LocalDelivery, $this->cad(2_000), zones: ['QC'], priority: 5));
        $this->methods->addMethod(DeliveryMethod::of('euro', 'Euro', DeliveryKind::Shipping, Money::of(500, Currency::of('EUR'))));
    }

    public function testTheMethodsServingTheOrderAreOfferedByPriority(): void
    {
        $options = new DeliveryOptions($this->methods);

        self::assertSame(['pickup', 'courier', 'post'], $this->codes($options->for($this->order(1, 'QC'))));
        self::assertSame(['pickup', 'post'], $this->codes($options->for($this->order(1, 'ON'))));
        self::assertSame(['pickup'], $this->codes($options->for($this->order(1, null))));
    }

    public function testTheChosenMethodAddsItsCost(): void
    {
        $order = $this->order(2, 'QC', 'post');

        self::assertSame([['Postes Canada', 1_200, null]], $this->shipping($order));
        self::assertSame(2_000 + 1_200, $order->total()->minorUnits);
    }

    /**
     * The threshold reads the lines after their promotions: 80.00 less 10.00 is under 75.00.
     */
    public function testDeliveryIsFreeFromItsThresholdAfterPromotions(): void
    {
        self::assertSame([['Postes Canada', 0, null]], $this->shipping($this->order(8, 'QC', 'post')));
        self::assertSame([['Postes Canada', 1_200, null]], $this->shipping($this->order(8, 'QC', 'post', 1_000)));
    }

    public function testAMethodThatNoLongerServesTheOrderAddsNothing(): void
    {
        self::assertSame([], $this->shipping($this->order(1, 'BC', 'post')));
        self::assertSame([], $this->shipping($this->order(1, 'QC', 'unknown')));
        self::assertSame([], $this->shipping($this->order(1, 'QC')));
        self::assertNull(new DeliveryOptions($this->methods)->chosen($this->order(1, 'QC')));
    }

    /**
     * @param list<DeliveryMethod> $methods
     *
     * @return list<string>
     */
    private function codes(array $methods): array
    {
        return array_map(static fn (DeliveryMethod $method): string => $method->code, $methods);
    }

    /**
     * @return list<array{string, int, ?string}>
     */
    private function shipping(Order $order): array
    {
        return array_map(static fn (Adjustment $adjustment): array => [$adjustment->label, $adjustment->amount->minorUnits, $adjustment->line?->value()], $order->adjustments(AdjustmentType::Shipping));
    }

    private function order(int $quantity, ?string $zone, ?string $method = null, int $discount = 0): Order
    {
        $sellables = new class implements SellableResolver {
            #[Override]
            public function supports(string $type): bool
            {
                return 'product' === $type;
            }

            #[Override]
            public function resolve(SellableReference $reference): Sellable
            {
                return Sellable::of($reference, 'Dice', Money::of(1_000, Currency::of('CAD')), 'goods');
            }
        };
        $promotion = new readonly class($discount) implements OrderProcessor {
            public function __construct(private int $discount)
            {
            }

            #[Override]
            public function process(Order $order): void
            {
                $order->replaceAdjustments(AdjustmentType::Promotion, 0 === $this->discount ? [] : [Adjustment::of(AdjustmentType::Promotion, 'Promo', Money::of(-$this->discount, Currency::of('CAD')), SellableReference::of('product', 'dice'))]);
            }
        };
        $modifier = new OrderModifier([$sellables], [], [$promotion, new ShippingProcessor(new DeliveryOptions($this->methods))]);
        $order = Order::open('1', Currency::of('CAD'));
        $modifier->add($order, SellableReference::of('product', 'dice'), $quantity, PricingContext::of(1, new DateTimeImmutable()));
        $modifier->changeTaxZone($order, $zone);
        $modifier->chooseDeliveryMethod($order, $method);

        return $order;
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }
}
