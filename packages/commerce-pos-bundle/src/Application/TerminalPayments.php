<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\NotificationOutcome;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommercePosBundle\Domain\Exception\PosRefused;
use Phil\CommercePosBundle\Domain\Model\CashSession;
use Phil\CommercePosBundle\Domain\Model\SessionPayment;
use Phil\CommercePosBundle\Domain\Model\TerminalPayment;
use Phil\CommercePosBundle\Domain\Repository\CashSessionRepository;
use Phil\CommercePosBundle\Domain\Repository\RegisterSaleRepository;
use Phil\Money\Money;
use Throwable;

/**
 * Card payments on connected terminals, driven by the server: the till asks for a charge, the server creates the
 * core's payment and asks the terminal's processor, through its adapter, to show it on the device; the processor's
 * signed notification settles it, and the session counts it once captured. The till only shows what the server
 * says, and polls or listens for the outcome.
 */
final readonly class TerminalPayments
{
    public function __construct(
        private Payments $payments,
        private TerminalGateway $gateway,
        private TerminalAdapters $adapters,
        private Registers $registers,
        private CashSessionRepository $sessions,
        private RegisterSaleRepository $sales,
    ) {
    }

    /**
     * Presents part or all of what is left to pay on a terminal of the session's register.
     *
     * @param Money|null $amount part of what is left to pay; all of it by default
     */
    public function charge(Order $order, CashSession $session, string $terminal, DateTimeImmutable $at, ?Money $amount = null): Payment
    {
        if (!$session->isOpen()) {
            throw PosRefused::sessionClosed($session->id);
        }

        $device = $this->registers->terminal($terminal);

        if ($device->register !== $session->register) {
            throw PosRefused::terminalNotAtRegister($device->code, $session->register);
        }

        $adapter = $this->adapters->named($device->adapter);
        [$payment] = $this->payments->start($order, $this->gateway->method(), $at, $amount);

        try {
            $reference = $adapter->present(new TerminalCharge($payment->id, $order->number, $device->device, $payment->amount));
        } catch (Throwable $throwable) {
            // The device never showed it: the payment gives its share of the order back.
            $this->payments->abandon($payment);

            throw $throwable;
        }

        $this->sales->addTerminalPayment(TerminalPayment::of($payment->id, $device, $session, $reference, $at));

        return $payment;
    }

    /**
     * Withdraws a charge the device still shows, and gives up its payment. A notification that comes later still
     * applies to it, as the core's do.
     */
    public function cancel(Payment $payment): void
    {
        $presented = $this->sales->findTerminalPayment($payment->id) ?? throw PosRefused::notPresented($payment->id);
        $device = $this->registers->terminal($presented->terminal);
        $this->adapters->named($device->adapter)->cancel($device->device, $presented->processorReference);
        $this->payments->abandon($payment);
    }

    /**
     * Applies a processor's notification through the core, then counts a captured payment in the session it was
     * taken in, once.
     *
     * @param array<string, list<string>> $headers the request's headers, names in lowercase
     *
     * @return array{NotificationOutcome, ?Payment}
     */
    public function notify(string $payload, array $headers, DateTimeImmutable $at): array
    {
        [$outcome, $payment] = $this->payments->notify($this->gateway->method(), $payload, $headers);

        if (!$payment instanceof Payment || !$payment->wasCaptured()) {
            return [$outcome, $payment];
        }

        $presented = $this->sales->findTerminalPayment($payment->id);
        $session = $presented instanceof TerminalPayment ? $this->sessions->findSession($presented->session) : null;

        if ($presented instanceof TerminalPayment && $session instanceof CashSession && $presented->count()) {
            $this->sales->addPayment(SessionPayment::of($payment->id, $session, $payment->orderNumber, $payment->method, $payment->amount, $presented->processorReference, $at));
        }

        return [$outcome, $payment];
    }
}
