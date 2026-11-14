<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Handler;

use Phil\NotificationsBundle\Application\Digests;
use Phil\NotificationsBundle\Application\Message\SendDigest;
use Phil\NotificationsBundle\Application\Message\SendDigests;

/**
 * Handles the digest messages: the sweep of one frequency, and one recipient's digest.
 */
final readonly class DigestHandler
{
    public function __construct(
        private Digests $digests,
    ) {
    }

    public function sweep(SendDigests $message): void
    {
        $this->digests->sweep($message->frequency);
    }

    public function send(SendDigest $message): void
    {
        $this->digests->send($message->recipient, $message->frequency);
    }
}
