<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Message;

use Phil\NotificationsBundle\Application\Preferences\DigestFrequency;

/**
 * Sends the digests of one frequency: the schedule dispatches it every day, and every week.
 */
final readonly class SendDigests
{
    public function __construct(
        public DigestFrequency $frequency,
    ) {
    }
}
