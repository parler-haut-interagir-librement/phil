<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceSubscriptionBundle\Domain\Model\Seat;
use Phil\CommerceSubscriptionBundle\Domain\Repository\SeatRepository;

final class InMemorySeats implements SeatRepository
{
    /** @var list<Seat> */
    public array $seats = [];

    #[Override]
    public function find(string $subscriptionId, string $member): ?Seat
    {
        return array_find($this->seats, static fn (Seat $seat): bool => $seat->subscriptionId === $subscriptionId && $seat->member === $member);
    }

    #[Override]
    public function holders(string $subscriptionId, ?int $limit = null): array
    {
        $holders = array_values(array_filter($this->seats, static fn (Seat $seat): bool => $seat->subscriptionId === $subscriptionId));
        usort($holders, static fn (Seat $left, Seat $right): int => [$left->assignedAt, $left->member] <=> [$right->assignedAt, $right->member]);

        return \array_slice($holders, 0, $limit);
    }

    #[Override]
    public function count(string $subscriptionId): int
    {
        return \count($this->holders($subscriptionId));
    }

    #[Override]
    public function holdsSeat(string $subscriptionId, string $member, int $seats): bool
    {
        return array_find($this->holders($subscriptionId, $seats), static fn (Seat $seat): bool => $seat->member === $member) instanceof Seat;
    }

    #[Override]
    public function add(Seat $seat): void
    {
        $this->seats[] = $seat;
    }

    #[Override]
    public function remove(Seat $seat): void
    {
        $this->seats = array_values(array_filter($this->seats, static fn (Seat $held): bool => $held !== $seat));
    }
}
