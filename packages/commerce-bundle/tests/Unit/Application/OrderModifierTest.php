<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Application;

use ArrayObject;
use Closure;
use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Application\Port\OrderProcessor;
use Phil\CommerceBundle\Application\Port\SellableAvailability;
use Phil\CommerceBundle\Application\Port\SellableResolver;
use Phil\CommerceBundle\Application\Port\UnitPriceResolver;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Channel\Offer;
use Phil\CommerceBundle\Domain\Channel\Visibility;
use Phil\CommerceBundle\Domain\Exception\NotSoldThere;
use Phil\CommerceBundle\Domain\Exception\UnknownSellable;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrderModifier::class)]
#[CoversClass(UnknownSellable::class)]
#[CoversClass(NotSoldThere::class)]
final class OrderModifierTest extends TestCase
{
    /** @var ArrayObject<int, array{string, int, ?string, ?string}> */
    private ArrayObject $priceRequests;

    /** @var ArrayObject<int, string> */
    private ArrayObject $processed;

    #[Override]
    protected function setUp(): void
    {
        $this->priceRequests = new ArrayObject();
        $this->processed = new ArrayObject();
    }

    public function testAnAddedLineTakesTheSellableAndItsBasePrice(): void
    {
        $order = $this->order();

        $this->modifier()->add($order, SellableReference::of('product', 'dice'), 2, $this->context());

        $line = $order->line(SellableReference::of('product', 'dice'));
        self::assertNotNull($line);
        self::assertSame('Dice', $line->description);
        self::assertSame(2, $line->quantity);
        self::assertSame(1_000, $line->unitPrice->minorUnits);
        self::assertCount(1, $this->processed);
    }

    /**
     * The first resolver to give a price wins; the price is asked for the line's whole quantity.
     */
    public function testThePriceResolversAreAskedInOrderForTheWholeLine(): void
    {
        $order = $this->order();
        $modifier = $this->modifier();

        $modifier->add($order, SellableReference::of('product', 'dice'), 9, $this->context('wholesale', 'store'));
        $modifier->add($order, SellableReference::of('product', 'dice'), 1, $this->context('wholesale', 'store'));

        self::assertSame(10, $order->lines()[0]->quantity);
        self::assertSame(700, $order->lines()[0]->unitPrice->minorUnits);
        self::assertSame([['first', 9, 'wholesale', 'store'], ['second', 9, 'wholesale', 'store'], ['first', 10, 'wholesale', 'store']], $this->priceRequests->getArrayCopy());
    }

    public function testAChangedQuantityIsPricedAgain(): void
    {
        $order = $this->order();
        $modifier = $this->modifier();
        $modifier->add($order, SellableReference::of('product', 'dice'), 1, $this->context('wholesale'));

        $modifier->changeQuantity($order, SellableReference::of('product', 'dice'), 12, $this->context('wholesale'));

        self::assertSame(12, $order->lines()[0]->quantity);
        self::assertSame(600, $order->lines()[0]->unitPrice->minorUnits);
        self::assertSame(['first', 12, 'wholesale', null], $this->priceRequests[\count($this->priceRequests) - 1]);
        self::assertCount(2, $this->processed);
    }

    public function testRemovingAndChangingTheZoneRecalculate(): void
    {
        $order = $this->order();
        $modifier = $this->modifier();
        $modifier->add($order, SellableReference::of('product', 'dice'), 1, $this->context());

        $modifier->changeTaxZone($order, 'QC');
        self::assertSame('QC', $order->taxZone());
        self::assertCount(2, $this->processed);

        $modifier->remove($order, SellableReference::of('product', 'dice'));
        self::assertTrue($order->isEmpty());
        self::assertCount(3, $this->processed);

        $modifier->recalculate($order);
        self::assertCount(4, $this->processed);
    }

    /**
     * The order's channel and customer group price its lines unless the caller gives its own.
     */
    public function testTheOrdersAudiencePricesItsLines(): void
    {
        $order = $this->order();
        $modifier = $this->modifier();
        $modifier->add($order, SellableReference::of('product', 'dice'), 12, $this->context());
        self::assertSame(1_000, $order->lines()[0]->unitPrice->minorUnits);

        $modifier->changeAudience($order, 'store', 'wholesale', $this->context());

        self::assertSame('store', $order->channel());
        self::assertSame('wholesale', $order->customerGroup());
        self::assertSame(600, $order->lines()[0]->unitPrice->minorUnits);
        self::assertSame(['first', 12, 'wholesale', 'store'], $this->priceRequests[\count($this->priceRequests) - 1]);
        self::assertCount(2, $this->processed);

        $modifier->changeQuantity($order, SellableReference::of('product', 'dice'), 12, $this->context('retail', 'web'));
        self::assertSame(['second', 12, 'retail', 'web'], $this->priceRequests[\count($this->priceRequests) - 1]);
    }

    public function testIncludingTheTaxesRecalculates(): void
    {
        $order = $this->order();
        $this->modifier()->changeTaxIncluded($order, true);

        self::assertTrue($order->taxIncluded());
        self::assertCount(1, $this->processed);
    }

    public function testADeliveryMethodIsChosenWithARecalculation(): void
    {
        $order = $this->order();
        $this->modifier()->chooseDeliveryMethod($order, 'post');

        self::assertSame('post', $order->deliveryMethod());
        self::assertCount(1, $this->processed);
    }

    public function testCodesAreEnteredAndRemovedWithARecalculation(): void
    {
        $order = $this->order();
        $modifier = $this->modifier();

        $modifier->enterCode($order, 'SAVE10');
        self::assertSame(['SAVE10'], $order->codes());
        self::assertCount(1, $this->processed);

        $modifier->removeCode($order, 'SAVE10');
        self::assertSame([], $order->codes());
        self::assertCount(2, $this->processed);
    }

    /**
     * A channel's selection refuses what it does not sell, on adding and on moving the order to it, before anything
     * changes.
     */
    public function testAChannelSellsOnlyItsSelection(): void
    {
        $selection = new class implements SellableAvailability {
            #[Override]
            public function offerThrough(SellableReference $reference, ?string $channel): Offer
            {
                return 'store' === $channel ? Offer::of(Visibility::Unlisted, false) : Offer::full();
            }
        };
        $modifier = new OrderModifier([$this->sellables()], [], [], [$selection]);
        $order = $this->order();
        $modifier->add($order, SellableReference::of('product', 'dice'), 1, $this->context());

        try {
            $modifier->changeAudience($order, 'store', 'vip', $this->context());
            self::fail('The order moved to the channel.');
        } catch (NotSoldThere $notSoldThere) {
            self::assertSame('product:dice is not sold through store.', $notSoldThere->getMessage());
        }

        self::assertNull($order->channel());
        self::assertNull($order->customerGroup());

        $modifier->changeAudience($order, 'web', null, $this->context());
        self::assertSame('web', $order->channel());

        $store = $this->order();
        $store->changeChannel('store');

        $this->expectException(NotSoldThere::class);

        $modifier->add($store, SellableReference::of('product', 'dice'), 1, $this->context());
    }

    /**
     * What only its direct link reaches is sold as anything listed: a hidden ticket, a presale.
     */
    public function testAnOfferReachedByItsLinkOnlyIsSold(): void
    {
        $selection = new class implements SellableAvailability {
            #[Override]
            public function offerThrough(SellableReference $reference, ?string $channel): Offer
            {
                return Offer::of(Visibility::Unlisted);
            }
        };
        $order = $this->order();

        new OrderModifier([$this->sellables()], [], [], [$selection])->add($order, SellableReference::of('product', 'dice'), 2, $this->context());

        self::assertSame(2, $order->lines()[0]->quantity);
    }

    public function testAnOrderWithoutAChannelIsNamedSo(): void
    {
        self::assertSame('product:dice is not sold through an order without a channel.', NotSoldThere::of(SellableReference::of('product', 'dice'), null)->getMessage());
        self::assertInstanceOf(DomainError::class, NotSoldThere::of(SellableReference::of('product', 'dice'), null));
    }

    /**
     * The cart is recomputed before it is placed.
     */
    public function testPlacingRecalculatesFirst(): void
    {
        $order = $this->order();
        $modifier = $this->modifier();
        $modifier->add($order, SellableReference::of('product', 'dice'), 1, $this->context());

        $at = new DateTimeImmutable('2026-11-03T15:00:00Z');

        $modifier->place($order, $at);

        self::assertCount(2, $this->processed);
        self::assertSame(OrderState::Placed, $order->state());
        self::assertSame($at, $order->placedAt());
    }

    /**
     * Every processor runs, in the order given: the second sees what the first wrote.
     */
    public function testEveryProcessorRunsInOrder(): void
    {
        $order = $this->order();
        /** @var ArrayObject<int, int> $seen */
        $seen = new ArrayObject();
        $first = new class implements OrderProcessor {
            #[Override]
            public function process(Order $order): void
            {
                $order->replaceAdjustments(AdjustmentType::Fee, [Adjustment::of(AdjustmentType::Fee, 'Fee', Money::of(100, $order->currency))]);
            }
        };
        $second = new readonly class($seen) implements OrderProcessor {
            /** @param ArrayObject<int, int> $seen */
            public function __construct(private ArrayObject $seen)
            {
            }

            #[Override]
            public function process(Order $order): void
            {
                $this->seen->append($order->adjustmentsTotal()->minorUnits);
            }
        };

        new OrderModifier([$this->sellables()], [], [$first, $second])->recalculate($order);

        self::assertSame([100], $seen->getArrayCopy());
    }

    public function testAReferenceNoResolverSupportsIsUnknown(): void
    {
        $this->expectException(UnknownSellable::class);
        $this->expectExceptionMessageIsOrContains('Nothing sellable is known as offer:gold.');

        $this->modifier()->add($this->order(), SellableReference::of('offer', 'gold'), 1, $this->context());
    }

    public function testAnIdentifierTheResolverDoesNotKnowIsUnknown(): void
    {
        $this->expectException(UnknownSellable::class);
        $this->expectExceptionMessageIsOrContains('Nothing sellable is known as product:meeple.');

        $this->modifier()->changeQuantity($this->order(), SellableReference::of('product', 'meeple'), 1, $this->context());
    }

    /**
     * Only the first resolver that supports the type is asked: another one is never asked after it.
     */
    public function testOnlyTheResolverOfTheTypeIsAsked(): void
    {
        $order = $this->order();
        $other = new class implements SellableResolver {
            #[Override]
            public function supports(string $type): bool
            {
                return 'download' === $type;
            }

            #[Override]
            public function resolve(SellableReference $reference): Sellable
            {
                return Sellable::of($reference, 'Download', Money::of(1, Currency::of('CAD')), 'digital');
            }
        };

        new OrderModifier([$other, $this->sellables()], [], [])->add($order, SellableReference::of('product', 'dice'), 1, $this->context());

        self::assertSame('Dice', $order->lines()[0]->description);

        $silent = new class implements SellableResolver {
            #[Override]
            public function supports(string $type): bool
            {
                return 'product' === $type;
            }

            #[Override]
            public function resolve(SellableReference $reference): ?Sellable
            {
                return null;
            }
        };

        $this->expectException(UnknownSellable::class);

        new OrderModifier([$silent, $this->sellables()], [], [])->add($order, SellableReference::of('product', 'dice'), 1, $this->context());
    }

    public function testAnUnknownSellableIsADomainError(): void
    {
        self::assertInstanceOf(DomainError::class, UnknownSellable::of(SellableReference::of('product', '1')));
    }

    private function modifier(): OrderModifier
    {
        $first = $this->priceList('first', static fn (PricingContext $context): ?int => 'wholesale' === $context->customerGroup && $context->quantity >= 10 ? 700 - ($context->quantity >= 12 ? 100 : 0) : null);
        $second = $this->priceList('second', static fn (PricingContext $context): ?int => 'store' === $context->channel ? 900 : null);
        $counter = new readonly class($this->processed) implements OrderProcessor {
            /** @param ArrayObject<int, string> $processed */
            public function __construct(private ArrayObject $processed)
            {
            }

            #[Override]
            public function process(Order $order): void
            {
                $this->processed->append($order->number);
            }
        };

        return new OrderModifier([$this->sellables()], [$first, $second], [$counter]);
    }

    /**
     * A price resolver that records each request under its name.
     *
     * @param Closure(PricingContext): ?int $price
     */
    private function priceList(string $name, Closure $price): UnitPriceResolver
    {
        return new readonly class($name, $this->priceRequests, $price) implements UnitPriceResolver {
            /**
             * @param ArrayObject<int, array{string, int, ?string, ?string}> $requests
             * @param Closure(PricingContext): ?int                          $price
             */
            public function __construct(private string $name, private ArrayObject $requests, private Closure $price)
            {
            }

            #[Override]
            public function priceFor(Sellable $sellable, PricingContext $context): ?Money
            {
                $this->requests->append([$this->name, $context->quantity, $context->customerGroup, $context->channel]);
                $price = ($this->price)($context);

                return null === $price ? null : Money::of($price, $sellable->unitPrice->currency);
            }
        };
    }

    private function sellables(): SellableResolver
    {
        return new class implements SellableResolver {
            #[Override]
            public function supports(string $type): bool
            {
                return 'product' === $type;
            }

            #[Override]
            public function resolve(SellableReference $reference): ?Sellable
            {
                return 'dice' === $reference->id ? Sellable::of($reference, 'Dice', Money::of(1_000, Currency::of('CAD')), 'goods') : null;
            }
        };
    }

    private function order(): Order
    {
        return Order::open('1', Currency::of('CAD'));
    }

    private function context(?string $group = null, ?string $channel = null): PricingContext
    {
        return PricingContext::of(1, new DateTimeImmutable('2026-11-03T12:00:00Z'), $group, $channel);
    }
}
