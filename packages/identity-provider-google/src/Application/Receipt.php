<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogle\Application;

/**
 * What came of a security event token: whether it was received before, the events of a known type it carried, and the
 * sessions closed.
 */
final readonly class Receipt
{
    public function __construct(
        public bool $duplicate,
        public int $events = 0,
        public int $sessionsClosed = 0,
    ) {
    }
}
