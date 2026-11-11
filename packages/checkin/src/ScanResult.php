<?php

declare(strict_types=1);

namespace Phil\Checkin;

/**
 * What the desk answers a scan: its outcome, the credential when the code is known, the attendance once the scan is
 * recorded, and the check-in recorded. A scan sent again under the same identifier — same credential, checkpoint,
 * device and direction — is answered with the check-in recorded the first time, marked as replayed; another scan under
 * an identifier already recorded is a collision.
 */
final readonly class ScanResult
{
    private function __construct(
        public ScanOutcome $outcome,
        public ?Credential $credential,
        public Attendance $attendance,
        public ?CheckIn $checkIn,
        public bool $replayed,
    ) {
    }

    /**
     * A code no resolver knows: nothing is recorded.
     */
    public static function unknown(): self
    {
        return new self(ScanOutcome::Unknown, null, Attendance::none(), null, false);
    }

    /**
     * A scan identifier recorded for another scan: nothing is recorded, and neither the outcome nor the check-in
     * recorded under it is given back.
     */
    public static function collided(): self
    {
        return new self(ScanOutcome::Collision, null, Attendance::none(), null, false);
    }

    public static function recorded(Credential $credential, Attendance $attendance, CheckIn $checkIn): self
    {
        return new self($checkIn->outcome, $credential, $attendance, $checkIn, false);
    }

    /**
     * A scan recorded before under the same identifier; the credential is given only when it is the one recorded.
     */
    public static function replayed(CheckIn $checkIn, ?Credential $credential, Attendance $attendance): self
    {
        return new self($checkIn->outcome, $credential?->id === $checkIn->credentialId ? $credential : null, $attendance, $checkIn, true);
    }
}
