<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Exception;

use Phil\NotificationsBundle\Application\Delivery\DeliveryResult;
use Phil\NotificationsBundle\Domain\Exception\NotificationsException;
use RuntimeException;

/**
 * No channel took a notification, and at least one was refused or held back by an open circuit: thrown at the end of
 * the delivery so that Messenger tries the recipient's message again. The message names the type and the channels with
 * their reasons, never the recipient nor an address.
 */
final class DeliveryFailed extends RuntimeException implements NotificationsException
{
    public function __construct(
        string $message,
        public readonly DeliveryResult $result,
    ) {
        parent::__construct($message);
    }

    public static function of(DeliveryResult $result): self
    {
        return new self(\sprintf('The notification "%s" reached none of its channels: %s.', $result->type, $result->context()['skipped']), $result);
    }
}
