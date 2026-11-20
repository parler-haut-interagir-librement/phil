<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Contract;

use Closure;
use Phil\NotificationsBundle\Application\Delivery\DedupeTicket;
use Phil\NotificationsBundle\Application\Message\SendNotification;
use Phil\NotificationsBundle\Application\NotificationType;

/**
 * One delivery of the same notification to a recipient per window: `NotificationDedupe` on phil/idempotency with
 * `delivery.dedupe` enabled, or the application's own.
 *
 * Two notifications are the same when their type and recipient match and either their `dedupeKey` matches or, without
 * one, their subject, data, actor and origin do; never the identifier a delivery gives a notification. `begin()`
 * reserves the delivery atomically, so that two workers never both send it; the store is shared by every server.
 */
interface DeliveryDedupe
{
    /**
     * Reserves the delivery, or says it was already made: a ticket marked as a duplicate. Null when the type has no
     * window.
     */
    public function begin(SendNotification $message, NotificationType $type, string $recipient): ?DedupeTicket;

    /**
     * Keeps the key for the window: the same notification is a duplicate until it ends.
     */
    public function complete(DedupeTicket $ticket, NotificationType $type): void;

    /**
     * Lets the next attempt with the same notification run.
     */
    public function release(DedupeTicket $ticket, NotificationType $type): void;

    /**
     * Runs `$record` once per key and window, under a key of its own: what keeps the notification in the centre.
     * Returns whether it ran.
     *
     * @param Closure(): void $record
     */
    public function once(SendNotification $message, NotificationType $type, string $recipient, Closure $record): bool;
}
