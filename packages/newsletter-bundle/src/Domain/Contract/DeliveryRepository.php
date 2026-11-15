<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\Contract;

use Phil\NewsletterBundle\Domain\Enum\DeliveryStatus;
use Phil\NewsletterBundle\Domain\Model\Delivery;

/**
 * Where the deliveries are kept.
 */
interface DeliveryRepository
{
    /**
     * Keeps a new delivery unless one of the same id is there.
     *
     * @return bool `false` when it was already there
     */
    public function create(Delivery $delivery): bool;

    public function save(Delivery $delivery): void;

    public function get(string $id): ?Delivery;

    /**
     * @return list<Delivery>
     */
    public function ofSubscriber(string $subscriberId): array;

    /**
     * How many deliveries of a campaign are in each state.
     *
     * @return array<value-of<DeliveryStatus>, int>
     */
    public function countByStatus(string $campaignId): array;

    /**
     * Forgets the deliveries of a subscriber.
     *
     * @return int how many were forgotten
     */
    public function removeOfSubscriber(string $subscriberId): int;
}
