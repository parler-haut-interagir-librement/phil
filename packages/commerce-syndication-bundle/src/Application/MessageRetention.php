<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Application;

use DateTimeImmutable;
use Phil\CommerceSyndicationBundle\Domain\Repository\MessageRepository;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * How long the messages are kept once their work is done. An outgoing message loses its personal data — the address
 * of a forwarded order — as soon as it is delivered or given up, and is deleted `retention_days` after; the id of a
 * received message, which recognises a delivery repeated, is deleted that long after it arrived.
 */
final readonly class MessageRetention
{
    /**
     * @param int $retentionDays how many days a message is kept once delivered, given up or received
     */
    public function __construct(
        private MessageRepository $messages,
        private int $retentionDays,
    ) {
        if ($retentionDays < 1) {
            throw InvalidValue::because(\sprintf('Messages are kept one day or more; %d is refused.', $retentionDays));
        }
    }

    /**
     * @return array{int, int} how many outgoing messages, then how many received ids, were deleted
     */
    public function purge(DateTimeImmutable $at): array
    {
        $before = $at->modify(\sprintf('-%d days', $this->retentionDays));

        return [$this->messages->purgeOutgoing($before), $this->messages->purgeReceived($before)];
    }
}
