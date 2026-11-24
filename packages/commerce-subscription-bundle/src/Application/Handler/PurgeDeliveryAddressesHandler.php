<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Application\Handler;

use DateTimeImmutable;
use Phil\CommerceSubscriptionBundle\Application\DeliveryAddresses;
use Phil\CommerceSubscriptionBundle\Application\Message\PurgeDeliveryAddresses;
use Psr\Clock\ClockInterface;

/**
 * Purges the delivery addresses kept past their retention, now. Run twice, or by two workers, the second run finds
 * nothing left.
 */
final readonly class PurgeDeliveryAddressesHandler
{
    public function __construct(
        private DeliveryAddresses $addresses,
        private ?ClockInterface $clock = null,
        private int $limit = 500,
    ) {
    }

    /**
     * @return array{addresses: int, copies: int} how many were erased
     */
    public function __invoke(PurgeDeliveryAddresses $message): array
    {
        return $this->addresses->purge($this->clock?->now() ?? new DateTimeImmutable(), $this->limit);
    }
}
