<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Message;

/**
 * Asks a worker to read the bounce mailbox.
 */
final readonly class FetchBounces
{
    public function __construct(
        public int $limit = 100,
    ) {
    }
}
