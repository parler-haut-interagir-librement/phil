<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\Model;

use DateTimeImmutable;
use Phil\NewsletterBundle\Domain\Enum\SubscriberStatus;
use Phil\NewsletterBundle\Domain\Exception\InvalidNewsletterValue;
use Phil\NewsletterBundle\Domain\ValueObject\Site;

/**
 * A person who subscribed on one site: their address, the identifier the application knows them by, if any, and
 * where they stand. Their lists are their memberships.
 *
 * A subscriber who bounced or complained is never mailed again; one who paused receives nothing until the pause ends,
 * then receives again without asking.
 */
final class Subscriber
{
    public const int MAX_EXTERNAL_ID_LENGTH = 191;

    public private(set) ?DateTimeImmutable $pausedUntil = null;

    public private(set) ?DateTimeImmutable $confirmedAt = null;

    public private(set) int $softBounces = 0;

    public private(set) ?string $externalId;

    /**
     * @param string $email the address as it was validated, its domain in lowercase
     */
    public function __construct(
        public readonly string $id,
        public readonly Site $site,
        public readonly string $email,
        ?string $externalId,
        public private(set) SubscriberStatus $status,
        public readonly DateTimeImmutable $createdAt,
    ) {
        $this->externalId = $this->externalId($externalId);
    }

    /**
     * The key the address is found by: lowercase, so that `Ada@Example.com` and `ada@example.com` are one subscriber.
     */
    public static function keyOf(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function emailKey(): string
    {
        return self::keyOf($this->email);
    }

    /**
     * Restores a subscriber as a store keeps it.
     */
    public static function restore(string $id, Site $site, string $email, ?string $externalId, SubscriberStatus $status, DateTimeImmutable $createdAt, ?DateTimeImmutable $confirmedAt, ?DateTimeImmutable $pausedUntil, int $softBounces): self
    {
        $subscriber = new self($id, $site, $email, $externalId, $status, $createdAt);
        $subscriber->confirmedAt = $confirmedAt;
        $subscriber->pausedUntil = $pausedUntil;
        $subscriber->softBounces = max(0, $softBounces);

        return $subscriber;
    }

    /**
     * The confirmation of a double opt-in, or a subscription without one: active from now.
     */
    public function activate(DateTimeImmutable $now): void
    {
        $this->assertReachable();
        $this->status = SubscriberStatus::Active;
        $this->confirmedAt ??= $now;
        $this->pausedUntil = null;
    }

    /**
     * Back to waiting for a confirmation: a subscriber who left subscribes again with a double opt-in.
     */
    public function awaitConfirmation(): void
    {
        $this->assertReachable();

        if (SubscriberStatus::Active !== $this->status) {
            $this->status = SubscriberStatus::Pending;
        }
    }

    public function pause(DateTimeImmutable $until, DateTimeImmutable $now): void
    {
        if (SubscriberStatus::Active !== $this->status && SubscriberStatus::Paused !== $this->status) {
            throw InvalidNewsletterValue::because('Only an active subscriber pauses.');
        }

        if ($until->getTimestamp() <= $now->getTimestamp()) {
            throw InvalidNewsletterValue::because('A pause ends in the future.');
        }

        $this->status = SubscriberStatus::Paused;
        $this->pausedUntil = $until;
    }

    public function resume(): void
    {
        if (SubscriberStatus::Paused === $this->status) {
            $this->status = SubscriberStatus::Active;
            $this->pausedUntil = null;
        }
    }

    public function unsubscribe(): void
    {
        if (!$this->isSuppressed()) {
            $this->status = SubscriberStatus::Unsubscribed;
            $this->pausedUntil = null;
        }
    }

    public function bounced(): void
    {
        if (SubscriberStatus::Complained !== $this->status) {
            $this->status = SubscriberStatus::Bounced;
        }
    }

    public function complained(): void
    {
        $this->status = SubscriberStatus::Complained;
    }

    /**
     * Counts a soft bounce; at the threshold, the address counts as bounced.
     *
     * @return bool whether the subscriber is now bounced
     */
    public function softBounce(int $threshold): bool
    {
        ++$this->softBounces;

        if ($this->softBounces >= max(1, $threshold)) {
            $this->bounced();

            return true;
        }

        return false;
    }

    /**
     * A message delivered resets the count of soft bounces.
     */
    public function delivered(): void
    {
        $this->softBounces = 0;
    }

    public function linkExternalId(?string $externalId): void
    {
        $this->externalId = $this->externalId($externalId);
    }

    /**
     * Whether a message may be sent now: active, or paused with the pause over.
     */
    public function canReceive(DateTimeImmutable $now): bool
    {
        return SubscriberStatus::Active === $this->status
            || (SubscriberStatus::Paused === $this->status && $this->pausedUntil instanceof DateTimeImmutable && $this->pausedUntil->getTimestamp() <= $now->getTimestamp());
    }

    public function isSuppressed(): bool
    {
        return SubscriberStatus::Bounced === $this->status || SubscriberStatus::Complained === $this->status;
    }

    private function assertReachable(): void
    {
        if ($this->isSuppressed()) {
            throw InvalidNewsletterValue::because('An address that bounced or complained is never subscribed again.');
        }
    }

    private function externalId(?string $externalId): ?string
    {
        if (null !== $externalId && ('' === $externalId || \strlen($externalId) > self::MAX_EXTERNAL_ID_LENGTH || 1 === preg_match('/[\x00-\x1F\x7F]/', $externalId))) {
            throw InvalidNewsletterValue::because(\sprintf('An external id is 1 to %d bytes, without control characters.', self::MAX_EXTERNAL_ID_LENGTH));
        }

        return $externalId;
    }
}
