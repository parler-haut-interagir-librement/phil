<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Application\PaymentLinks;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Application\SecretToken;
use Phil\CommerceBundle\Application\WishLists;
use Phil\CommerceBundle\Domain\Event\OrderPurchased;
use Phil\CommerceBundle\Domain\Exception\PaymentRefused;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Tests\Unit\Support\FakeGateway;
use Phil\CommerceBundle\Tests\Unit\Support\InMemoryCommerceStore;
use Phil\CommerceBundle\Tests\Unit\Support\InMemoryOrders;
use Phil\CommerceBundle\Tests\Unit\Support\InMemoryPayments;
use Phil\CommerceBundle\Tests\Unit\Support\PricedProducts;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WishLists::class)]
#[CoversClass(PaymentLinks::class)]
#[CoversClass(SecretToken::class)]
final class WishListsAndLinksTest extends TestCase
{
    private InMemoryCommerceStore $store;

    private InMemoryOrders $orders;

    private OrderModifier $modifier;

    private WishLists $lists;

    #[Override]
    protected function setUp(): void
    {
        $this->store = new InMemoryCommerceStore();
        $this->orders = new InMemoryOrders();
        $this->modifier = new OrderModifier([new PricedProducts()], [], []);
        $this->lists = new WishLists($this->store, $this->orders, $this->modifier);
    }

    public function testATokenIsShownOnceAndOnlyItsHashIsKept(): void
    {
        [$token, $hash] = SecretToken::create();

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{32}$/', $token);
        self::assertSame(hash('sha256', $token), $hash);
        self::assertSame($hash, SecretToken::hash($token));
        self::assertNotSame($token, SecretToken::create()[0]);
        self::assertNull(SecretToken::hash(substr($token, 1)));
        self::assertNull(SecretToken::hash($token.'A'));
        self::assertNull(SecretToken::hash(str_repeat('+', 32)));
    }

    public function testASharedListIsReadByItsLinkAndOthersBuyFromIt(): void
    {
        $list = $this->lists->create('member-1', 'Birthday', new DateTimeImmutable());
        $dice = SellableReference::of('product', 'dice');
        $list->wish($dice, 3);
        self::assertSame([$list], $this->store->wishLists);

        $token = $this->lists->share($list);
        self::assertSame($list, $this->lists->shared($token));
        self::assertNull($this->lists->shared('not-a-token'));
        self::assertNull($this->lists->shared(SecretToken::create()[0]));

        $order = Order::open('2026-0009', Currency::of('CAD'));
        $line = $this->lists->buyFrom($list, $order, $dice, 2, PricingContext::of(1, new DateTimeImmutable()));
        self::assertSame(2, $line->quantity);
        self::assertSame($list->id, $order->wishList());

        // Sharing again gives a new link; the old one opens nothing.
        $again = $this->lists->share($list);
        self::assertNull($this->lists->shared($token));
        self::assertSame($list, $this->lists->shared($again));
    }

    public function testOnlyWhatIsStillWishedForIsBought(): void
    {
        $list = $this->lists->create('member-1', 'Birthday', new DateTimeImmutable());
        $dice = SellableReference::of('product', 'dice');
        $list->wish($dice, 2);
        $context = PricingContext::of(1, new DateTimeImmutable());

        foreach ([[$dice, 3, 'still wishes for 2 of product:dice'], [$dice, 0, 'still wishes for 2 of product:dice'], [SellableReference::of('product', 'mat'), 1, 'still wishes for 0 of product:mat']] as [$reference, $quantity, $message]) {
            try {
                $this->lists->buyFrom($list, Order::open('2026-0009', Currency::of('CAD')), $reference, $quantity, $context);
                self::fail('Refused: '.$message);
            } catch (InvalidValue $refused) {
                self::assertStringContainsString($message, $refused->getMessage());
            }
        }

        self::assertSame(2, $this->lists->buyFrom($list, Order::open('2026-0010', Currency::of('CAD')), $dice, 2, $context)->quantity);
    }

    public function testAnOrderBuysFromOneListOnly(): void
    {
        $first = $this->lists->create('member-1', 'One', new DateTimeImmutable());
        $second = $this->lists->create('member-2', 'Two', new DateTimeImmutable());
        $dice = SellableReference::of('product', 'dice');
        $first->wish($dice, 1);
        $second->wish($dice, 1);
        $order = Order::open('2026-0009', Currency::of('CAD'));
        $context = PricingContext::of(1, new DateTimeImmutable());
        $this->lists->buyFrom($first, $order, $dice, 1, $context);

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The order 2026-0009 already buys from another wish list.');

        $this->lists->buyFrom($second, $order, $dice, 1, $context);
    }

    public function testAPaidOrderCountsTowardsItsList(): void
    {
        $list = $this->lists->create('member-1', 'Birthday', new DateTimeImmutable());
        $dice = SellableReference::of('product', 'dice');
        $list->wish($dice, 3);
        $order = Order::open('2026-0009', Currency::of('CAD'));
        $this->lists->buyFrom($list, $order, $dice, 2, PricingContext::of(1, new DateTimeImmutable()));
        $this->modifier->add($order, SellableReference::of('product', 'mat'), 1, PricingContext::of(1, new DateTimeImmutable()));
        $this->orders->add($order);

        $this->lists->recordPurchase($this->purchased('2026-0009'));

        self::assertSame(2, $list->item($dice)?->bought);
        self::assertNull($list->item(SellableReference::of('product', 'mat')));
    }

    public function testAPurchaseOfNoListOrOfAListGoneChangesNothing(): void
    {
        $plain = Order::open('2026-0010', Currency::of('CAD'));
        $this->orders->add($plain);
        $gone = Order::open('2026-0011', Currency::of('CAD'));
        $this->modifier->add($gone, SellableReference::of('product', 'dice'), 1, PricingContext::of(1, new DateTimeImmutable()));
        $gone->buyFromWishList('0190a0b2-3c4d-7e5f-8a9b-0c1d2e3f4a5b');

        $this->orders->add($gone);

        $this->lists->recordPurchase($this->purchased('2026-0010'));
        $this->lists->recordPurchase($this->purchased('2026-0011'));
        $this->lists->recordPurchase($this->purchased('unknown'));

        self::assertSame([], $this->store->wishLists);
    }

    public function testALinkOpensItsOrderUntilItExpires(): void
    {
        $order = $this->placed();
        $links = $this->links();
        $at = new DateTimeImmutable('2026-11-11T10:00:00Z');

        [$link, $token] = $links->create($order, $at);

        self::assertSame([$link], $this->store->links);
        self::assertEquals(new DateTimeImmutable('2026-11-14T10:00:00Z'), $link->expiresAt);
        self::assertSame($order, $links->open($token, new DateTimeImmutable('2026-11-14T09:59:59Z')));
        self::assertNull($links->open($token, new DateTimeImmutable('2026-11-14T10:00:00Z')));
        self::assertNull($links->open('garbage', $at));
        self::assertNull($links->open(SecretToken::create()[0], $at));

        [$short] = $links->create($order, $at, 1);
        self::assertEquals(new DateTimeImmutable('2026-11-11T11:00:00Z'), $short->expiresAt);
        [$longest] = $links->create($order, $at, 720);
        self::assertEquals(new DateTimeImmutable('2026-12-11T10:00:00Z'), $longest->expiresAt);
    }

    public function testALinkOpensNothingOnceItsOrderIsPaidOrGone(): void
    {
        $payments = new InMemoryPayments();
        $order = $this->placed();
        $links = $this->links($payments);
        $at = new DateTimeImmutable('2026-11-11T10:00:00Z');
        [, $token] = $links->create($order, $at);

        new Payments([new FakeGateway('card')], $payments)->start($order, 'card', $at);
        self::assertNull($links->open($token, $at));

        $other = $this->placed('2026-0002');
        [, $orphan] = $links->create($other, $at);
        unset($this->orders->orders['2026-0002']);
        self::assertNull($links->open($orphan, $at));

        $cancelled = $this->placed('2026-0003');
        [, $late] = $links->create($cancelled, $at);
        $cancelled->cancel($at);
        self::assertNull($links->open($late, $at));
    }

    public function testRevokingClosesTheOpenLinksOnly(): void
    {
        $order = $this->placed();
        $links = $this->links();
        $at = new DateTimeImmutable('2026-11-11T10:00:00Z');
        [$expired] = $links->create($order, $at, 1);
        [, $token] = $links->create($order, $at);
        $this->placed('2026-0002');
        [$other] = $links->create($this->orders->orders['2026-0002'], $at);

        self::assertSame(1, $links->revokeAll($order, new DateTimeImmutable('2026-11-11T12:00:00Z')));
        self::assertNull($expired->revokedAt());
        self::assertNull($links->open($token, $at));
        self::assertNull($other->revokedAt());
    }

    public function testALinkLastsWithinItsBounds(): void
    {
        $links = $this->links();

        foreach ([0, 721] as $hours) {
            try {
                $links->create($this->placed(), new DateTimeImmutable(), $hours);
                self::fail('Refused: '.$hours);
            } catch (InvalidValue $refused) {
                self::assertSame('A payment link opens its order for 1 to 720 hours.', $refused->getMessage());
            }
        }

        self::assertSame([], $this->store->links);
    }

    public function testALinkIsMadeForAPlacedOrderThatOwesSomething(): void
    {
        $this->expectException(PaymentRefused::class);

        $this->links()->create(Order::open('2026-0005', Currency::of('CAD')), new DateTimeImmutable());
    }

    private function links(?InMemoryPayments $payments = null): PaymentLinks
    {
        return new PaymentLinks($this->store, $this->orders, new Payments([], $payments ?? new InMemoryPayments()));
    }

    private function placed(string $number = '2026-0001'): Order
    {
        $order = Order::open($number, Currency::of('CAD'));
        $this->modifier->add($order, SellableReference::of('product', 'dice'), 1, PricingContext::of(1, new DateTimeImmutable()));
        $order->place(new DateTimeImmutable());
        $this->orders->add($order);

        return $order;
    }

    private function purchased(string $number): OrderPurchased
    {
        return new OrderPurchased($number, null, null, Money::of(1, Currency::of('CAD')), new DateTimeImmutable());
    }

    public function testNoLinkIsMadeForACartEvenOneWithGoodsToPay(): void
    {
        $cart = Order::open('2026-0006', Currency::of('CAD'));
        $this->modifier->add($cart, SellableReference::of('product', 'dice'), 1, PricingContext::of(1, new DateTimeImmutable()));

        $this->expectException(PaymentRefused::class);

        $this->links()->create($cart, new DateTimeImmutable());
    }
}
