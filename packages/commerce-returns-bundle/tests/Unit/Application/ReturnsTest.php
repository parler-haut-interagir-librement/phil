<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Exception\InvalidNotification;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Payment\PaymentState;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\LineConfiguration;
use Phil\CommerceBundle\Domain\ValueObject\OptionDefinition;
use Phil\CommerceBundle\Domain\ValueObject\OptionSet;
use Phil\CommerceBundle\Domain\ValueObject\OptionValue;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Domain\ValueObject\TextConstraints;
use Phil\CommerceReturnsBundle\Application\Returns;
use Phil\CommerceReturnsBundle\Application\ReturnsGateway;
use Phil\CommerceReturnsBundle\Application\Settlement;
use Phil\CommerceReturnsBundle\Domain\Exception\ReturnRefused;
use Phil\CommerceReturnsBundle\Domain\Model\ReturnRequest;
use Phil\CommerceReturnsBundle\Domain\Rma\DeliveryRefund;
use Phil\CommerceReturnsBundle\Domain\Rma\ItemCondition;
use Phil\CommerceReturnsBundle\Domain\Rma\Resolution;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnReason;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnState;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnLine;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnPolicy;
use Phil\CommerceReturnsBundle\Tests\Unit\Support\CardGateway;
use Phil\CommerceReturnsBundle\Tests\Unit\Support\InMemoryOrders;
use Phil\CommerceReturnsBundle\Tests\Unit\Support\InMemoryPayments;
use Phil\CommerceReturnsBundle\Tests\Unit\Support\InMemoryReturns;
use Phil\CommerceReturnsBundle\Tests\Unit\Support\Recorders;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Returns::class)]
#[CoversClass(ReturnsGateway::class)]
#[CoversClass(Settlement::class)]
#[CoversClass(ReturnPolicy::class)]
final class ReturnsTest extends TestCase
{
    private InMemoryOrders $orders;

    private InMemoryReturns $returns;

    private CardGateway $card;

    private Payments $payments;

    private Recorders $recorders;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->orders = new InMemoryOrders();
        $this->returns = new InMemoryReturns();
        $paymentStore = new InMemoryPayments();
        $this->card = new CardGateway();
        $this->payments = new Payments([$this->card, new ReturnsGateway()], $paymentStore);
        $this->recorders = new Recorders();
        $this->at = new DateTimeImmutable('2026-11-03 10:00:00');
    }

    public function testAReturnIsWorthItsShareOfTheLinesPromotionsAndTaxesIncluded(): void
    {
        $order = $this->order();

        $return = $this->service()->request($order, [['line' => 'product:TEA', 'quantity' => 2, 'reason' => ReturnReason::Defective], ['line' => 'product:MUG', 'quantity' => 1, 'reason' => ReturnReason::ChangedMind]], $this->at);

        self::assertSame($return, $this->returns->findReturn($return->id));
        self::assertSame([['product:TEA', 2, 1_670, 3], ['product:MUG', 1, 1_380, 1]], array_map(static fn (ReturnLine $line): array => [$line->line, $line->quantity, $line->linePaid, $line->lineQuantity], $return->lines()));
        self::assertSame([100_000, 'CAD'], [$return->restockingFee, $return->currency()->code]);
    }

    public function testWhatIsReturnedCannotBeReturnedAgainUntilFreed(): void
    {
        $order = $this->order();
        $first = $this->service()->request($order, [['line' => 'product:TEA', 'quantity' => 2, 'reason' => ReturnReason::Other]], $this->at);

        try {
            $this->service()->request($order, [['line' => 'product:TEA', 'quantity' => 2, 'reason' => ReturnReason::Other]], $this->at);
            self::fail('Units were returned twice.');
        } catch (ReturnRefused $returnRefused) {
            self::assertSame('2 of product:TEA cannot come back: 1 can.', $returnRefused->getMessage());
        }

        self::assertSame(1, $this->service()->request($order, [['line' => 'product:TEA', 'quantity' => 1, 'reason' => ReturnReason::Other]], $this->at)->quantityOf('product:TEA'));
        $this->service()->cancel($first->id, $this->at);
        self::assertSame(2, $this->service()->request($order, [['line' => 'product:TEA', 'quantity' => 2, 'reason' => ReturnReason::Other]], $this->at)->quantityOf('product:TEA'));
    }

    /**
     * What every holding return holds adds up per line, whatever the returns that no longer hold before them.
     */
    public function testTheUnitsHeldAddUpPerLineAcrossTheHoldingReturns(): void
    {
        $order = $this->order();
        $cancelled = $this->service()->request($order, [['line' => 'product:TEA', 'quantity' => 3, 'reason' => ReturnReason::Other], ['line' => 'product:MUG', 'quantity' => 1, 'reason' => ReturnReason::Other]], $this->at);
        $this->service()->cancel($cancelled->id, $this->at);
        $this->service()->request($order, [['line' => 'product:TEA', 'quantity' => 1, 'reason' => ReturnReason::Other], ['line' => 'product:MUG', 'quantity' => 1, 'reason' => ReturnReason::Other]], $this->at);
        $this->service()->request($order, [['line' => 'product:TEA', 'quantity' => 1, 'reason' => ReturnReason::Other]], $this->at);

        try {
            $this->service()->request($order, [['line' => 'product:TEA', 'quantity' => 1, 'reason' => ReturnReason::Other], ['line' => 'product:MUG', 'quantity' => 1, 'reason' => ReturnReason::Other]], $this->at);
            self::fail('The mug was returned twice.');
        } catch (ReturnRefused $returnRefused) {
            self::assertSame('1 of product:MUG cannot come back: 0 can.', $returnRefused->getMessage());
        }

        $last = $this->service()->request($order, [['line' => 'product:TEA', 'quantity' => 1, 'reason' => ReturnReason::Other]], $this->at);
        self::assertSame([2], array_map(static fn (ReturnLine $line): int => $line->returnedBefore, $last->lines()));
    }

    public function testOnlyAPlacedOrderWithinItsWindowReturnsItsOwnLines(): void
    {
        $order = $this->order();

        try {
            $this->service()->request($order, [['line' => 'product:CUP', 'quantity' => 1, 'reason' => ReturnReason::Other]], $this->at);
            self::fail('A line the order lacks was returned.');
        } catch (ReturnRefused $returnRefused) {
            self::assertSame('The order A-1 has no line product:CUP.', $returnRefused->getMessage());
        }

        self::assertSame(ReturnState::Requested, $this->service()->request($order, [['line' => 'product:MUG', 'quantity' => 1, 'reason' => ReturnReason::Other]], $this->at->modify('+30 days'))->state());

        try {
            $this->service()->request($order, [['line' => 'product:TEA', 'quantity' => 1, 'reason' => ReturnReason::Other]], $this->at->modify('+30 days +1 second'));
            self::fail('A late return was accepted.');
        } catch (ReturnRefused $returnRefused) {
            self::assertSame('The order A-1 is past its 30 days of returns.', $returnRefused->getMessage());
        }

        $cart = Order::open('A-2', Currency::of('CAD'));
        $this->expectException(ReturnRefused::class);
        $this->expectExceptionMessageIsOrContains('The order A-2 is not placed: nothing of it can come back.');

        $this->service()->request($cart, [['line' => 'product:TEA', 'quantity' => 1, 'reason' => ReturnReason::Other]], $this->at);
    }

    public function testTheResellableUnitsGoBackOnTheShelf(): void
    {
        $return = $this->authorized();

        $this->service()->receive($return->id, ['product:TEA' => ItemCondition::Resellable, 'product:MUG' => ItemCondition::Damaged], $this->at);

        self::assertSame([['product:TEA', 2, $return->id]], $this->recorders->restocked);
        self::assertSame(ReturnState::Received, $return->state());
    }

    public function testALineWhoseIdHoldsAColonIsRestocked(): void
    {
        $cad = Currency::of('CAD');
        $order = Order::open('A-9', $cad);
        $order->add(Sellable::of(SellableReference::of('product', 'TEA:GREEN'), 'Green tea', Money::of(500, $cad), 'standard'), 1, Money::of(500, $cad));
        $order->place($this->at);

        $this->orders->add($order);
        $return = $this->service()->request($order, [['line' => 'product:TEA:GREEN', 'quantity' => 1, 'reason' => ReturnReason::Other]], $this->at);
        $this->service()->authorize($return->id, $this->at);

        $this->service()->receive($return->id, ['product:TEA:GREEN' => ItemCondition::Resellable], $this->at);

        self::assertSame([['product:TEA:GREEN', 1, $return->id]], $this->recorders->restocked);
    }

    public function testAConfiguredLineIsRestockedWithItsFingerprint(): void
    {
        $cad = Currency::of('CAD');
        $order = Order::open('A-8', $cad);
        $shirt = Sellable::of(SellableReference::of('product', 'SHIRT'), 'Shirt', Money::of(2_500, $cad), 'standard', OptionSet::of(OptionDefinition::text('name', 'Name', TextConstraints::of(20))));
        $line = $order->add($shirt, 2, Money::of(2_500, $cad), LineConfiguration::of(['name' => OptionValue::chosen('Bob')]))->reference;
        $order->place($this->at);

        $this->orders->add($order);
        $return = $this->service()->request($order, [['line' => $line->value(), 'quantity' => 1, 'reason' => ReturnReason::Other]], $this->at);
        $this->service()->authorize($return->id, $this->at);

        $this->service()->receive($return->id, [$line->value() => ItemCondition::Resellable], $this->at);

        self::assertNotNull($line->configuration);
        self::assertSame([[$line->value(), 1, $return->id]], $this->recorders->restocked);
    }

    public function testARefundGoesBackToThePaymentsTheLatestFirst(): void
    {
        $return = $this->received();
        $first = $this->pay($this->orders->orders['A-1'], 1_500);
        $second = $this->pay($this->orders->orders['A-1'], 1_000);
        $failed = $this->payments->start($this->orders->orders['A-1'], 'card', $this->at, Money::of(100, Currency::of('CAD')))[0];

        $settlement = $this->service()->refund($return->id, $this->at);

        self::assertSame([Resolution::Refund, ReturnState::Resolved], [$return->resolution(), $return->state()]);
        self::assertSame(2_355, $settlement->value->minorUnits);
        self::assertSame([[$second->id, 1_000], [$first->id, 1_355]], array_map(static fn (array $refund): array => [$refund[0]->id, $refund[1]->minorUnits], $settlement->refunds));
        self::assertSame([2_355, 0, 0, null], [$settlement->refunded()->minorUnits, $settlement->shortfall->minorUnits, $settlement->credited->minorUnits, $settlement->exchange]);
        self::assertSame([PaymentState::Refunded, 1_355, 0], [$second->state(), $first->refunded->minorUnits, $failed->refunded->minorUnits]);
        self::assertSame([$return->id.':'.$second->id, $return->id.':'.$first->id], $this->card->idempotencyKeys);
    }

    public function testWhatThePaymentsCannotTakeBackIsTheShortfall(): void
    {
        $return = $this->received();
        $this->pay($this->orders->orders['A-1'], 1_000);

        $settlement = $this->service()->refund($return->id, $this->at);

        self::assertSame([1_000, 1_355], [$settlement->refunded()->minorUnits, $settlement->shortfall->minorUnits]);
    }

    /**
     * The value an earlier return put into this order through an exchange is not refunded as money: the order's other
     * payments are refunded, and what they cannot take is the shortfall.
     */
    public function testAnExchangePaymentIsNotRefundedAsMoney(): void
    {
        $first = $this->received();
        $order = $this->orders->orders['A-1'];
        $card = $this->pay($order, 300);
        $exchange = $this->service()->exchange($first->id, $order, $this->at)->exchange;
        self::assertNotNull($exchange);
        $second = $this->service()->request($order, [['line' => 'product:TEA', 'quantity' => 1, 'reason' => ReturnReason::Defective]], $this->at);
        $this->service()->authorize($second->id, $this->at);
        $this->service()->receive($second->id, ['product:TEA' => ItemCondition::Resellable], $this->at);

        $settlement = $this->service()->refund($second->id, $this->at);

        self::assertSame(557, $settlement->value->minorUnits);
        self::assertSame([[$card->id, 300]], array_map(static fn (array $refund): array => [$refund[0]->id, $refund[1]->minorUnits], $settlement->refunds));
        self::assertSame([257, 0], [$settlement->shortfall->minorUnits, $exchange->refunded->minorUnits]);
    }

    /**
     * A processor that fails does not undo the refunds already sent: the failure is reported, and its part goes to
     * the next payment or to the shortfall.
     */
    public function testAFailingGatewayIsReportedAndPassedOver(): void
    {
        $return = $this->received();
        $first = $this->pay($this->orders->orders['A-1'], 2_000);
        $second = $this->pay($this->orders->orders['A-1'], 1_000);
        $this->card->failing = $second->id;

        $settlement = $this->service()->refund($return->id, $this->at);

        self::assertSame(ReturnState::Resolved, $return->state());
        self::assertSame([[$first->id, 2_000]], array_map(static fn (array $refund): array => [$refund[0]->id, $refund[1]->minorUnits], $settlement->refunds));
        self::assertSame(355, $settlement->shortfall->minorUnits);
        self::assertCount(1, $settlement->failures);
        [$payment, $amount, $exception] = $settlement->failures[0];
        self::assertSame([$second->id, 1_000, 'The processor is unreachable.'], [$payment->id, $amount->minorUnits, $exception->getMessage()]);
    }

    /**
     * Three returns of one unit each of a line of 16.70 for 3 give 5.57, 5.56 and 5.57: the line, to the cent.
     */
    public function testReturnsOfOneLineInPartsAddUpToTheLine(): void
    {
        $order = $this->order();
        $values = [];

        for ($i = 0; $i < 3; ++$i) {
            $return = $this->service()->request($order, [['line' => 'product:TEA', 'quantity' => 1, 'reason' => ReturnReason::Defective]], $this->at);
            $values[] = $return->value()->minorUnits;
        }

        self::assertSame([557, 556, 557], $values);
        self::assertSame(1_670, array_sum($values));
    }

    public function testAReturnWhoseOrderIsGoneRefundsNothing(): void
    {
        $return = $this->received();
        $this->orders->orders = [];

        self::assertSame([[], 2_355], [$this->service()->refund($return->id, $this->at)->refunds, $this->service()->get($return->id)->value()->minorUnits]);
    }

    public function testStoreCreditIsGrantedForTheValue(): void
    {
        $return = $this->received();

        $settlement = $this->service()->credit($return->id, 'ada', $this->at);

        self::assertSame([2_355, 2_355, [], 0], [$settlement->value->minorUnits, $settlement->credited->minorUnits, $settlement->refunds, $settlement->shortfall->minorUnits]);
        self::assertSame([['ada', 2_355, 'Return '.$return->id, 'A-1']], $this->recorders->credited);
        self::assertSame(Resolution::StoreCredit, $return->resolution());
    }

    public function testNothingWorthNothingIsCredited(): void
    {
        $return = $this->authorized();
        $this->service()->receive($return->id, ['product:TEA' => ItemCondition::Missing, 'product:MUG' => ItemCondition::Missing], $this->at);

        self::assertSame(0, $this->service()->credit($return->id, 'ada', $this->at)->credited->minorUnits);
        self::assertSame([], $this->recorders->credited);
    }

    public function testStoreCreditNeedsTheStoredValueModule(): void
    {
        $return = $this->received();

        $this->expectException(ReturnRefused::class);
        $this->expectExceptionMessageIsOrContains('Store credit needs phil/commerce-stored-value-bundle, and a customer to credit.');

        new Returns($this->orders, $this->returns, $this->payments, ReturnPolicy::of(restockingFee: 0, deliveryRefund: DeliveryRefund::Never))->credit($return->id, 'ada', $this->at);
    }

    public function testAnExchangePaysTheNewOrderAndRefundsTheRest(): void
    {
        $return = $this->received();
        $this->pay($this->orders->orders['A-1'], 3_105);
        $new = $this->placed('B-1', 2_000);

        $settlement = $this->service()->exchange($return->id, $new, $this->at);

        self::assertSame(Resolution::Exchange, $return->resolution());
        self::assertSame(['exchange', 2_000, PaymentState::Completed], [$settlement->exchange?->method, $settlement->exchange?->amount->minorUnits, $settlement->exchange?->state()]);
        self::assertSame([355, 0], [$settlement->refunded()->minorUnits, $settlement->shortfall->minorUnits]);
        self::assertTrue($this->payments->summary($new)->payable()->isZero());
    }

    public function testAnExchangeForMoreLeavesTheRestToPay(): void
    {
        $return = $this->received();
        $new = $this->placed('B-1', 5_000);

        $settlement = $this->service()->exchange($return->id, $new, $this->at);

        self::assertSame([2_355, 0], [$settlement->exchange?->amount->minorUnits, $settlement->refunded()->minorUnits]);
        self::assertSame(2_645, $this->payments->summary($new)->payable()->minorUnits);
    }

    public function testAnExchangeWithAPaidOrderRefundsEverything(): void
    {
        $return = $this->received();
        $this->pay($this->orders->orders['A-1'], 3_105);
        $new = $this->placed('B-1', 500);
        $this->pay($new, 500);

        $settlement = $this->service()->exchange($return->id, $new, $this->at);

        self::assertNull($settlement->exchange);
        self::assertSame(2_355, $settlement->refunded()->minorUnits);
    }

    public function testAnExchangeNeedsAPlacedOrderInTheSameCurrency(): void
    {
        $cart = Order::open('B-1', Currency::of('CAD'));
        $dollars = Order::open('B-2', Currency::of('USD'));
        $dollars->add(Sellable::of(SellableReference::of('product', 'X'), 'X', Money::of(100, Currency::of('USD')), 'standard'), 1, Money::of(100, Currency::of('USD')));
        $dollars->place($this->at);

        foreach ([$cart, $dollars] as $order) {
            $return = $this->received('A-'.$order->number);

            try {
                $this->service()->exchange($return->id, $order, $this->at);
                self::fail('A bad exchange was accepted.');
            } catch (ReturnRefused $refused) {
                self::assertSame(\sprintf('The order %s cannot take an exchange: it is not placed, or in another currency.', $order->number), $refused->getMessage());
            }

            self::assertSame(ReturnState::Received, $return->state());
        }
    }

    public function testRejectingAndCancellingGoThroughTheService(): void
    {
        $order = $this->order();
        $rejected = $this->service()->request($order, [['line' => 'product:TEA', 'quantity' => 1, 'reason' => ReturnReason::Other]], $this->at);
        $cancelled = $this->service()->request($order, [['line' => 'product:MUG', 'quantity' => 1, 'reason' => ReturnReason::Other]], $this->at);

        self::assertSame([ReturnState::Rejected, 'Opened'], [$this->service()->reject($rejected->id, 'Opened', $this->at)->state(), $rejected->note()]);
        self::assertSame(ReturnState::Cancelled, $this->service()->cancel($cancelled->id, $this->at)->state());
        self::assertSame(ReturnState::Authorized, $this->service()->authorize($this->service()->request($order, [['line' => 'product:TEA', 'quantity' => 1, 'reason' => ReturnReason::Other]], $this->at)->id, $this->at)->state());

        $this->expectException(ReturnRefused::class);
        $this->expectExceptionMessageIsOrContains('No return is known as nope.');

        $this->service()->get('nope');
    }

    public function testTheSettingsAreBounded(): void
    {
        foreach ([[-1, 0, null], [0, -1, null], [0, 1_000_001, null], [0, 0, 0]] as [$days, $fee, $expiry]) {
            try {
                ReturnPolicy::of($days, false, $fee, DeliveryRefund::Never, $expiry);
                self::fail('Bad settings were accepted.');
            } catch (InvalidValue $invalid) {
                self::assertSame('A return window is zero days or more, a restocking fee 0 to 1000000 parts per million, and an expiry one day or more.', $invalid->getMessage());
            }
        }

        $order = $this->order();
        self::assertSame(1, ReturnPolicy::of(expiryDays: 1)->expiryDays);
        $default = ReturnPolicy::of();
        self::assertSame([30, false, 0, DeliveryRefund::ShopsFault, null], [$default->windowDays, $default->windowFromShipment, $default->restockingFee, $default->deliveryRefund, $default->expiryDays]);
        self::assertSame(1_000_000, new Returns($this->orders, $this->returns, $this->payments, ReturnPolicy::of(0, false, 1_000_000))->request($order, [['line' => 'product:TEA', 'quantity' => 1, 'reason' => ReturnReason::Other]], $this->at)->restockingFee);
    }

    public function testTheExchangeGatewayIsItsOwnProcessor(): void
    {
        $gateway = new ReturnsGateway();
        $order = $this->placed('B-1', 1_000);
        $payment = Payment::create($order, 'exchange', Money::of(500, Currency::of('CAD')), $this->at);

        self::assertTrue($gateway->supports('exchange'));
        self::assertFalse($gateway->supports('card'));
        self::assertSame($payment->id, $gateway->start($payment, $order)->externalReference);

        $this->expectException(InvalidNotification::class);
        $this->expectExceptionMessageIsOrContains('An exchange is its own processor: it sends no notification.');

        $gateway->read('{}', []);
    }

    public function testAnExchangePaymentIsRefundedAtOnce(): void
    {
        $return = $this->received();
        $new = $this->placed('B-1', 1_000);
        $exchange = $this->service()->exchange($return->id, $new, $this->at)->exchange;
        self::assertNotNull($exchange);

        $this->payments->refund($exchange, Money::of(400, Currency::of('CAD')), 'refund-1');
        $this->payments->refund($exchange, Money::of(400, Currency::of('CAD')), 'refund-1');

        self::assertSame(400, $exchange->refunded->minorUnits);

        $this->payments->refund($exchange, Money::of(100, Currency::of('CAD')), 'refund-2');

        self::assertSame(500, $exchange->refunded->minorUnits);
    }

    private function service(): Returns
    {
        return new Returns($this->orders, $this->returns, $this->payments, ReturnPolicy::of(30, false, 100_000, DeliveryRefund::Never), [$this->recorders], $this->recorders);
    }

    /**
     * TEA: 3 at 5.00 less 1.50 of promotion, plus 3.20 of tax, so 16.70; MUG: 12.00 plus 1.80 of tax, so 13.80; a
     * delivery of 10.00 on no line, never given back.
     */
    private function order(string $number = 'A-1'): Order
    {
        $cad = Currency::of('CAD');
        $order = Order::open($number, $cad);
        $tea = SellableReference::of('product', 'TEA');
        $mug = SellableReference::of('product', 'MUG');
        $order->add(Sellable::of($tea, 'Tea', Money::of(500, $cad), 'standard'), 3, Money::of(500, $cad));
        $order->add(Sellable::of($mug, 'Mug', Money::of(1_200, $cad), 'standard'), 1, Money::of(1_200, $cad));
        $order->replaceAdjustments(AdjustmentType::Promotion, [Adjustment::of(AdjustmentType::Promotion, '10 %', Money::of(-150, $cad), $tea)]);
        $order->replaceAdjustments(AdjustmentType::Tax, [
            Adjustment::of(AdjustmentType::Tax, 'Tax', Money::of(320, $cad), $tea),
            Adjustment::of(AdjustmentType::Tax, 'Tax', Money::of(180, $cad), $mug),
            Adjustment::of(AdjustmentType::Tax, 'Included', Money::of(999, $cad), $mug, true),
        ]);
        $order->replaceAdjustments(AdjustmentType::Shipping, [Adjustment::of(AdjustmentType::Shipping, 'Delivery', Money::of(1_000, $cad))]);
        $order->place($this->at);

        $this->orders->add($order);

        return $order;
    }

    private function placed(string $number, int $price): Order
    {
        $cad = Currency::of('CAD');
        $order = Order::open($number, $cad);
        $order->add(Sellable::of(SellableReference::of('product', 'NEW'), 'New', Money::of($price, $cad), 'standard'), 1, Money::of($price, $cad));
        $order->place($this->at);

        $this->orders->add($order);

        return $order;
    }

    private function authorized(string $number = 'A-1'): ReturnRequest
    {
        $return = $this->service()->request($this->order($number), [['line' => 'product:TEA', 'quantity' => 2, 'reason' => ReturnReason::Defective], ['line' => 'product:MUG', 'quantity' => 1, 'reason' => ReturnReason::ChangedMind]], $this->at);

        return $this->service()->authorize($return->id, $this->at);
    }

    private function received(string $number = 'A-1'): ReturnRequest
    {
        $return = $this->authorized($number);

        return $this->service()->receive($return->id, ['product:TEA' => ItemCondition::Resellable, 'product:MUG' => ItemCondition::Resellable], $this->at);
    }

    private function pay(Order $order, int $amount): Payment
    {
        [$payment] = $this->payments->start($order, 'card', $this->at, Money::of($amount, Currency::of('CAD')));
        $this->card->capture($payment);

        return $payment;
    }
}
