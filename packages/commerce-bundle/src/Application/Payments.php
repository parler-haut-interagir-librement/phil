<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\Port\PaymentGateway;
use Phil\CommerceBundle\Domain\Event\OrderPurchased;
use Phil\CommerceBundle\Domain\Exception\PaymentRefused;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\Payment\OrderPaymentState;
use Phil\CommerceBundle\Domain\Payment\PaymentState;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceBundle\Domain\Repository\PaymentRepository;
use Phil\CommerceBundle\Domain\ValueObject\OrderPayments;
use Phil\CommerceBundle\Domain\ValueObject\PaymentStart;
use Phil\Money\Money;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The payments of placed orders, through the gateways the application registers.
 *
 * - **The amount is the server's**: what is left to pay on the order, or a part of it for a split payment, never
 *   more.
 * - **The state comes from the processor's notifications**, read and verified by the gateway of the payment's
 *   method, each applied once.
 * - **A refund is asked here and recorded when the processor confirms it.**
 *
 * - **Two payments started at once cannot both take the same share**: each start moves the order's version on,
 *   so the second commit over the same copy of the order is refused as stale.
 * - **A payment the buyer gave up is abandoned** here, by the application, and frees its share.
 * - **An offline payment is declared** for a method of `phil_commerce.offline_payments`, without a gateway, and
 *   confirmed or cancelled by someone of the store; its refunds are recorded at once.
 * - **An order paid in full is announced once** by `OrderPurchased`, when the payment that completes it is applied —
 *   what a loyalty programme or a wish list listens to.
 *
 * Nothing is committed here: the application, or the webhook controller, commits its unit of work.
 */
final readonly class Payments
{
    /**
     * @param iterable<PaymentGateway> $gateways
     * @param list<string>             $offlineMethods the methods paid offline, as `phil_commerce.offline_payments` lists them
     * @param OrderRepository|null     $orders         where the order of a notified payment is read, to announce it paid
     */
    public function __construct(
        private iterable $gateways,
        private PaymentRepository $payments,
        private ?OrderRepository $orders = null,
        private ?EventDispatcherInterface $dispatcher = null,
        private array $offlineMethods = [],
        private ?ClockInterface $clock = null,
    ) {
    }

    /**
     * Creates a payment of the order and hands it to the gateway of the method.
     *
     * A different currency from the order's throws `CurrencyMismatch`.
     *
     * @param Money|null $amount part of what is left to pay, for a split payment; all of it by default
     *
     * @return array{Payment, PaymentStart} the payment, stored with the next commit, and how the buyer goes on
     */
    public function start(Order $order, string $method, DateTimeImmutable $at, ?Money $amount = null): array
    {
        if (OrderState::Placed !== $order->state()) {
            throw PaymentRefused::orderNotPlaced($order->number);
        }

        $gateway = $this->gateway($method);
        $payable = OrderPayments::of($order, $this->payments->forOrder($order->number))->payable();
        $amount ??= $payable;

        if (!$amount->isPositive() || $amount->isGreaterThan($payable)) {
            throw PaymentRefused::amount('A payment', $this->show($amount), $this->show($payable));
        }

        $payment = Payment::create($order, $method, $amount, $at);
        $order->countPaymentStart();
        $start = $gateway->start($payment, $order);
        $payment->handOver($start);
        $this->payments->add($payment);

        return [$payment, $start];
    }

    /**
     * Gives up a new or processing payment, so that it no longer holds its share of the order: the buyer came back
     * from the processor without paying, or no notification came in time. A notification that comes later still
     * applies to it.
     */
    public function abandon(Payment $payment): void
    {
        $payment->abandon();
    }

    /**
     * Gives up every new or processing payment of the order created before the given time, and returns them: what
     * an application calls before a new attempt, or on a schedule, so that a checkout the buyer left does not hold
     * the order's balance for ever.
     *
     * @return list<Payment> the payments abandoned
     */
    public function abandonStale(Order $order, DateTimeImmutable $createdBefore): array
    {
        $abandoned = [];

        foreach ($this->payments->forOrder($order->number) as $payment) {
            // An offline payment waits for its money as long as it takes: someone of the store confirms or cancels it.
            if (!$payment->isOffline() && \in_array($payment->state(), [PaymentState::New, PaymentState::Processing], true) && $payment->createdAt < $createdBefore) {
                $payment->abandon();
                $abandoned[] = $payment;
            }
        }

        return $abandoned;
    }

    /**
     * Applies a notification the gateway of the method verifies and reads.
     *
     * @param array<string, list<string>> $headers
     *
     * @return array{NotificationOutcome, ?Payment} the outcome, and the payment it applied to, if any
     */
    public function notify(string $method, string $payload, array $headers): array
    {
        $notification = $this->gateway($method)->read($payload, $headers);
        $payment = $this->payments->findByExternalReference($method, $notification->externalReference);

        if (!$payment instanceof Payment) {
            return [NotificationOutcome::UnknownPayment, null];
        }

        $order = $this->orders?->find($payment->orderNumber);
        $before = $order instanceof Order ? $this->summary($order)->state() : null;

        try {
            $applied = $payment->apply($notification);
        } catch (PaymentRefused) {
            return [NotificationOutcome::Refused, $payment];
        }

        if ($applied && $order instanceof Order) {
            $this->announceIfPaid($order, $before, $this->clock?->now() ?? new DateTimeImmutable());
        }

        return [$applied ? NotificationOutcome::Applied : NotificationOutcome::AlreadyApplied, $payment];
    }

    /**
     * Declares an offline payment of the order: it holds its share of the order as processing until someone of the
     * store confirms or cancels it.
     *
     * @param Money|null $amount part of what is left to pay; all of it by default
     */
    public function declareOffline(Order $order, string $method, DateTimeImmutable $at, ?Money $amount = null): Payment
    {
        if (OrderState::Placed !== $order->state()) {
            throw PaymentRefused::orderNotPlaced($order->number);
        }

        if (!\in_array($method, $this->offlineMethods, true)) {
            throw PaymentRefused::notOfflineMethod($method);
        }

        $payable = OrderPayments::of($order, $this->payments->forOrder($order->number))->payable();
        $amount ??= $payable;

        if (!$amount->isPositive() || $amount->isGreaterThan($payable)) {
            throw PaymentRefused::amount('A payment', $this->show($amount), $this->show($payable));
        }

        $payment = Payment::declareOffline($order, $method, $amount, $at);
        $order->countPaymentStart();
        $this->payments->add($payment);

        return $payment;
    }

    /**
     * The money of an offline payment came in: the payment completes, and the order is announced once paid in full.
     */
    public function confirmOffline(Order $order, Payment $payment, DateTimeImmutable $at): void
    {
        if ($payment->orderNumber !== $order->number) {
            throw PaymentRefused::orderNotPlaced($order->number);
        }

        $before = $this->summary($order)->state();
        $payment->confirmReceived();
        $this->announceIfPaid($order, $before, $at);
    }

    public function cancelOffline(Payment $payment): void
    {
        $payment->cancelOffline();
    }

    /**
     * @return list<string> the methods paid offline
     */
    public function offlineMethods(): array
    {
        return $this->offlineMethods;
    }

    /**
     * Asks the gateway of the payment to refund part or all of what is left.
     *
     * @param string $idempotencyKey the same for every try of one refund — `Refunds` gives the refund's identifier —, so
     *                               that the processor refunds once however often it is asked
     */
    public function refund(Payment $payment, Money $amount, string $idempotencyKey): void
    {
        if (PaymentState::Completed !== $payment->state()) {
            throw PaymentRefused::notRefundable($payment->id, $payment->state());
        }

        $refundable = $payment->refundable();

        if (!$amount->isPositive() || $amount->isGreaterThan($refundable)) {
            throw PaymentRefused::amount('A refund', $this->show($amount), $this->show($refundable));
        }

        if ($payment->isOffline()) {
            // No processor will notify it: the money handed back is recorded now.
            $payment->recordOfflineRefund($amount);

            return;
        }

        $this->gateway($payment->method)->refund($payment, $amount, $idempotencyKey);
    }

    public function summary(Order $order): OrderPayments
    {
        return OrderPayments::of($order, $this->payments->forOrder($order->number));
    }

    /**
     * Announces the order once its payments cover it in full, and only when they did not before.
     */
    private function announceIfPaid(Order $order, ?OrderPaymentState $before, DateTimeImmutable $at): void
    {
        if (OrderPaymentState::Paid === $before || OrderPaymentState::Paid !== $this->summary($order)->state()) {
            return;
        }

        $this->dispatcher?->dispatch(new OrderPurchased($order->number, $order->channel(), $order->customer(), $order->total(), $at));
    }

    private function gateway(string $method): PaymentGateway
    {
        foreach ($this->gateways as $gateway) {
            if ($gateway->supports($method)) {
                return $gateway;
            }
        }

        throw PaymentRefused::unknownMethod($method);
    }

    private function show(Money $amount): string
    {
        return $amount->toDecimal().' '.$amount->currency->code;
    }
}
