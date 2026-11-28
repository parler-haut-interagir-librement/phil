<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Tests\Support;

use DateTimeImmutable;
use Override;
use Phil\BenefitDeliverables\Contract\DeliverableStore;
use Phil\BenefitDeliverables\DeliverableKey;
use Phil\BenefitDeliverables\Period;

/**
 * A store that hands back every delivery of the store it wraps, whatever the period asked: a careless adapter.
 */
abstract class ForwardingDeliverables implements DeliverableStore
{
    public function __construct(private readonly InMemoryDeliverables $inner)
    {
    }

    #[Override]
    public function deliveries(Period $period, array $members): array
    {
        return array_values($this->inner->deliveries);
    }

    #[Override]
    public function markDone(array $keys, DateTimeImmutable $at, string $by): int
    {
        return $this->inner->markDone($keys, $at, $by);
    }

    #[Override]
    public function unmark(DeliverableKey $key): bool
    {
        return $this->inner->unmark($key);
    }
}
