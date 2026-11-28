<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Memory;

use DateTimeImmutable;
use Override;
use Phil\LocalPickup\Contract\PickupRepository;
use Phil\LocalPickup\Pickup;
use Phil\Reservation\Booking;
use Phil\Reservation\Interval;

/**
 * A pickup store in an array, for tests and for a single process. It keeps what toArray() gives and rebuilds the
 * pickup on each read, as a real store would.
 */
final class InMemoryPickups implements PickupRepository
{
    /** @var array<string, array<string, mixed>> */
    private array $rows = [];

    #[Override]
    public function find(string $order): ?Pickup
    {
        $row = $this->rows[$order] ?? null;

        return null === $row ? null : Pickup::fromArray($row);
    }

    #[Override]
    public function save(Pickup $pickup): void
    {
        $this->rows[$pickup->order] = $pickup->toArray();
    }

    #[Override]
    public function bookings(string $point, Interval $span): array
    {
        $bookings = [];

        foreach ($this->all() as $pickup) {
            if ($point === $pickup->point->code && $pickup->state->holdsSlot() && $pickup->slot->overlaps($span)) {
                $bookings[] = new Booking($pickup->order, $pickup->slot);
            }
        }

        return $bookings;
    }

    #[Override]
    public function overdue(DateTimeImmutable $at, int $limit): array
    {
        $overdue = array_filter($this->all(), static fn (Pickup $pickup): bool => $pickup->overdue($at));
        usort($overdue, static fn (Pickup $a, Pickup $b): int => [$a->holdUntil?->getTimestamp(), $a->order] <=> [$b->holdUntil?->getTimestamp(), $b->order]);

        return \array_slice(array_map(static fn (Pickup $pickup): string => $pickup->order, $overdue), 0, $limit);
    }

    #[Override]
    public function ofCustomer(string $customer): array
    {
        return array_values(array_filter($this->all(), static fn (Pickup $pickup): bool => $customer === $pickup->customer));
    }

    /**
     * @return list<Pickup>
     */
    private function all(): array
    {
        return array_map(Pickup::fromArray(...), array_values($this->rows));
    }
}
