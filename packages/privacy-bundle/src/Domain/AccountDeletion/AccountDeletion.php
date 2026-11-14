<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\AccountDeletion;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;

/**
 * One request to delete an account, from the moment it is made until the person is erased or changes their mind.
 *
 * **The person is an opaque, stable identifier, never an e-mail address**: this record outlives the account, so that
 * an erasure can be replayed after a backup restore, and an address kept here would be the very data it erased. The
 * identifier of the request is drawn at random, and it is what messages and log lines carry.
 *
 * The place is changed by the workflow, through `getPlace()` and `setPlace()`, and only under the lock of the person.
 */
final class AccountDeletion
{
    public const string ID_PATTERN = '/^[0-9a-f]{32}$/D';

    public function __construct(
        public readonly string $id,
        public readonly DataSubject $subject,
        private DeletionPlace $place,
        public readonly DateTimeImmutable $requestedAt,
        public readonly DateTimeImmutable $effectiveAt,
        private ?DateTimeImmutable $closedAt = null,
        private ?string $exportReference = null,
    ) {
        if (1 !== preg_match(self::ID_PATTERN, $id)) {
            throw InvalidPrivacyValue::because('An account deletion identifier is 32 lowercase hexadecimal characters.');
        }
    }

    public function getPlace(): DeletionPlace
    {
        return $this->place;
    }

    public function setPlace(DeletionPlace $place): void
    {
        $this->place = $place;
    }

    public function closedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function exportReference(): ?string
    {
        return $this->exportReference;
    }

    public function isOpen(): bool
    {
        return DeletionPlace::Requested === $this->place || DeletionPlace::Exported === $this->place;
    }

    /**
     * Whether the delay is over and the request still has something to do.
     */
    public function isDue(DateTimeImmutable $now): bool
    {
        return $this->isOpen() && $now >= $this->effectiveAt;
    }

    /**
     * Whether the person can still change their mind: only during the delay, before anything was exported.
     */
    public function isCancellable(DateTimeImmutable $now): bool
    {
        return DeletionPlace::Requested === $this->place && $now < $this->effectiveAt;
    }

    public function exportedTo(string $reference): void
    {
        $this->exportReference = $reference;
    }

    public function closeAt(DateTimeImmutable $now): void
    {
        $this->closedAt = $now;
    }
}
