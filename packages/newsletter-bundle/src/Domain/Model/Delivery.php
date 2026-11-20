<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\Model;

use DateTimeImmutable;
use Phil\NewsletterBundle\Domain\Enum\DeliveryStatus;

/**
 * One message to one subscriber: of a campaign, or of a template sent on its own — by an automation, say. Its id is
 * drawn from what it delivers, so that the same message is queued once, whatever retries the queue makes. A message of
 * a campaign names the send run that queued it.
 */
final class Delivery
{
    public private(set) DeliveryStatus $status = DeliveryStatus::Queued;

    public private(set) int $attempts = 0;

    public private(set) ?DateTimeImmutable $sentAt = null;

    public function __construct(
        public readonly string $id,
        public readonly string $subscriberId,
        public readonly ?string $campaignId,
        public readonly ?string $template,
        public readonly DateTimeImmutable $createdAt,
        public readonly ?string $runId = null,
    ) {
    }

    /**
     * The id of a campaign's message to a subscriber.
     */
    public static function idFor(string $campaignId, string $subscriberId): string
    {
        return substr(hash('sha256', 'campaign'."\0".$campaignId."\0".$subscriberId), 0, 32);
    }

    /**
     * The id of a message sent on its own, under the idempotency key of what asks for it.
     */
    public static function idForKey(string $idempotencyKey): string
    {
        return substr(hash('sha256', 'single'."\0".$idempotencyKey), 0, 32);
    }

    public static function restore(self $delivery, DeliveryStatus $status, int $attempts, ?DateTimeImmutable $sentAt): self
    {
        $delivery->status = $status;
        $delivery->attempts = $attempts;
        $delivery->sentAt = $sentAt;

        return $delivery;
    }

    public function sent(DateTimeImmutable $now): void
    {
        ++$this->attempts;
        $this->status = DeliveryStatus::Sent;
        $this->sentAt = $now;
    }

    public function failed(): void
    {
        ++$this->attempts;
        $this->status = DeliveryStatus::Failed;
    }

    public function skipped(): void
    {
        $this->status = DeliveryStatus::Skipped;
    }

    public function bounced(): void
    {
        $this->status = DeliveryStatus::Bounced;
    }

    public function isPending(): bool
    {
        return DeliveryStatus::Queued === $this->status || DeliveryStatus::Failed === $this->status;
    }
}
