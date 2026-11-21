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
     * How many deliveries the send run queued.
     */
    public function countOfRun(string $runId): int;

    /**
     * The ids of the deliveries of a send run still to send, queued or failed, in pages by id.
     *
     * @param string|null $after the last id of the page before
     *
     * @return list<string>
     */
    public function pendingOfRun(string $runId, ?string $after, int $limit): array;

    /**
     * Skips the deliveries of a send run still to send.
     *
     * @return int how many were skipped
     */
    public function skipPendingOfRun(string $runId): int;

    /**
     * Forgets the deliveries of a subscriber.
     *
     * @return int how many were forgotten
     */
    public function removeOfSubscriber(string $subscriberId): int;
}
