<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Memory;

use Override;
use Phil\LocalPickup\Contract\HandOverLog;
use Phil\LocalPickup\HandOver;

/**
 * A hand-over journal in an array, for tests and for a single process.
 */
final class InMemoryHandOverLog implements HandOverLog
{
    /** @var list<HandOver> */
    private array $entries = [];

    #[Override]
    public function record(HandOver $handOver): void
    {
        $this->entries[] = $handOver;
    }

    #[Override]
    public function ofCustomer(string $customer): array
    {
        return array_values(array_filter($this->entries, static fn (HandOver $entry): bool => $customer === $entry->customer));
    }

    #[Override]
    public function eraseCollectors(string $customer): void
    {
        $this->entries = array_map(static fn (HandOver $entry): HandOver => $customer === $entry->customer ? $entry->withoutNames() : $entry, $this->entries);
    }

    /**
     * Every entry, in the order written.
     *
     * @return list<HandOver>
     */
    public function all(): array
    {
        return $this->entries;
    }
}
