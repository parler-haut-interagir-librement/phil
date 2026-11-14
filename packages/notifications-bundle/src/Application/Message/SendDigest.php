<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Message;

use Phil\NotificationsBundle\Application\Preferences\DigestFrequency;

/**
 * Sends one recipient their digest, by identifier.
 */
final readonly class SendDigest
{
    public function __construct(
        public string $recipient,
        public DigestFrequency $frequency,
    ) {
    }
}
