<?php

declare(strict_types=1);

namespace Phil\GuardianConsent;

use DateTimeImmutable;
use DateTimeZone;

/**
 * A member known to be below the age of consent: their jurisdiction and the day they reach the age of consent, never
 * their date of birth; when they were told of the handover, and when they confirmed their own choices.
 */
final readonly class Minor
{
    public DateTimeImmutable $registeredAt;

    public ?DateTimeImmutable $handoverNoticedAt;

    public ?DateTimeImmutable $handedOverAt;

    public function __construct(
        public MemberId $member,
        public string $jurisdiction,
        public CalendarDate $consentAgeOn,
        DateTimeImmutable $registeredAt,
        ?DateTimeImmutable $handoverNoticedAt = null,
        ?DateTimeImmutable $handedOverAt = null,
    ) {
        $utc = new DateTimeZone('UTC');
        $this->registeredAt = $registeredAt->setTimezone($utc);
        $this->handoverNoticedAt = $handoverNoticedAt?->setTimezone($utc);
        $this->handedOverAt = $handedOverAt?->setTimezone($utc);
    }

    /**
     * Whether the age of consent is reached on `$today`, the day it is in the member's jurisdiction.
     */
    public function hasReachedConsentAge(CalendarDate $today): bool
    {
        return !$today->isBefore($this->consentAgeOn);
    }

    public function isHandedOver(): bool
    {
        return $this->handedOverAt instanceof DateTimeImmutable;
    }

    public function withHandoverNoticed(DateTimeImmutable $at): self
    {
        return new self($this->member, $this->jurisdiction, $this->consentAgeOn, $this->registeredAt, $at, $this->handedOverAt);
    }

    public function withHandedOver(DateTimeImmutable $at): self
    {
        return new self($this->member, $this->jurisdiction, $this->consentAgeOn, $this->registeredAt, $this->handoverNoticedAt, $at);
    }
}
