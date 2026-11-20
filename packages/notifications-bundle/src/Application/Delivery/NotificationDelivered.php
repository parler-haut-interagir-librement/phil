<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Delivery;

/**
 * Dispatched after each delivery to one recipient, whatever its status, before a total failure is thrown for the
 * retry: for a metric, an audit trail or an alert. It carries the recipient's identifier, never an address.
 */
final readonly class NotificationDelivered
{
    public function __construct(
        public DeliveryResult $result,
    ) {
    }
}
