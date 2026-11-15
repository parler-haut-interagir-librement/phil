<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Contract\PaymentLinkRepository;
use Phil\CommerceBundle\Domain\Exception\PaymentRefused;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\PaymentLink;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * Payment links that expire: a link opens one placed order to whoever holds it, for `phil_commerce.payment_links`'
 * hours by default and never longer than its most.
 *
 * - **The token is shown once**, its SHA-256 stored; a malformed token is refused without a lookup.
 * - **A link refuses alike** whether it is unknown, revoked, expired, or its order is no longer placed or owes
 *   nothing: whoever follows it learns nothing more.
 * - **The amount is the server's**: following a link starts a payment of what the order still lacks.
 */
final readonly class PaymentLinks
{
    public function __construct(
        private PaymentLinkRepository $links,
        private OrderRepository $orders,
        private Payments $payments,
        private int $defaultHours = 72,
        private int $maxHours = 720,
    ) {
    }

    /**
     * Creates a link to the placed order, and returns it with its token, shown once.
     *
     * @param int|null $hours how long it opens the order; the configured default when `null`
     *
     * @return array{PaymentLink, string}
     */
    public function create(Order $order, DateTimeImmutable $at, ?int $hours = null): array
    {
        $hours ??= $this->defaultHours;

        if ($hours < 1 || $hours > $this->maxHours) {
            throw InvalidValue::because(\sprintf('A payment link opens its order for 1 to %d hours.', $this->maxHours));
        }

        if (OrderState::Placed !== $order->state() || !$this->payments->summary($order)->payable()->isPositive()) {
            throw PaymentRefused::orderNotPlaced($order->number);
        }

        [$token, $hash] = SecretToken::create();
        $link = PaymentLink::create($order->number, $hash, $at, $at->modify(\sprintf('+%d hours', $hours)));
        $this->links->addPaymentLink($link);

        return [$link, $token];
    }

    /**
     * The order a token opens at that moment, or `null`, whatever the reason.
     */
    public function open(string $token, DateTimeImmutable $at): ?Order
    {
        $hash = SecretToken::hash($token);
        $link = null === $hash ? null : $this->links->findPaymentLinkByTokenHash($hash);

        if (!$link instanceof PaymentLink || !$link->opensAt($at)) {
            return null;
        }

        $order = $this->orders->find($link->orderNumber);

        if (!$order instanceof Order || OrderState::Placed !== $order->state() || !$this->payments->summary($order)->payable()->isPositive()) {
            return null;
        }

        return $order;
    }

    /**
     * Revokes every link of the order still open: what an application does when the order is paid otherwise, amended
     * or cancelled.
     *
     * @return int how many links were revoked
     */
    public function revokeAll(Order $order, DateTimeImmutable $at): int
    {
        $revoked = 0;

        foreach ($this->links->paymentLinksOf($order->number) as $link) {
            if ($link->opensAt($at)) {
                $link->revoke($at);
                ++$revoked;
            }
        }

        return $revoked;
    }
}
