<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Application;

use Closure;
use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Application\OrderAmendments;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Application\Port\OrderProcessor;
use Phil\CommerceBundle\Application\Quotes;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Exception\OrderLocked;
use Phil\CommerceBundle\Domain\Exception\QuoteRefused;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Receipt;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\Order\QuoteState;
use Phil\CommerceBundle\Domain\Payment\PaymentEvent;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\Issuer;
use Phil\CommerceBundle\Domain\ValueObject\OrderPayments;
use Phil\CommerceBundle\Domain\ValueObject\PaymentNotification;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Tests\Unit\Support\FakeGateway;
use Phil\CommerceBundle\Tests\Unit\Support\InMemoryPayments;
use Phil\CommerceBundle\Tests\Unit\Support\InMemoryQuotes;
use Phil\CommerceBundle\Tests\Unit\Support\InMemoryReceipts;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Quotes::class)]
#[CoversClass(OrderAmendments::class)]
#[CoversClass(OrderPayments::class)]
final class QuotesTest extends TestCase
{
    private DateTimeImmutable $at;

    private InMemoryQuotes $stored;

    private int $fee = 100;

    protected function setUp(): void
    {
        $this->at = new DateTimeImmutable('2026-11-03 10:00:00');
        $this->stored = new InMemoryQuotes();
    }

    public function testAQuoteIsRecomputedFrozenThenPlacedAtItsPrices(): void
    {
        $order = $this->cart();
        $quotes = $this->quotes();

        $quote = $quotes->issue($order, 'ada', $this->at, $this->at->modify('+30 days'));

        self::assertSame($quote, $this->stored->findQuote('1'));
        self::assertSame([OrderState::Quoted, 1_100], [$order->state(), $order->total()->minorUnits]);

        $this->fee = 500;
        self::assertSame($quote, $quotes->accept($order, $this->at->modify('+1 day')));
        self::assertSame([QuoteState::Accepted, OrderState::Placed, 1_100], [$quote->state(), $order->state(), $order->total()->minorUnits]);
    }

    public function testADeclinedQuoteIsACartAgain(): void
    {
        $order = $this->cart();
        $quotes = $this->quotes();
        $quotes->issue($order, 'ada', $this->at, $this->at->modify('+30 days'));

        $quote = $quotes->decline($order, $this->at);

        self::assertSame([QuoteState::Declined, OrderState::Cart], [$quote->state(), $order->state()]);
    }

    public function testAnExpiredQuoteLeavesItsOrderQuoted(): void
    {
        $order = $this->cart();
        $quotes = $this->quotes();
        $quotes->issue($order, 'ada', $this->at, $this->at->modify('+1 day'));

        try {
            $quotes->accept($order, $this->at->modify('+2 days'));
            self::fail('An expired quote was accepted.');
        } catch (QuoteRefused) {
        }

        self::assertSame(OrderState::Quoted, $order->state());
    }

    public function testAnOrderWithoutQuoteIsSaidSo(): void
    {
        $this->expectException(QuoteRefused::class);
        $this->expectExceptionMessageIsOrContains('No quote is known for the order 1.');

        $this->quotes()->decline($this->cart(), $this->at);
    }

    public function testAPlacedOrderIsNotQuoted(): void
    {
        $order = $this->cart();
        $order->place($this->at);

        try {
            $this->quotes()->issue($order, 'ada', $this->at, $this->at->modify('+1 day'));
            self::fail('A placed order was quoted.');
        } catch (OrderLocked) {
        }

        self::assertSame([], $this->stored->quotes);
    }

    public function testAnOrderIsAmendedUntilItsReceiptIsIssued(): void
    {
        $payments = new InMemoryPayments();
        $receipts = new InMemoryReceipts();
        $order = $this->cart();
        $order->place($this->at);

        $amendments = new OrderAmendments(new Payments([new FakeGateway()], $payments), $receipts);

        $amendments->reopen($order);
        self::assertSame(OrderState::Cart, $order->state());

        $order->place($this->at);
        $receipts->add(Receipt::issue('R-1', $order, Issuer::of('Shop'), $this->at));
        $this->expectException(QuoteRefused::class);
        $this->expectExceptionMessageIsOrContains('The order 1 has its receipt: it no longer changes; credit it instead.');

        $amendments->reopen($order);
    }

    public function testWhatWasPaidBeyondTheAmendedTotalIsOverpaid(): void
    {
        $repository = new InMemoryPayments();
        $payments = new Payments([new FakeGateway()], $repository);
        $amendments = new OrderAmendments($payments, new InMemoryReceipts());
        $order = $this->cart();
        $order->place($this->at);
        self::assertSame(0, $amendments->overpaid($order)->minorUnits);

        [$payment] = $payments->start($order, 'card', $this->at);
        $payment->apply(PaymentNotification::of('n1', (string) $payment->externalReference(), PaymentEvent::Captured));
        self::assertSame(0, $amendments->overpaid($order)->minorUnits);

        $amendments->reopen($order);
        $order->remove(SellableReference::of('product', 'A'));
        $order->add(Sellable::of(SellableReference::of('product', 'B'), 'B', Money::of(300, Currency::of('CAD')), 'standard'), 1, Money::of(300, Currency::of('CAD')));
        $order->place($this->at);

        self::assertSame(700, $amendments->overpaid($order)->minorUnits);
        self::assertSame(0, $payments->summary($order)->payable()->minorUnits);

        $failed = $payments->start($this->cartPlaced('2'), 'card', $this->at)[0];
        $failed->apply(PaymentNotification::of('n2', (string) $failed->externalReference(), PaymentEvent::Failed));
        self::assertSame(0, $amendments->overpaid($this->cartPlaced('3'))->minorUnits);
    }

    private function cartPlaced(string $number): Order
    {
        $order = $this->cart($number);
        $order->place($this->at);

        return $order;
    }

    private function quotes(): Quotes
    {
        $fee = fn (): int => $this->fee;
        $processor = new readonly class($fee) implements OrderProcessor {
            /**
             * @param Closure(): int $fee
             */
            public function __construct(private Closure $fee)
            {
            }

            #[Override]
            public function process(Order $order): void
            {
                $order->replaceAdjustments(AdjustmentType::Fee, [Adjustment::of(AdjustmentType::Fee, 'Handling', Money::of(($this->fee)(), $order->currency))]);
            }
        };

        return new Quotes(new OrderModifier([], [], [$processor]), $this->stored);
    }

    private function cart(string $number = '1'): Order
    {
        $order = Order::open($number, Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'A'), 'A', Money::of(1_000, Currency::of('CAD')), 'standard'), 1, Money::of(1_000, Currency::of('CAD')));

        return $order;
    }
}
