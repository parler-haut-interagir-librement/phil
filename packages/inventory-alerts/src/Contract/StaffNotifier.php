<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Contract;

use Phil\InventoryAlerts\LowStock\LowStockDigest;

/**
 * Tells the staff of a location which SKUs are low there: an email, a notification, a message on a queue.
 */
interface StaffNotifier
{
    /**
     * Sends the digest of one location. A notifier that queues it puts the location and the movement identifiers in
     * the message, never the digest itself.
     */
    public function notify(LowStockDigest $digest): void;
}
