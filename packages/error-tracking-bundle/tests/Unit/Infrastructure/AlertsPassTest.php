<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit\Infrastructure;

use Phil\ErrorTracking\ErrorTracker;
use Phil\ErrorTrackingBundle\Application\AlertDestination;
use Phil\ErrorTrackingBundle\Application\UndeliveredAlerter;
use Phil\ErrorTrackingBundle\Infrastructure\DependencyInjection\AlertsPass;
use Phil\ErrorTrackingBundle\Infrastructure\Messenger\AlertErrorGroupHandler;
use Phil\ErrorTrackingBundle\Infrastructure\Messenger\MessengerAlerter;
use Phil\ErrorTrackingBundle\Infrastructure\Notifier\ChatterAlerter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(AlertsPass::class)]
final class AlertsPassTest extends TestCase
{
    public function testWithoutAChatterTheAlertsAreLoggedAndTheDestinationIsNone(): void
    {
        $container = $this->container(queued: true, services: ['messenger.default_bus']);

        new AlertsPass()->process($container);

        self::assertEquals(new Reference(UndeliveredAlerter::class), $container->getDefinition(ErrorTracker::class)->getArgument(6));
        $undelivered = $container->getDefinition(UndeliveredAlerter::class);
        self::assertSame(UndeliveredAlerter::class, $undelivered->getClass());
        self::assertEquals([new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $undelivered->getArguments());
        self::assertSame([AlertDestination::None->value], $container->getDefinition(AlertDestination::class)->getArguments());
        self::assertFalse($container->hasDefinition(ChatterAlerter::class));
        self::assertFalse($container->hasDefinition(MessengerAlerter::class));
        self::assertFalse($container->hasDefinition(AlertErrorGroupHandler::class));
    }

    public function testWithoutAChatterOrADestinationServiceTheAlertsAreStillLogged(): void
    {
        $container = $this->container(queued: false, services: []);
        $container->removeDefinition(AlertDestination::class);

        new AlertsPass()->process($container);

        self::assertEquals(new Reference(UndeliveredAlerter::class), $container->getDefinition(ErrorTracker::class)->getArgument(6));
        self::assertFalse($container->hasDefinition(AlertDestination::class));
    }

    public function testWithoutABusTheAlertsAreSentAtOnce(): void
    {
        $container = $this->container(queued: true, services: ['chatter']);

        new AlertsPass()->process($container);

        self::assertSame([AlertDestination::Chat->value], $container->getDefinition(AlertDestination::class)->getArguments());
        self::assertFalse($container->hasDefinition(UndeliveredAlerter::class));

        self::assertEquals(new Reference(ChatterAlerter::class), $container->getDefinition(ErrorTracker::class)->getArgument(6));
        self::assertTrue($container->hasDefinition(ChatterAlerter::class));
        self::assertFalse($container->hasDefinition(MessengerAlerter::class));
        self::assertFalse($container->hasDefinition(AlertErrorGroupHandler::class));
    }

    public function testWithBothTheAlertsAreQueued(): void
    {
        $container = $this->container(queued: true, services: ['chatter', 'messenger.default_bus']);

        new AlertsPass()->process($container);

        self::assertEquals(new Reference(MessengerAlerter::class), $container->getDefinition(ErrorTracker::class)->getArgument(6));
        self::assertTrue($container->hasDefinition(MessengerAlerter::class));
        self::assertTrue($container->hasDefinition(AlertErrorGroupHandler::class));
    }

    public function testAlertsSentAtOnceAskNoBus(): void
    {
        $container = $this->container(queued: false, services: ['chatter']);

        new AlertsPass()->process($container);

        self::assertEquals(new Reference(ChatterAlerter::class), $container->getDefinition(ErrorTracker::class)->getArgument(6));
        self::assertTrue($container->hasDefinition(ChatterAlerter::class));
    }

    public function testAnotherAlerterIsLeftAlone(): void
    {
        $container = new ContainerBuilder();

        new AlertsPass()->process($container);

        self::assertFalse($container->hasDefinition(ErrorTracker::class));
    }

    /**
     * @param list<string> $services
     */
    private function container(bool $queued, array $services): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(ChatterAlerter::class);
        $container->register(AlertDestination::class)->setArguments([AlertDestination::Chat->value]);
        $container->register(ErrorTracker::class)->setArguments([1, 2, 3, 4, 5, 6, new Reference($queued ? MessengerAlerter::class : ChatterAlerter::class)]);

        if ($queued) {
            $container->register(MessengerAlerter::class);
            $container->register(AlertErrorGroupHandler::class);
        }

        foreach ($services as $service) {
            $container->register($service, stdClass::class);
        }

        return $container;
    }
}
