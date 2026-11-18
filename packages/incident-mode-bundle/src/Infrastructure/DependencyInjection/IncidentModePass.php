<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\IncidentMode\Contract\LiftScheduler;
use Phil\IncidentMode\Messenger\LiftExpiredIncidentsHandler;
use Phil\IncidentMode\Messenger\LiftIncidentHandler;
use Phil\IncidentMode\Messenger\MessengerLiftScheduler;
use Phil\IncidentMode\NoLiftScheduler;
use Phil\IncidentModeBundle\Infrastructure\Scheduler\IncidentSchedule;
use Phil\IncidentModeBundle\Infrastructure\Translation\IncidentWording;
use Phil\IncidentModeBundle\Infrastructure\Twig\KitComponents;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * What depends on the application's own services, once they are all known:
 *
 * - without the configured message bus, an incident is lifted at its end by the sweep of `phil:incident:lift
 *   --expired` alone: the delayed message, its handlers and the schedule go;
 * - without a translator, the words are those of the translation keys, through Symfony's identity translator;
 * - the recipes of the kit are named after the components subdirectory of the Symfony UX Toolkit, unless the option
 *   `kit_subdirectory` names one.
 */
final readonly class IncidentModePass implements CompilerPassInterface
{
    private const string TOOLKIT_SUBDIRECTORY = '.ux_toolkit.components.subdirectory';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        $bus = $container->hasParameter(IncidentModeServices::BUS_PARAMETER) ? $container->getParameter(IncidentModeServices::BUS_PARAMETER) : null;

        if (\is_string($bus) && !$container->has($bus)) {
            foreach ([MessengerLiftScheduler::class, LiftIncidentHandler::class, LiftExpiredIncidentsHandler::class, IncidentSchedule::class] as $service) {
                $container->removeDefinition($service);
            }

            $container->setAlias(LiftScheduler::class, NoLiftScheduler::class);
        }

        if ($container->hasDefinition(IncidentWording::class) && !$container->has('translator')) {
            $container->getDefinition(IncidentWording::class)->setArgument(0, new Definition(IdentityTranslator::class));
        }

        if ($container->hasDefinition(KitComponents::class) && null === $container->getDefinition(KitComponents::class)->getArgument(0)) {
            $subdirectory = $container->hasParameter(self::TOOLKIT_SUBDIRECTORY) ? $container->getParameter(self::TOOLKIT_SUBDIRECTORY) : null;
            $container->getDefinition(KitComponents::class)->setArgument(0, \is_string($subdirectory) ? $subdirectory : '');
        }
    }
}
