<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Tests\Support;

use DateTimeImmutable;
use Override;
use Phil\BenefitDeliverables\Contract\DeliverableStore;
use Phil\BenefitDeliverables\DeliverableKey;
use Phil\BenefitDeliverables\Delivery;
use Phil\BenefitDeliverables\Period;

final class InMemoryDeliverables implements DeliverableStore
{
    /** @var array<string, Delivery> */
    public array $deliveries = [];

    /** @var list<list<string>> the members of each read */
    public array $reads = [];

    #[Override]
    public function deliveries(Period $period, array $members): array
    {
        $this->reads[] = $members;

        return array_values(array_filter($this->deliveries, static fn (Delivery $delivery): bool => $delivery->key->period->equals($period) && \in_array($delivery->key->member, $members, true)));
    }

    #[Override]
    public function markDone(array $keys, DateTimeImmutable $at, string $by): int
    {
        $marked = 0;

        foreach ($keys as $key) {
            $id = $this->id($key);

            if (!isset($this->deliveries[$id])) {
                $this->deliveries[$id] = new Delivery($key, $at, $by);
                ++$marked;
            }
        }

        return $marked;
    }

    #[Override]
    public function unmark(DeliverableKey $key): bool
    {
        $id = $this->id($key);
        $had = isset($this->deliveries[$id]);
        unset($this->deliveries[$id]);

        return $had;
    }

    /**
     * A delivery stored as is, of any period: what a store shared by several periods may hand back.
     */
    public function put(Delivery $delivery): void
    {
        $this->deliveries['x'.\count($this->deliveries).$this->id($delivery->key)] = $delivery;
    }

    private function id(DeliverableKey $key): string
    {
        return $key->period->name."\0".$key->benefit."\0".$key->member;
    }
}
