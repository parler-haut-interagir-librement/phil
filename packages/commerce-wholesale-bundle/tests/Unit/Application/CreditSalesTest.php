<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Domain\Exception\InvalidNotification;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Payment\PaymentEvent;
use Phil\CommerceBundle\Domain\Payment\PaymentState;
use Phil\CommerceBundle\Domain\ValueObject\PaymentNotification;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceWholesaleBundle\Application\AccountGateway;
use Phil\CommerceWholesaleBundle\Application\CreditSales;
use Phil\CommerceWholesaleBundle\Domain\Exception\WholesaleRefused;
use Phil\CommerceWholesaleBundle\Tests\Unit\Support\InMemoryCredit;
use Phil\CommerceWholesaleBundle\Tests\Unit\Support\InMemoryPayments;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreditSales::class)]
#[CoversClass(AccountGateway::class)]
final class CreditSalesTest extends TestCase
{
    private InMemoryCredit $credit;

    private InMemoryPayments $paymentStore;

    private Payments $payments;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->credit = new InMemoryCredit();
        $this->paymentStore = new InMemoryPayments();
        $this->payments = new Payments([new AccountGateway($this->credit)], $this->paymentStore);
        $this->at = new DateTimeImmutable('2026-11-03 10:00:00');
    }

    public function testAnOrderIsBoughtOnAccount(): void
    {
        $sales = $this->sales();
        $account = $sales->open('acme', $this->cad(10_000), 30);
        self::assertSame($account, $sales->account('acme'));

        $receivable = $sales->charge($this->order(4_000), 'acme', $this->at);

        $payment = $this->paymentStore->payments[0];
        self::assertSame(['on_account', 4_000, PaymentState::Completed, $payment->id], [$payment->method, $payment->amount->minorUnits, $payment->state(), $payment->externalReference()]);
        self::assertSame(['acme', 'A-1', $payment->id, 4_000, '2026-12-03'], [$receivable->buyer, $receivable->orderNumber, $receivable->payment, $receivable->amount->minorUnits, $receivable->dueAt->format('Y-m-d')]);
        self::assertSame([$receivable], $this->credit->receivables);
        self::assertSame(4_000, $account->owed->minorUnits);
    }

    public function testPartOfAnOrderIsBoughtOnAccount(): void
    {
        $sales = $this->sales();
        $sales->open('acme', $this->cad(10_000), 30);

        self::assertSame(1_000, $sales->charge($this->order(4_000), 'acme', $this->at, $this->cad(1_000))->amount->minorUnits);
    }

    public function testBeyondTheLimitNothingIsCharged(): void
    {
        $sales = $this->sales();
        $sales->open('acme', $this->cad(3_000), 30);

        try {
            $sales->charge($this->order(4_000), 'acme', $this->at);
            self::fail('The limit was passed.');
        } catch (WholesaleRefused $wholesaleRefused) {
            self::assertSame('40.00 on account goes beyond the credit of acme: 30.00 is available.', $wholesaleRefused->getMessage());
        }

        self::assertSame([[], []], [$this->paymentStore->payments, $this->credit->receivables]);
    }

    public function testABuyerWithoutAccountIsSaidSo(): void
    {
        $this->expectException(WholesaleRefused::class);
        $this->expectExceptionMessageIsOrContains('nobody has no open credit account.');

        $this->sales()->charge($this->order(1_000), 'nobody', $this->at);
    }

    public function testTheBuyersPaymentsLowerWhatIsOwed(): void
    {
        $sales = $this->sales();
        $account = $sales->open('acme', $this->cad(10_000), 30);
        $receivable = $sales->charge($this->order(4_000), 'acme', $this->at);

        self::assertSame(1_500, $sales->receive($receivable->id, $this->cad(1_500))->minorUnits);
        self::assertSame(2_500, $sales->receive($receivable->id, $this->cad(9_000))->minorUnits);
        self::assertSame(0, $sales->receive($receivable->id, $this->cad(1))->minorUnits);
        self::assertSame([0, 0], [$account->owed->minorUnits, $receivable->outstanding()->minorUnits]);

        $this->expectException(WholesaleRefused::class);
        $this->expectExceptionMessageIsOrContains('No receivable is known as nope.');
        $sales->receive('nope', $this->cad(1));
    }

    public function testWhatIsOverdueIsListed(): void
    {
        $sales = $this->sales();
        $sales->open('acme', $this->cad(10_000), 30);

        $paid = $sales->charge($this->order(1_000, 'A-2'), 'acme', $this->at);
        $late = $sales->charge($this->order(1_000), 'acme', $this->at);
        $sales->charge($this->order(1_000, 'A-3'), 'acme', $this->at->modify('+20 days'));
        $sales->receive($paid->id, $this->cad(1_000));

        self::assertSame([$late], $sales->overdue('acme', $this->at->modify('+31 days')));
        self::assertSame([], $sales->overdue('acme', $this->at->modify('+30 days')));
    }

    public function testARefundCreditsTheReceivableAndTheAccount(): void
    {
        $sales = $this->sales();
        $account = $sales->open('acme', $this->cad(10_000), 30);
        $receivable = $sales->charge($this->order(4_000), 'acme', $this->at);
        $payment = $this->paymentStore->payments[0];
        $sales->receive($receivable->id, $this->cad(3_000));

        $this->payments->refund($payment, $this->cad(2_000), 'refund-1');

        self::assertSame([1_000, 0, 0], [$receivable->credited()->minorUnits, $receivable->outstanding()->minorUnits, $account->owed->minorUnits]);
        self::assertSame([2_000, PaymentState::Completed], [$payment->refunded->minorUnits, $payment->state()]);
    }

    public function testARefundAskedAgainUnderItsKeyCreditsNothingMore(): void
    {
        $sales = $this->sales();
        $account = $sales->open('acme', $this->cad(10_000), 30);
        $receivable = $sales->charge($this->order(4_000), 'acme', $this->at);
        $payment = $this->paymentStore->payments[0];

        $this->payments->refund($payment, $this->cad(1_000), 'refund-1');
        $this->payments->refund($payment, $this->cad(1_000), 'refund-1');

        self::assertSame([1_000, 3_000, 3_000, 1_000], [$receivable->credited()->minorUnits, $receivable->outstanding()->minorUnits, $account->owed->minorUnits, $payment->refunded->minorUnits]);

        $this->payments->refund($payment, $this->cad(500), 'refund-2');

        self::assertSame([1_500, 2_500, 1_500], [$receivable->credited()->minorUnits, $account->owed->minorUnits, $payment->refunded->minorUnits]);
    }

    public function testARefundOfAFullyPaidPurchaseLeavesTheAccountAlone(): void
    {
        $sales = $this->sales();
        $account = $sales->open('acme', $this->cad(10_000), 30);
        $sales->charge($this->order(1_000, 'A-2'), 'acme', $this->at);
        $receivable = $sales->charge($this->order(4_000), 'acme', $this->at);
        $sales->receive($receivable->id, $this->cad(4_000));

        $this->payments->refund($this->paymentStore->payments[1], $this->cad(500), 'refund-1');

        self::assertSame([0, 1_000], [$receivable->credited()->minorUnits, $account->owed->minorUnits]);
        self::assertSame(500, $receivable->refundDue()->minorUnits);
        self::assertSame([$receivable], $sales->refundsDue('acme'));
    }

    /**
     * What the buyer already paid is owed back to the buyer, beside what is credited, until the shop settles it.
     */
    public function testARefundBeyondWhatIsOutstandingIsOwedBackUntilSettled(): void
    {
        $sales = $this->sales();
        $sales->open('acme', $this->cad(10_000), 30);

        $receivable = $sales->charge($this->order(4_000), 'acme', $this->at);
        $sales->receive($receivable->id, $this->cad(3_000));

        $this->payments->refund($this->paymentStore->payments[0], $this->cad(2_500), 'refund-1');

        self::assertSame([1_000, 1_500], [$receivable->credited()->minorUnits, $receivable->refundDue()->minorUnits]);
        self::assertSame(1_000, $sales->settleRefund($receivable->id, $this->cad(1_000))->minorUnits);
        self::assertSame(500, $sales->settleRefund($receivable->id, $this->cad(900))->minorUnits);
        self::assertSame([0, []], [$receivable->refundDue()->minorUnits, $sales->refundsDue('acme')]);

        $this->expectException(WholesaleRefused::class);
        $sales->settleRefund('missing', $this->cad(1));
    }

    public function testAPaymentWithoutReceivableIsStillRefunded(): void
    {
        $order = $this->order(1_000);
        $payment = Payment::create($order, 'on_account', $this->cad(1_000), $this->at);
        $payment->apply(PaymentNotification::of('n', $payment->id, PaymentEvent::Captured));

        new AccountGateway($this->credit)->refund($payment, $this->cad(300), 'refund-1');

        self::assertSame(300, $payment->refunded->minorUnits);
    }

    public function testAReceivableWhoseAccountIsGoneIsStillCredited(): void
    {
        $sales = $this->sales();
        $sales->open('acme', $this->cad(10_000), 30);

        $receivable = $sales->charge($this->order(4_000), 'acme', $this->at);
        $this->credit->accounts = [];

        $this->payments->refund($this->paymentStore->payments[0], $this->cad(1_000), 'refund-1');

        self::assertSame(1_000, $receivable->credited()->minorUnits);
    }

    public function testTheGatewayIsItsOwnProcessor(): void
    {
        $gateway = new AccountGateway($this->credit);

        self::assertTrue($gateway->supports('on_account'));
        self::assertFalse($gateway->supports('card'));

        $this->expectException(InvalidNotification::class);
        $this->expectExceptionMessageIsOrContains('A purchase on account is its own processor: it sends no notification.');

        $gateway->read('{}', []);
    }

    private function sales(): CreditSales
    {
        return new CreditSales($this->payments, $this->credit);
    }

    private function order(int $total, string $number = 'A-1'): Order
    {
        $order = Order::open($number, Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'BOX'), 'Box', $this->cad($total), 'standard'), 1, $this->cad($total));
        $order->place($this->at);

        return $order;
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }
}
