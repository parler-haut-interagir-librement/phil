<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\ErrorTracking\ErrorTracker;
use Phil\ErrorTrackingBundle\Application\AlertDestination;
use Phil\ErrorTrackingBundle\Application\Message\AlertErrorGroup;
use Phil\ErrorTrackingBundle\Application\UndeliveredAlerter;
use Phil\ErrorTrackingBundle\Infrastructure\Messenger\AlertErrorGroupHandler;
use Phil\ErrorTrackingBundle\Infrastructure\Messenger\MessengerAlerter;
use Phil\ErrorTrackingBundle\Infrastructure\Notifier\ChatterAlerter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Lets the alerts, on by default, go without a destination: without a chatter they reach no one — the degraded state,
 * where `UndeliveredAlerter` logs each alert and `AlertDestination` says so —, and without a Messenger bus they are sent
 * in the request that failed. The handler of the queued alerts is tied to the default bus, the one `MessengerAlerter`
 * dispatches on: without a bus named on its tag, Messenger would give it to every bus, a query bus among them.
 */
final class AlertsPass implements CompilerPassInterface
{
    private const int ALERTER_ARGUMENT = 6;

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ChatterAlerter::class)) {
            return;
        }

        $tracker = $container->getDefinition(ErrorTracker::class);

        if (!$container->has('chatter')) {
            $container->register(UndeliveredAlerter::class, UndeliveredAlerter::class)
                ->setArguments([new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
            $tracker->replaceArgument(self::ALERTER_ARGUMENT, new Reference(UndeliveredAlerter::class));
            $container->removeDefinition(ChatterAlerter::class);
            $this->removeQueue($container);

            if ($container->hasDefinition(AlertDestination::class)) {
                $container->getDefinition(AlertDestination::class)->setArguments([AlertDestination::None->value]);
            }
        } elseif ($container->hasDefinition(MessengerAlerter::class) && !$container->has('messenger.default_bus')) {
            $tracker->replaceArgument(self::ALERTER_ARGUMENT, new Reference(ChatterAlerter::class));
            $this->removeQueue($container);
        } elseif ($container->hasDefinition(AlertErrorGroupHandler::class)) {
            $this->tieToTheDefaultBus($container);
        }
    }

    private function tieToTheDefaultBus(ContainerBuilder $container): void
    {
        $bus = $container->hasAlias('messenger.default_bus') ? (string) $container->getAlias('messenger.default_bus') : 'messenger.default_bus';
        $container->getDefinition(AlertErrorGroupHandler::class)
            ->clearTag('messenger.message_handler')
            ->addTag('messenger.message_handler', ['handles' => AlertErrorGroup::class, 'bus' => $bus]);
    }

    private function removeQueue(ContainerBuilder $container): void
    {
        $container->removeDefinition(MessengerAlerter::class);
        $container->removeDefinition(AlertErrorGroupHandler::class);
    }
}
