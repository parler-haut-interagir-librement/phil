<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Contract\ExpiringOrders;
use Phil\CommerceBundle\Domain\Repository\ChannelRepository;
use Phil\CommerceBundle\Domain\ValueObject\ExpirySweep;

/**
 * Expires the placed orders nobody paid in time, and deletes the expired ones once kept long enough, by the delays
 * of each channel; an order of no channel, or of a channel the store does not know, follows
 * `phil_commerce.order_expiry`. A deferred sale is never expired: its balance has its own due date.
 *
 * What a sweep handles is bounded: at most `$limit` orders expired and as many deleted per channel. The application
 * runs it on a schedule and commits after it.
 */
final readonly class OrderExpiry
{
    public function __construct(
        private ExpiringOrders $orders,
        private ChannelRepository $channels,
        private ?OrderLifecycle $lifecycle = null,
        private ?int $unpaidHours = 24,
        private ?int $retentionDays = 30,
    ) {
    }

    public function sweep(DateTimeImmutable $at, int $limit = 1_000): ExpirySweep
    {
        $limit = max(1, min($limit, 10_000));
        $expired = [];
        $deleted = [];
        $known = [];

        foreach ($this->channels->all() as $channel) {
            $known[] = $channel->code;
            $this->sweepFor([$channel->code], [], $channel->unpaidExpiryHours(), $channel->expiredRetentionDays(), $at, $limit, $expired, $deleted);
        }

        $this->sweepFor(null, $known, $this->unpaidHours, $this->retentionDays, $at, $limit, $expired, $deleted);

        return new ExpirySweep($expired, $deleted);
    }

    /**
     * @param list<string>|null $channels
     * @param list<string>      $except
     * @param list<string>      $expired
     * @param list<string>      $deleted
     */
    private function sweepFor(?array $channels, array $except, ?int $hours, ?int $days, DateTimeImmutable $at, int $limit, array &$expired, array &$deleted): void
    {
        if (null !== $hours) {
            foreach ($this->orders->unpaidPlacedBefore($channels, $except, $at->modify(\sprintf('-%d hours', $hours)), $limit) as $order) {
                $this->lifecycle instanceof OrderLifecycle ? $this->lifecycle->expire($order, $at) : $order->expire($at);
                $expired[] = $order->number;
            }
        }

        if (null !== $days) {
            foreach ($this->orders->expiredBefore($channels, $except, $at->modify(\sprintf('-%d days', $days)), $limit) as $number) {
                $this->orders->deleteExpired($number);
                $deleted[] = $number;
            }
        }
    }
}
