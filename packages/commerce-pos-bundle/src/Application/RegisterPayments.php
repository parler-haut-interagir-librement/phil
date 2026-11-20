<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Payment\PaymentEvent;
use Phil\CommerceBundle\Domain\ValueObject\PaymentNotification;
use Phil\CommercePosBundle\Domain\Exception\PosRefused;
use Phil\CommercePosBundle\Domain\Model\CashSession;
use Phil\CommercePosBundle\Domain\Model\SessionPayment;
use Phil\CommercePosBundle\Domain\Repository\CashSessionRepository;
use Phil\CommercePosBundle\Domain\Repository\RegisterRepository;
use Phil\CommercePosBundle\Domain\Repository\RegisterSaleRepository;
use Phil\CommercePosBundle\Domain\Session\CashMovementKind;
use Phil\CommercePosBundle\Domain\ValueObject\CashRounding;
use Phil\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * Payments taken at the register, each one a payment of the core: cash, rounded to the register's increment, with
 * its change; a recorded method, with the terminal's approval; any other payment — a gift card — counted in the
 * session. Several make a split payment. Each payment is its own transaction: the core reads what is left to pay from
 * the payments already stored.
 */
final readonly class RegisterPayments
{
    public function __construct(
        private Payments $payments,
        private RegisterGateway $gateway,
        private RegisterRepository $registers,
        private CashSessionRepository $sessions,
        private RegisterSaleRepository $sales,
    ) {
    }

    /**
     * @param Money|null $amount part of what is left to pay; all of it by default
     */
    public function cash(Order $order, CashSession $session, Money $tendered, string $by, DateTimeImmutable $at, ?Money $amount = null): CashTender
    {
        $this->checkOpen($session);
        $amount ??= $this->payments->summary($order)->payable();
        $due = $this->rounding($session)->round($amount);

        if ($tendered->isLessThan($due)) {
            throw PosRefused::notEnoughTendered($tendered->toDecimal(), $due->toDecimal());
        }

        [$payment] = $this->payments->start($order, RegisterGateway::CASH, $at, $amount);
        $movement = $session->move(CashMovementKind::Sale, $due, $due->subtract($amount), $by, $at, $payment->id);
        $this->sessions->addCashMovement($movement);
        $payment->apply(PaymentNotification::of($movement->id, $payment->id, PaymentEvent::Captured));
        $this->sales->addPayment(SessionPayment::of($payment->id, $session, $order->number, $payment->method, $amount, null, $at));

        return new CashTender($payment, $due, $tendered, $tendered->subtract($due));
    }

    /**
     * A payment the register records without processing it: the card terminal approved it, the cheque was handed over.
     *
     * @param string|null $approval the terminal's approval number, or the cheque's
     */
    public function recorded(Order $order, CashSession $session, string $method, Money $amount, ?string $approval, DateTimeImmutable $at): Payment
    {
        $this->checkOpen($session);

        if (!$this->gateway->records($method)) {
            throw PosRefused::methodNotRecorded($method);
        }

        [$payment] = $this->payments->start($order, $method, $at, $amount);
        $payment->apply(PaymentNotification::of(Uuid::v7()->toRfc4122(), $payment->id, PaymentEvent::Captured));
        $this->sales->addPayment(SessionPayment::of($payment->id, $session, $order->number, $method, $amount, $approval, $at));

        return $payment;
    }

    /**
     * Counts in the session a payment another module took at the register: a gift card, store credit.
     */
    public function count(Payment $payment, CashSession $session, DateTimeImmutable $at, ?string $approval = null): void
    {
        $this->checkOpen($session);
        $this->sales->addPayment(SessionPayment::of($payment->id, $session, $payment->orderNumber, $payment->method, $payment->amount, $approval, $at));
    }

    /**
     * Gives cash back for part or all of a cash payment, out of the session's drawer. Asked again with the same
     * idempotency key — a second tap, a retry —, it gives nothing more and moves no cash.
     *
     * @param string $idempotencyKey the same for every try of one refund, 1 to 255 visible ASCII characters
     *
     * @return Money the cash given, once rounded; zero when the refund was already made
     */
    public function refundCash(Payment $payment, Money $amount, CashSession $session, string $by, DateTimeImmutable $at, string $idempotencyKey): Money
    {
        $this->checkOpen($session);

        if (RegisterGateway::CASH !== $payment->method) {
            throw PosRefused::notTakenHere($payment->id);
        }

        $before = $payment->refunded;
        $this->payments->refund($payment, $amount, $idempotencyKey);

        if ($payment->refunded->equals($before)) {
            return Money::zero($amount->currency);
        }

        $given = $this->rounding($session)->round($amount);
        $this->sessions->addCashMovement($session->move(CashMovementKind::Refund, $given, $amount->subtract($given), $by, $at, $payment->id));

        return $given;
    }

    /**
     * Records a refund made on the terminal for a recorded payment; asked again with the same idempotency key, it is
     * recorded once.
     *
     * @param string $idempotencyKey the same for every try of one refund, 1 to 255 visible ASCII characters
     */
    public function refundRecorded(Payment $payment, Money $amount, string $idempotencyKey): void
    {
        if (!$this->gateway->records($payment->method)) {
            throw PosRefused::methodNotRecorded($payment->method);
        }

        $this->payments->refund($payment, $amount, $idempotencyKey);
    }

    private function checkOpen(CashSession $session): void
    {
        if (!$session->isOpen()) {
            throw PosRefused::sessionClosed($session->id);
        }
    }

    private function rounding(CashSession $session): CashRounding
    {
        return ($this->registers->findRegister($session->register) ?? throw PosRefused::unknownRegister($session->register))->rounding();
    }
}
