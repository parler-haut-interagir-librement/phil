<?php

declare(strict_types=1);

namespace Phil\MapBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\MapBundle\Application\Contract\BoothAvailability;
use Phil\MapBundle\Infrastructure\Reservation\ReservationBoothAvailability;
use Phil\ReservationBundle\Application\Reservations;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Gives the booths the states of phil/reservation-bundle once every bundle is loaded, when its reservations are a
 * service and the configuration names no availability of its own.
 */
final readonly class BoothAvailabilityPass implements CompilerPassInterface
{
    public const string PARAMETER = 'phil_map.booths.default';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(self::PARAMETER) || true !== $container->getParameter(self::PARAMETER) || !$container->has(Reservations::class)) {
            return;
        }

        $container->register(ReservationBoothAvailability::class, ReservationBoothAvailability::class)->setArguments([new Reference(Reservations::class)]);
        $container->setAlias(BoothAvailability::class, ReservationBoothAvailability::class);
    }
}
