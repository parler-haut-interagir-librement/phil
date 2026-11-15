<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Functional\App;

use Override;
use Phil\CommerceBundle\Application\Port\PaymentAttemptStateStore;
use Phil\CommerceBundle\Domain\ValueObject\PaymentAttemptKey;
use Phil\CommerceBundle\Domain\ValueObject\PaymentAttemptState;

/**
 * The in-memory store `doc/testing.md` shows: each state with the time-to-live it was saved with.
 */
final class InMemoryPaymentAttemptStateStore implements PaymentAttemptStateStore
{
    /**
     * @var array<string, array{PaymentAttemptState, int}>
     */
    public array $saved = [];

    #[Override]
    public function find(PaymentAttemptKey $key): ?PaymentAttemptState
    {
        return $this->saved[$key->value][0] ?? null;
    }

    #[Override]
    public function save(PaymentAttemptKey $key, PaymentAttemptState $state, int $timeToLiveSeconds): void
    {
        $this->saved[$key->value] = [$state, $timeToLiveSeconds];
    }
}
