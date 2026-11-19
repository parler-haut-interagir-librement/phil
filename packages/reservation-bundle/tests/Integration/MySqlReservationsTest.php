<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Tests\Integration;

use Override;
use Phil\ReservationBundle\Application\Reservations;
use Phil\ReservationBundle\Domain\Model\BookableResource;
use Phil\ReservationBundle\Domain\Model\Reservation;
use Phil\ReservationBundle\Infrastructure\Doctrine\DoctrineReservationRepository;
use Phil\ReservationBundle\Infrastructure\Doctrine\DoctrineResourceRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(DoctrineResourceRepository::class)]
#[CoversClass(DoctrineReservationRepository::class)]
#[CoversClass(BookableResource::class)]
#[CoversClass(Reservation::class)]
#[CoversClass(Reservations::class)]
#[Group('mysql')]
final class MySqlReservationsTest extends ServerTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }
}
