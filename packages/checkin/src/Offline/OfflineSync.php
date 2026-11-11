<?php

declare(strict_types=1);

namespace Phil\Checkin\Offline;

use DateTimeImmutable;
use Phil\Checkin\AdmissionPolicy;
use Phil\Checkin\CheckIn;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\Contract\CheckInLedger;
use Phil\Checkin\Contract\CredentialCatalog;
use Phil\Checkin\Credential;
use Phil\Checkin\DeviceId;
use Phil\Checkin\Exception\InvalidCheckin;
use Phil\Checkin\Exception\LedgerContention;
use Phil\Checkin\ScanOutcome;
use Psr\Clock\ClockInterface;

/**
 * Records the journal of a device back online. The scans are applied by instant, then by identifier; each is decided
 * again by the policy on the ledger's attendance at its instant, and recorded with the device's decision, which the
 * holder already met at the door — with the ledger's decision as its conflict when the two differ, so that the staff
 * can see the entries two offline devices both admitted.
 *
 * A scan whose identifier is recorded is a duplicate when the scan recorded is the same — made offline, of the same
 * credential, at the same checkpoint, by the same device, in the same direction, with the same outcome — and a
 * collision otherwise, recorded neither way; a scan whose credential the catalog no longer knows, or whose
 * instant is older than the maximum age or later than now plus the clock skew, is rejected. An instant in the future
 * within the skew is recorded as now.
 *
 * The ledger's attendance follows the device's claim: a scan is not checked against the manifest issued to the
 * device, so whoever posts a journal is trusted as much as a door scanner, and the application authenticates devices.
 */
final readonly class OfflineSync
{
    public const int ATTEMPTS = 3;

    /**
     * @param int $skewSeconds   how far ahead of the server a device's clock may be, from 0 to an hour
     * @param int $maxAgeSeconds how old a scan may be, from a minute to thirty days
     *
     * @throws InvalidCheckin
     */
    public function __construct(
        private CredentialCatalog $catalog,
        private CheckInLedger $ledger,
        private ClockInterface $clock,
        private AdmissionPolicy $policy = new AdmissionPolicy(),
        private int $skewSeconds = 300,
        private int $maxAgeSeconds = 172_800,
    ) {
        if ($skewSeconds < 0 || $skewSeconds > 3_600) {
            throw InvalidCheckin::because('A device clock may be 0 to 3600 seconds ahead.');
        }

        if ($maxAgeSeconds < 60 || $maxAgeSeconds > 2_592_000) {
            throw InvalidCheckin::because('A scan made offline may be 60 to 2592000 seconds old.');
        }
    }

    /**
     * @param list<OfflineScan> $scans
     *
     * @throws LedgerContention when the attendance of a credential kept changing under one of the scans
     */
    public function apply(CheckpointId $checkpoint, DeviceId $device, array $scans): SyncReport
    {
        usort($scans, static fn (OfflineScan $a, OfflineScan $b): int => [$a->scannedAt->getTimestamp(), $a->id->value] <=> [$b->scannedAt->getTimestamp(), $b->id->value]);

        $now = $this->clock->now();
        $report = new SyncReport();

        foreach ($scans as $scan) {
            $report->add($scan->id, $this->record($scan, $checkpoint, $device, $now));
        }

        return $report;
    }

    private function record(OfflineScan $scan, CheckpointId $checkpoint, DeviceId $device, DateTimeImmutable $now): SyncStatus
    {
        $known = $this->ledger->find($scan->id);

        if ($known instanceof CheckIn) {
            return $this->known($known, $scan, $checkpoint, $device);
        }

        $at = $scan->scannedAt->getTimestamp();

        if ($at < $now->getTimestamp() - $this->maxAgeSeconds || $at > $now->getTimestamp() + $this->skewSeconds) {
            return SyncStatus::Rejected;
        }

        $credential = $this->catalog->find($scan->credentialId);

        if (!$credential instanceof Credential) {
            return SyncStatus::Rejected;
        }

        $scannedAt = $at > $now->getTimestamp() ? $now : $scan->scannedAt;

        for ($attempt = 0; $attempt < self::ATTEMPTS; ++$attempt) {
            $seen = $this->ledger->attendance($credential->id);
            $decided = $this->policy->decide($credential, $seen, $checkpoint, $scan->direction, $scannedAt);
            $conflict = $decided === $scan->outcome ? null : $decided;
            $checkIn = CheckIn::decided($scan->id, $credential, $checkpoint, $scan->direction, $scan->outcome, $device, $scannedAt, $now, $seen, true, $conflict);

            if ($this->ledger->append($checkIn)) {
                return $conflict instanceof ScanOutcome ? SyncStatus::Conflict : SyncStatus::Recorded;
            }

            $known = $this->ledger->find($scan->id);

            if ($known instanceof CheckIn) {
                return $this->known($known, $scan, $checkpoint, $device);
            }
        }

        throw LedgerContention::after(self::ATTEMPTS);
    }

    /**
     * What becomes of a scan whose identifier is recorded: a duplicate when it is the same scan, a collision otherwise.
     */
    private function known(CheckIn $known, OfflineScan $scan, CheckpointId $checkpoint, DeviceId $device): SyncStatus
    {
        $same = $known->offline
            && $known->credentialId === $scan->credentialId
            && $known->checkpoint->value === $checkpoint->value
            && $known->device->value === $device->value
            && $known->direction === $scan->direction
            && $known->outcome === $scan->outcome;

        return $same ? SyncStatus::Duplicate : SyncStatus::Collision;
    }
}
