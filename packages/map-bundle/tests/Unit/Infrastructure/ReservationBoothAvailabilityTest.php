<?php

declare(strict_types=1);

namespace Phil\MapBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\MapBundle\Domain\Enum\BoothState;
use Phil\MapBundle\Domain\ValueObject\Booth;
use Phil\MapBundle\Infrastructure\Reservation\ReservationBoothAvailability;
use Phil\MapBundle\Tests\Fixtures\Reservations as ReservationStore;
use Phil\MapBundle\Tests\Fixtures\Resources;
use Phil\ReservationBundle\Application\Reservations;
use Phil\ReservationBundle\Domain\Model\BookableResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(ReservationBoothAvailability::class)]
final class ReservationBoothAvailabilityTest extends TestCase
{
    public function testEachBoothInTheStateOfItsResource(): void
    {
        $resources = new Resources();
        $free = BookableResource::create('Free booth');
        $taken = BookableResource::create('Taken booth');
        $closed = BookableResource::create('Closed booth');
        $closed->restrict(minimumNotice: 1_000_000);

        $inactive = BookableResource::create('Inactive booth');
        $inactive->activate(false);

        foreach ([$free, $taken, $closed, $inactive] as $resource) {
            $resources->save($resource);
        }

        $reservations = new Reservations($resources, new ReservationStore(), new MockClock('2026-01-01T00:00:00Z'));
        $start = new DateTimeImmutable('2026-06-01T09:00:00Z');
        $end = new DateTimeImmutable('2026-06-01T17:00:00Z');
        $reservations->book($taken->id, $start, $end);

        self::assertSame([
            'free' => BoothState::Free,
            'taken' => BoothState::Taken,
            'closed' => BoothState::Closed,
            'inactive' => BoothState::Unknown,
            'missing' => BoothState::Unknown,
            'none' => BoothState::Unknown,
        ], new ReservationBoothAvailability($reservations)->states([
            Booth::of('free', 'A', 0, 0, 1, 1, $free->id),
            Booth::of('taken', 'B', 0, 0, 1, 1, $taken->id),
            Booth::of('closed', 'C', 0, 0, 1, 1, $closed->id),
            Booth::of('inactive', 'D', 0, 0, 1, 1, $inactive->id),
            Booth::of('missing', 'E', 0, 0, 1, 1, 'nowhere'),
            Booth::of('none', 'F', 0, 0, 1, 1),
        ], $start, $end));
    }
}
