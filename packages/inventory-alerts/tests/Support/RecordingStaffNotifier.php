<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Tests\Support;

use Phil\InventoryAlerts\Contract\StaffNotifier;
use Phil\InventoryAlerts\LowStock\LowStockDigest;
use RuntimeException;

/**
 * Remembers the digests it is given, or fails at the one it is told to.
 */
final class RecordingStaffNotifier implements StaffNotifier
{
    /** @var list<LowStockDigest> */
    public array $digests = [];

    public function __construct(private readonly ?string $failOnLocation = null)
    {
    }

    public function notify(LowStockDigest $digest): void
    {
        if ($digest->location === $this->failOnLocation) {
            throw new RuntimeException('The mail server is down.');
        }

        $this->digests[] = $digest;
    }
}
