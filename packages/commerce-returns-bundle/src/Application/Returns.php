<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Application;

use DateTimeImmutable;
use Exception;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\Payment\PaymentEvent;
use Phil\CommerceBundle\Domain\Payment\PaymentState;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceBundle\Domain\ValueObject\PaymentNotification;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceReturnsBundle\Application\Contract\ReturnLabels;
use Phil\CommerceReturnsBundle\Application\Contract\SetParts;
use Phil\CommerceReturnsBundle\Application\Contract\ShipmentDates;
use Phil\CommerceReturnsBundle\Application\Contract\SpecialDiscounts;
use Phil\CommerceReturnsBundle\Application\Port\Restocking;
use Phil\CommerceReturnsBundle\Application\Port\StoreCreditGranter;
use Phil\CommerceReturnsBundle\Domain\Event\ReturnSettled;
use Phil\CommerceReturnsBundle\Domain\Event\ReturnStateChanged;
use Phil\CommerceReturnsBundle\Domain\Exception\ReturnRefused;
use Phil\CommerceReturnsBundle\Domain\Model\ReturnRequest;
use Phil\CommerceReturnsBundle\Domain\Repository\ReturnRepository;
use Phil\CommerceReturnsBundle\Domain\Rma\ItemCondition;
use Phil\CommerceReturnsBundle\Domain\Rma\Resolution;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnReason;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnState;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnLabel;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnPolicy;
use Phil\Money\Money;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Returns of goods, from the request to the refund, the store credit or the exchange.
 *
 * Each unit returned is worth its share of what its order line came to, promotions and taxes on the line included; a
 * set comes back whole; a special loses its discount when one of its products comes back; the delivery charge is
 * given back once per order as the policy says — see `ReturnValuation`. A line returned in part keeps the rest
 * returnable, and a rejected, cancelled or expired return frees its units.
 *
 * Each step dispatches a `ReturnStateChanged`, and a settlement a `ReturnSettled`, when an event dispatcher is given.
 */
final readonly class Returns
{
    private ReturnValuation $valuation;

    private ReturnPolicy $policy;

    /**
     * @param ReturnPolicy|null    $policy     the shop's rules; `ReturnPolicy::of()`'s defaults when none is given
     * @param iterable<Restocking> $restocking
     */
    public function __construct(
        private OrderRepository $orders,
        private ReturnRepository $returns,
        private Payments $payments,
        ?ReturnPolicy $policy = null,
        private iterable $restocking = [],
        private ?StoreCreditGranter $storeCredit = null,
        ?SetParts $sets = null,
        ?SpecialDiscounts $specials = null,
        private ?ShipmentDates $shipments = null,
        private ?ReturnLabels $labels = null,
        private ?RateLimiterFactoryInterface $limiter = null,
        private ?EventDispatcherInterface $dispatcher = null,
    ) {
        $this->policy = $policy ?? ReturnPolicy::of();
        $this->valuation = new ReturnValuation($sets, $specials);
    }

    /**
     * Asks for a return of some lines of a placed order, within the window, counted from the order's last shipment
     * when the policy says so and one is known, from its placement otherwise. A requester — the customer's stable
     * identifier — is held to the rate limiter, when one is given.
     *
     * @param list<array{line: string, quantity: int, reason: ReturnReason}> $lines the order lines as `type:id`
     */
    public function request(Order $order, array $lines, DateTimeImmutable $at, ?string $requester = null): ReturnRequest
    {
        if (OrderState::Placed !== $order->state()) {
            throw ReturnRefused::notPlaced($order->number);
        }

        // A placed order always has its moment.
        $from = $order->placedAt() ?? $at;

        if ($this->policy->windowFromShipment) {
            $from = $this->shipments?->lastShippedAt($order->number) ?? $from;
        }

        if (!$this->policy->isOpen($from, $at)) {
            throw ReturnRefused::tooLate($order->number, $this->policy->windowDays);
        }

        if (null !== $requester && $this->limiter instanceof RateLimiterFactoryInterface && !$this->limiter->create($requester)->consume()->isAccepted()) {
            throw ReturnRefused::tooManyRequests();
        }

        $holding = array_values(array_filter($this->returns->returnsOf($order->number), static fn (ReturnRequest $return): bool => $return->state()->holds()));
        $valued = $this->valuation->value($order, $lines, $holding, $this->policy->deliveryRefund);
        $return = ReturnRequest::request($order->number, $order->currency, $valued['lines'], $this->policy->restockingFee, $at, $valued['specialCharges'], $valued['deliveryRefund']);
        $this->returns->addReturn($return);
        $this->changed($return, null, $at);

        return $return;
    }

    public function get(string $id): ReturnRequest
    {
        return $this->returns->findReturn($id) ?? throw ReturnRefused::unknown($id);
    }

    public function authorize(string $id, DateTimeImmutable $at): ReturnRequest
    {
        return $this->step($id, $at, static fn (ReturnRequest $return) => $return->authorize($at));
    }

    public function reject(string $id, string $note, DateTimeImmutable $at): ReturnRequest
    {
        return $this->step($id, $at, static fn (ReturnRequest $return) => $return->reject($note, $at));
    }

    public function cancel(string $id, DateTimeImmutable $at): ReturnRequest
    {
        return $this->step($id, $at, static fn (ReturnRequest $return) => $return->cancel($at));
    }

    /**
     * Sends the return back to the customer for what the shop needs to know, said in the note.
     */
    public function askForInformation(string $id, string $note, DateTimeImmutable $at): ReturnRequest
    {
        return $this->step($id, $at, static fn (ReturnRequest $return) => $return->askForInformation($note, $at));
    }

    /**
     * The customer gave what the shop asked: the return is requested again.
     */
    public function provideInformation(string $id, DateTimeImmutable $at): ReturnRequest
    {
        return $this->step($id, $at, static fn (ReturnRequest $return) => $return->provideInformation($at));
    }

    /**
     * Expires the authorized returns whose goods did not come back within the policy's days; none when the policy
     * lets them wait. A scheduled task calls it.
     *
     * @return list<ReturnRequest> the returns expired
     */
    public function expire(DateTimeImmutable $now): array
    {
        if (null === $this->policy->expiryDays) {
            return [];
        }

        $expired = [];

        foreach ($this->returns->authorizedBefore($now->modify(\sprintf('-%d days', $this->policy->expiryDays))) as $return) {
            $return->expire($now);
            $this->changed($return, ReturnState::Authorized, $now);
            $expired[] = $return;
        }

        return $expired;
    }

    /**
     * Asks the carrier adapter for the label that sends an authorized return's goods back, and keeps it.
     */
    public function label(string $id, DateTimeImmutable $at): ReturnLabel
    {
        if (!$this->labels instanceof ReturnLabels) {
            throw ReturnRefused::noLabels();
        }

        $return = $this->get($id);

        if (ReturnState::Authorized !== $return->state()) {
            throw ReturnRefused::notAuthorized($return->id, $return->state());
        }

        $label = $this->labels->labelFor($return, $at);
        $return->attachLabel($label);

        return $label;
    }

    /**
     * Erases what was written freely on the return, keeping what the accounts need.
     */
    public function anonymize(string $id): ReturnRequest
    {
        $return = $this->get($id);
        $return->anonymize();

        return $return;
    }

    /**
     * Records the goods back and what each line was found to be, then puts the resellable units back on the shelf.
     *
     * @param array<string, ItemCondition> $conditions by order line
     */
    public function receive(string $id, array $conditions, DateTimeImmutable $at): ReturnRequest
    {
        $return = $this->get($id);
        $return->receive($conditions, $at);
        $this->changed($return, ReturnState::Authorized, $at);

        foreach ($return->lines() as $line) {
            if (ItemCondition::Resellable === $line->condition) {
                foreach ($this->restocking as $restocking) {
                    $restocking->restock($this->reference($line->line), $line->quantity, $return->id, $at);
                }
            }
        }

        return $return;
    }

    /**
     * Gives the value back to the order's payments, the latest first; see `refundTo()` for what is skipped.
     */
    public function refund(string $id, DateTimeImmutable $at): Settlement
    {
        $return = $this->get($id);
        $value = $return->value();
        $this->resolve($return, Resolution::Refund, $at);
        [$refunds, $shortfall, $failures] = $this->refundTo($return, $value);

        return new Settlement($value, $refunds, $shortfall, Money::zero($value->currency), null, $failures);
    }

    /**
     * Turns the value into the customer's store credit.
     */
    public function credit(string $id, string $customer, DateTimeImmutable $at): Settlement
    {
        if (!$this->storeCredit instanceof StoreCreditGranter) {
            throw ReturnRefused::noStoreCredit();
        }

        $return = $this->get($id);
        $value = $return->value();
        $this->resolve($return, Resolution::StoreCredit, $at);

        if ($value->isPositive()) {
            $this->storeCredit->grant($customer, $value, $at, \sprintf('Return %s', $return->id), $return->orderNumber);
        }

        $zero = Money::zero($value->currency);

        return new Settlement($value, [], $zero, $value, null);
    }

    /**
     * Pays a placed order with the value, as a payment of the core of method `exchange`; what the new order does not
     * take is refunded to the returned order's payments.
     */
    public function exchange(string $id, Order $order, DateTimeImmutable $at): Settlement
    {
        $return = $this->get($id);
        $value = $return->value();

        if (OrderState::Placed !== $order->state() || !$order->currency->equals($value->currency)) {
            throw ReturnRefused::notAnExchange($order->number);
        }

        $this->resolve($return, Resolution::Exchange, $at);
        $payable = $this->payments->summary($order)->payable();
        $applied = $value->isGreaterThan($payable) ? $payable : $value;
        $payment = null;

        if ($applied->isPositive()) {
            [$payment] = $this->payments->start($order, ReturnsGateway::EXCHANGE, $at, $applied);
            $payment->apply(PaymentNotification::of(Uuid::v7()->toRfc4122(), $payment->id, PaymentEvent::Captured));
        }

        [$refunds, $shortfall, $failures] = $this->refundTo($return, $value->subtract($applied));

        return new Settlement($value, $refunds, $shortfall, Money::zero($value->currency), $payment, $failures);
    }

    /**
     * Refunds the order's completed payments, the latest first, each up to what it can still refund.
     *
     * A payment of method `exchange` is skipped: its value came from an earlier return, and refunding it moves no
     * money; its part stays in the shortfall, for the application to give back — as store credit, say. A gateway that
     * fails is recorded and passed over, so that the refunds already sent to other processors are kept with the
     * return's resolution instead of being rolled back and sent again on a retry; what it should have taken goes to
     * the next payments, or to the shortfall.
     *
     * Each refund's idempotency key is the return's id and the payment's, the same on every try: a processor asked
     * again refunds once.
     *
     * It returns the refunds made, what no payment could take back, and the refunds that failed.
     *
     * @return array{list<array{Payment, Money}>, Money, list<array{Payment, Money, Exception}>}
     */
    private function refundTo(ReturnRequest $return, Money $amount): array
    {
        $order = $this->orders->find($return->orderNumber);
        $refunds = [];
        $failures = [];

        if ($order instanceof Order) {
            foreach (array_reverse($this->payments->summary($order)->payments) as $payment) {
                $part = $payment->refundable()->isGreaterThan($amount) ? $amount : $payment->refundable();

                if (PaymentState::Completed !== $payment->state() || ReturnsGateway::EXCHANGE === $payment->method || !$part->isPositive()) {
                    continue;
                }

                try {
                    $this->payments->refund($payment, $part, $return->id.':'.$payment->id);
                } catch (Exception $exception) {
                    $failures[] = [$payment, $part, $exception];

                    continue;
                }

                $refunds[] = [$payment, $part];
                $amount = $amount->subtract($part);
            }
        }

        return [$refunds, $amount, $failures];
    }

    /**
     * Takes a step of a return, then tells it.
     *
     * @param callable(ReturnRequest): void $step
     */
    private function step(string $id, DateTimeImmutable $at, callable $step): ReturnRequest
    {
        $return = $this->get($id);
        $from = $return->state();
        $step($return);
        $this->changed($return, $from, $at);

        return $return;
    }

    private function resolve(ReturnRequest $return, Resolution $resolution, DateTimeImmutable $at): void
    {
        $return->resolve($resolution, $at);
        $this->changed($return, ReturnState::Received, $at);
        $this->dispatcher?->dispatch(new ReturnSettled($return->id, $return->orderNumber, $resolution, $return->value(), $at));
    }

    private function changed(ReturnRequest $return, ?ReturnState $from, DateTimeImmutable $at): void
    {
        $this->dispatcher?->dispatch(new ReturnStateChanged($return->id, $return->orderNumber, $from, $return->state(), $at));
    }

    /**
     * The reference of an order line read back from its key, the fingerprint of a configured line included.
     */
    private function reference(string $line): SellableReference
    {
        return SellableReference::fromValue($line);
    }
}
