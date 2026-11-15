<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application\Port;

use Phil\CommerceBundle\Domain\ValueObject\PaymentAttemptKey;
use Phil\CommerceBundle\Domain\ValueObject\PaymentAttemptState;

/**
 * Keeps one state per limiter key, and lets it expire.
 *
 * **Every saved state carries a time-to-live, and the store must honour it**: a client IP address is personal
 * information, and the window of the limiter is what bounds how long it is kept. The adapter belongs to the
 * application (a key-value store with native expiry is the natural fit). It stores exactly the state it is
 * given — no verdict, no penalty counter, no history.
 */
interface PaymentAttemptStateStore
{
    public function find(PaymentAttemptKey $key): ?PaymentAttemptState;

    /**
     * @param int $timeToLiveSeconds the policy's window, renewed by each attempt
     */
    public function save(PaymentAttemptKey $key, PaymentAttemptState $state, int $timeToLiveSeconds): void;
}
