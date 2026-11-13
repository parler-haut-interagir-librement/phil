<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Application\Event;

use Phil\Checkin\CheckIn;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\DeviceId;
use Phil\Checkin\Direction;
use Phil\Checkin\Offline\OfflineScan;
use Phil\Checkin\ScanId;
use Phil\Checkin\ScanOutcome;

/**
 * A scan the endpoints recorded: one decided online, or one of a journal synchronised — refusals included, replays and
 * duplicates not. It carries identifiers only, never a code nor a label: a listener that hands out a badge, counts
 * attendance or rewards it reads the rest from its own records.
 */
final readonly class CheckInRecorded
{
    /**
     * @param bool $conflict whether the server decided otherwise than the device that scanned offline
     */
    public function __construct(
        public ScanId $id,
        public string $credentialId,
        public CheckpointId $checkpoint,
        public DeviceId $device,
        public Direction $direction,
        public ScanOutcome $outcome,
        public bool $offline = false,
        public bool $conflict = false,
    ) {
    }

    public static function online(CheckIn $checkIn): self
    {
        return new self($checkIn->id, $checkIn->credentialId, $checkIn->checkpoint, $checkIn->device, $checkIn->direction, $checkIn->outcome);
    }

    public static function synchronised(OfflineScan $scan, CheckpointId $checkpoint, DeviceId $device, bool $conflict): self
    {
        return new self($scan->id, $scan->credentialId, $checkpoint, $device, $scan->direction, $scan->outcome, true, $conflict);
    }

    /**
     * Whether the holder went in or out: an admission or a check-out, not a refusal.
     */
    public function changesAttendance(): bool
    {
        return $this->outcome->changesAttendance();
    }
}
