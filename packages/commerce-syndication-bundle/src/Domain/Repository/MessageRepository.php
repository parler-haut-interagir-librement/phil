<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Domain\Repository;

use DateTimeImmutable;
use Phil\CommerceSyndicationBundle\Domain\Model\OutgoingMessage;
use Phil\CommerceSyndicationBundle\Domain\Model\ReceivedMessage;

interface MessageRepository
{
    public function queue(OutgoingMessage $message): void;

    /**
     * The messages due at that moment, oldest first: not delivered, not given up, their next attempt come, and held by
     * no delivery run.
     *
     * @return list<OutgoingMessage>
     */
    public function due(DateTimeImmutable $at, int $limit): array;

    /**
     * Holds the message for this delivery run until then, in one atomic step that two runs cannot both win: the
     * message is claimed, in storage and on the object, only when no run holds it at that moment.
     *
     * @return bool whether this run holds it now
     */
    public function claim(OutgoingMessage $message, DateTimeImmutable $at, DateTimeImmutable $until): bool;

    public function wasReceived(string $partner, string $id): bool;

    public function markReceived(ReceivedMessage $message): void;

    /**
     * Deletes the outgoing messages delivered or given up before then.
     *
     * @return int how many were deleted
     */
    public function purgeOutgoing(DateTimeImmutable $before): int;

    /**
     * Deletes the ids of the messages received before then.
     *
     * @return int how many were deleted
     */
    public function purgeReceived(DateTimeImmutable $before): int;
}
