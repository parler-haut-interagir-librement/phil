<?php

declare(strict_types=1);

namespace Phil\Checkin;

use DateTimeImmutable;
use Phil\Checkin\Exception\InvalidCheckin;

/**
 * One scan of a known credential, recorded with what it decided: admitted, checked out or refused. It names the
 * credential by its identifier, never by its code.
 *
 * An admission or a check-out carries its sequence, the version of the attendance it makes: one more than the version
 * it was decided on. A refusal carries none and changes nothing. A scan made offline keeps the decision of its device,
 * which the holder already met at the door, and, when the ledger decides otherwise on synchronisation, the ledger's
 * decision as its conflict.
 */
final readonly class CheckIn
{
    private function __construct(
        public ScanId $id,
        public string $credentialId,
        public CheckpointId $checkpoint,
        public Direction $direction,
        public ScanOutcome $outcome,
        public DeviceId $device,
        public DateTimeImmutable $scannedAt,
        public DateTimeImmutable $recordedAt,
        public ?int $sequence,
        public bool $offline,
        public ?ScanOutcome $conflict,
    ) {
    }

    /**
     * A check-in decided on an attendance: its sequence follows the attendance's version when it changes it.
     *
     * @throws InvalidCheckin
     */
    public static function decided(
        ScanId $id,
        Credential $credential,
        CheckpointId $checkpoint,
        Direction $direction,
        ScanOutcome $outcome,
        DeviceId $device,
        DateTimeImmutable $scannedAt,
        DateTimeImmutable $recordedAt,
        Attendance $seen,
        bool $offline = false,
        ?ScanOutcome $conflict = null,
    ): self {
        return self::restore($id, $credential->id, $checkpoint, $direction, $outcome, $device, $scannedAt, $recordedAt, $outcome->changesAttendance() ? $seen->version + 1 : null, $offline, $conflict);
    }

    /**
     * A check-in a ledger kept.
     *
     * @throws InvalidCheckin when its parts cannot go together
     */
    public static function restore(
        ScanId $id,
        string $credentialId,
        CheckpointId $checkpoint,
        Direction $direction,
        ScanOutcome $outcome,
        DeviceId $device,
        DateTimeImmutable $scannedAt,
        DateTimeImmutable $recordedAt,
        ?int $sequence,
        bool $offline = false,
        ?ScanOutcome $conflict = null,
    ): self {
        if (\in_array($outcome, [ScanOutcome::Unknown, ScanOutcome::Collision], true) || \in_array($conflict, [ScanOutcome::Unknown, ScanOutcome::Collision], true)) {
            throw InvalidCheckin::because('A check-in is the scan of a known credential.');
        }

        if ((ScanOutcome::Admitted === $outcome && Direction::In !== $direction) || (ScanOutcome::CheckedOut === $outcome && Direction::Out !== $direction)) {
            throw InvalidCheckin::because('An admission is an entry and a check-out an exit.');
        }

        if ($outcome->changesAttendance() !== (null !== $sequence) || (null !== $sequence && $sequence < 1)) {
            throw InvalidCheckin::because('An admission or a check-out has a sequence from 1, a refusal none.');
        }

        if (!$offline && $conflict instanceof ScanOutcome) {
            throw InvalidCheckin::because('Only a scan made offline can conflict with the ledger.');
        }

        return new self($id, $credentialId, $checkpoint, $direction, $outcome, $device, $scannedAt, $recordedAt, $sequence, $offline, $conflict);
    }
}
