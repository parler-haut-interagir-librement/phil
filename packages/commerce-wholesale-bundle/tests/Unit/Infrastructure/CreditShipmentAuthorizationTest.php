<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Payment\PaymentEvent;
use Phil\CommerceBundle\Domain\ValueObject\PaymentNotification;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceWholesaleBundle\Domain\Model\CreditAccount;
use Phil\CommerceWholesaleBundle\Infrastructure\Logistics\CreditShipmentAuthorization;
use Phil\CommerceWholesaleBundle\Tests\Unit\Support\InMemoryCredit;
use Phil\CommerceWholesaleBundle\Tests\Unit\Support\InMemoryPayments;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A buyer on credit receives an order before paying it, within the credit of an active account in the order's
 * currency; anyone else waits for the payments.
 */
#[CoversClass(CreditShipmentAuthorization::class)]
final class CreditShipmentAuthorizationTest extends TestCase
{
    private InMemoryCredit $credit;

    private InMemoryPayments $paymentStore;

    private CreditShipmentAuthorization $authorization;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->credit = new InMemoryCredit();
        $this->paymentStore = new InMemoryPayments();
        $this->authorization = new CreditShipmentAuthorization(new Payments([], $this->paymentStore), $this->credit);
        $this->at = new DateTimeImmutable('2026-11-24 10:00:00');
    }

    public function testAnOrderWithinTheCreditLeaves(): void
    {
        $this->credit->addAccount(CreditAccount::open('acme', $this->cad(10_000), 30));

        self::assertTrue($this->authorization->authorizes($this->order(4_000, 'acme')));
        self::assertTrue($this->authorization->authorizes($this->order(10_000, 'acme')), 'the whole credit is enough');
        self::assertFalse($this->authorization->authorizes($this->order(10_001, 'acme')), 'a cent past the credit is not');
    }

    public function testWhatIsOwedAlreadyLowersTheCredit(): void
    {
        $account = CreditAccount::open('acme', $this->cad(10_000), 30);
        $account->charge($this->cad(7_000));

        $this->credit->addAccount($account);

        self::assertTrue($this->authorization->authorizes($this->order(3_000, 'acme')));
        self::assertFalse($this->authorization->authorizes($this->order(3_001, 'acme')));
    }

    public function testOnlyWhatIsLeftToPayCountsAgainstTheCredit(): void
    {
        $this->credit->addAccount(CreditAccount::open('acme', $this->cad(1_000), 30));
        $order = $this->order(5_000, 'acme');
        self::assertFalse($this->authorization->authorizes($order));

        $payment = Payment::create($order, 'card', $this->cad(4_000), $this->at);
        $payment->apply(PaymentNotification::of('n-1', $payment->id, PaymentEvent::Captured));

        $this->paymentStore->add($payment);

        self::assertTrue($this->authorization->authorizes($order), 'a deposit and the credit together cover it');
    }

    public function testWithoutAnActiveAccountInTheCurrencyNothingLeaves(): void
    {
        self::assertFalse($this->authorization->authorizes($this->order(100, null)), 'a guest has no account');
        self::assertFalse($this->authorization->authorizes($this->order(100, 'nobody')), 'a buyer without an account');

        $suspended = CreditAccount::open('suspended', $this->cad(10_000), 30);
        $suspended->suspend();

        $this->credit->addAccount($suspended);
        self::assertFalse($this->authorization->authorizes($this->order(100, 'suspended')));

        $this->credit->addAccount(CreditAccount::open('dollars', Money::of(10_000, Currency::of('USD')), 30));
        self::assertFalse($this->authorization->authorizes($this->order(100, 'dollars')));
    }

    private function order(int $total, ?string $buyer): Order
    {
        $order = Order::open('A-1', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'BOX'), 'Box', $this->cad($total), 'standard'), 1, $this->cad($total));
        $order->assignCustomer($buyer);
        $order->place($this->at);

        return $order;
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }
}
