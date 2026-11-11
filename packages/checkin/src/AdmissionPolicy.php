<?php

declare(strict_types=1);

namespace Phil\Checkin;

use DateTimeImmutable;
use Phil\Checkin\Exception\InvalidCheckin;

/**
 * How the checkpoints of an application decide a scan: how early before its opening a credential is let in, and
 * whether a holder inside must check out before coming in again (anti-passback), so that a code passed back over the
 * fence does not admit a second person.
 *
 * The decision is pure: the same credential, attendance, checkpoint, direction and instant give the same outcome,
 * online at the desk and offline on the device, whose script follows the same order.
 */
final readonly class AdmissionPolicy
{
    public const int MAX_EARLY_SECONDS = 86_400;

    public function __construct(
        public int $earlySeconds = 0,
        public bool $antiPassback = true,
    ) {
        if ($earlySeconds < 0 || $earlySeconds > self::MAX_EARLY_SECONDS) {
            throw InvalidCheckin::because(\sprintf('A credential is let in 0 to %d seconds before it opens.', self::MAX_EARLY_SECONDS));
        }
    }

    /**
     * The outcome of a scan, in this order: a revoked credential, a checkpoint it does not open, then a check-out of
     * a holder inside or not; for an entry, a credential not yet open or closed, a holder already inside under
     * anti-passback, entries used up, and otherwise an admission.
     */
    public function decide(Credential $credential, Attendance $attendance, CheckpointId $checkpoint, Direction $direction, DateTimeImmutable $at): ScanOutcome
    {
        if (CredentialStatus::Revoked === $credential->status) {
            return ScanOutcome::Revoked;
        }

        if (!$credential->opens($checkpoint)) {
            return ScanOutcome::WrongCheckpoint;
        }

        if (Direction::Out === $direction) {
            return $attendance->inside ? ScanOutcome::CheckedOut : ScanOutcome::NotInside;
        }

        $now = $at->getTimestamp();

        if ($credential->validFrom instanceof DateTimeImmutable && $now < $credential->validFrom->getTimestamp() - $this->earlySeconds) {
            return ScanOutcome::NotYetValid;
        }

        if ($credential->validUntil instanceof DateTimeImmutable && $now > $credential->validUntil->getTimestamp()) {
            return ScanOutcome::Expired;
        }

        if (($this->antiPassback && $attendance->inside) || (null !== $credential->maxEntries && $attendance->entries >= $credential->maxEntries)) {
            return ScanOutcome::AlreadyAdmitted;
        }

        return ScanOutcome::Admitted;
    }
}
