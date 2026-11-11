<?php

declare(strict_types=1);

namespace Phil\Checkin\Contract;

use Phil\Checkin\Attendance;
use Phil\Checkin\CheckIn;
use Phil\Checkin\ScanId;

/**
 * Where the check-ins are kept, shared by every device and every process.
 */
interface CheckInLedger
{
    /**
     * Where a credential stands; {@see Attendance::none()} for one never scanned.
     */
    public function attendance(string $credentialId): Attendance;

    /**
     * Where several credentials stand at once, for an offline manifest: one entry per identifier asked, by
     * identifier.
     *
     * @param list<string> $credentialIds
     *
     * @return array<string, Attendance>
     */
    public function attendances(array $credentialIds): array;

    /**
     * The check-in recorded under a scan identifier, if any.
     *
     * @phpstan-impure
     */
    public function find(ScanId $id): ?CheckIn;

    /**
     * Records a check-in, atomically: false, and nothing recorded, when its scan identifier is already recorded, or
     * when it carries a sequence and the credential's attendance is no longer at the version before it — another
     * scan was recorded first.
     */
    public function append(CheckIn $checkIn): bool;
}
