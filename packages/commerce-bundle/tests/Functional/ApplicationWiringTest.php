<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Functional;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Application\Port\PaymentAttemptStateStore;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Exception\UnknownSellable;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Service\PaymentAttemptLimiter;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceBundle\Domain\ValueObject\PaymentAttemptKey;
use Phil\CommerceBundle\Domain\ValueObject\PaymentAttemptPolicy;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Tests\Functional\App\InMemoryPaymentAttemptStateStore;
use Phil\Money\Currency;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The order modifier with what the application plugs into it, and the payment attempt limiter, wired by the application from its own
 * parameters as the bundle's documentation shows, then used the way a checkout would.
 */
#[CoversClass(OrderModifier::class)]
#[CoversClass(PaymentAttemptLimiter::class)]
#[CoversClass(PaymentAttemptPolicy::class)]
final class ApplicationWiringTest extends FunctionalTestCase
{
    private const string START = '2026-01-01T12:00:00Z';

    /**
     * A sale built through the container: the sellable resolver and the price list plug in by autoconfiguration,
     * the core taxes each line with the configured rates. 19.99 and 10.00 at a quarter off are 14.99 and 7.50;
     * GST and QST on each line.
     */
    public function testAnOrderIsPricedAndTaxedByWhatThePortsGive(): void
    {
        $modifier = $this->service(OrderModifier::class, OrderModifier::class);
        $order = Order::open('2026-0001', Currency::of('CAD'));
        $context = PricingContext::of(1, new DateTimeImmutable(self::START), 'wholesale');

        $modifier->add($order, SellableReference::of('product', 'dice'), 1, $context);
        $modifier->add($order, SellableReference::of('product', 'pdf'), 1, $context);
        $modifier->changeTaxZone($order, 'QC');

        self::assertSame([1_499, 750], array_map(static fn (OrderLine $line): int => $line->unitPrice->minorUnits, $order->lines()));
        self::assertSame([75, 150, 38, 75], array_map(static fn (Adjustment $tax): int => $tax->amount->minorUnits, $order->adjustments(AdjustmentType::Tax)));
        self::assertSame(2_249 + 338, $order->total()->minorUnits);

        $modifier->changeTaxZone($order, 'ON');

        self::assertSame(['HST'], array_map(static fn (Adjustment $tax): string => $tax->label, $order->adjustments(AdjustmentType::Tax)));
    }

    public function testAProductNoModuleSellsIsRefused(): void
    {
        $this->expectException(UnknownSellable::class);

        $this->service(OrderModifier::class, OrderModifier::class)->add(
            Order::open('2026-0002', Currency::of('CAD')),
            SellableReference::of('offer', 'gold'),
            1,
            PricingContext::of(1, new DateTimeImmutable(self::START)),
        );
    }

    /**
     * Each attempt is decided, recorded and saved with the policy's window as its time-to-live; the configured
     * budget spent, the next attempt waits until the state expires.
     */
    public function testTheLimiterSpendsTheConfiguredBudgetThroughTheStore(): void
    {
        $limiter = $this->service(PaymentAttemptLimiter::class, PaymentAttemptLimiter::class);
        $store = $this->service(PaymentAttemptStateStore::class, InMemoryPaymentAttemptStateStore::class);
        $key = PaymentAttemptKey::forOrder('order-1');

        foreach ([0, 1, 2] as $second) {
            self::assertTrue($limiter->decide($store->find($key), $this->moment($second))->isAllowed());
            $store->save($key, $limiter->record($store->find($key), $this->moment($second), false), $limiter->timeToLiveSeconds());
        }

        $decision = $limiter->decide($store->find($key), $this->moment(3));

        self::assertFalse($decision->isAllowed());
        self::assertSame(899, $decision->retryAfterSeconds($this->moment(3)));
        self::assertSame(900, $store->saved[$key->value][1]);
    }

    private function moment(int $seconds): DateTimeImmutable
    {
        return new DateTimeImmutable(self::START)->modify(\sprintf('+%d seconds', $seconds));
    }
}
