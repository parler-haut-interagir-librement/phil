<?php

declare(strict_types=1);

namespace Phil\Checkin;

use DateTimeImmutable;
use Phil\Checkin\Exception\InvalidCheckin;

/**
 * Where a credential stands: how many times it let its holder in, whether they are inside, and when they last came
 * in. Its version counts the check-ins that changed it, admissions and check-outs: a store records the next one only
 * if the version it was decided on is still the last, so that two devices never both admit the last entry.
 */
final readonly class Attendance
{
    private function __construct(
        public int $version,
        public int $entries,
        public bool $inside,
        public ?DateTimeImmutable $lastEntryAt,
    ) {
    }

    /**
     * A credential never scanned in.
     */
    public static function none(): self
    {
        return new self(0, 0, false, null);
    }

    /**
     * The attendance a store kept.
     *
     * @throws InvalidCheckin when the counts cannot go together
     */
    public static function restore(int $version, int $entries, bool $inside, ?DateTimeImmutable $lastEntryAt): self
    {
        if ($entries < 0 || $entries > $version || ($entries > 0) !== ($lastEntryAt instanceof DateTimeImmutable) || ($inside && 0 === $entries)) {
            throw InvalidCheckin::because('An attendance counts its entries among its check-ins, has a last entry once it has one, and is inside only after an entry.');
        }

        return new self($version, $entries, $inside, $lastEntryAt);
    }

    /**
     * The attendance once a check-in decided on this one is recorded; the same one for a refusal.
     */
    public function after(CheckIn $checkIn): self
    {
        return match ($checkIn->outcome) {
            ScanOutcome::Admitted => new self($this->version + 1, $this->entries + 1, true, $checkIn->scannedAt),
            ScanOutcome::CheckedOut => new self($this->version + 1, $this->entries, false, $this->lastEntryAt),
            default => $this,
        };
    }
}
