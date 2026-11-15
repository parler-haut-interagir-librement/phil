<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\IncidentMode\Contract\LiftScheduler;
use Phil\IncidentMode\Messenger\LiftExpiredIncidentsHandler;
use Phil\IncidentMode\Messenger\LiftIncidentHandler;
use Phil\IncidentMode\Messenger\MessengerLiftScheduler;
use Phil\IncidentMode\NoLiftScheduler;
use Phil\ModerationBundle\Application\Contract\MemberProfiles;
use Phil\ModerationBundle\Application\ProfileTarget;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * What depends on the application's own services, once they are all known:
 *
 * - the profiles become a target of type `profile` when the application gives a `MemberProfiles`;
 * - the incident mode lifts at the end by a delayed message when Messenger has a bus, and by the recurring sweep alone
 *   otherwise.
 */
final readonly class ModerationPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if ($container->has(MemberProfiles::class) && !$container->hasDefinition(ProfileTarget::class)) {
            $container->register(ProfileTarget::class, ProfileTarget::class)
                ->setArguments([new Reference(MemberProfiles::class)])
                ->addTag(ModerationServices::TARGET_TAG);
        }

        if ($container->hasDefinition(MessengerLiftScheduler::class) && !$container->has('messenger.default_bus')) {
            $container->removeDefinition(MessengerLiftScheduler::class);
            $container->removeDefinition(LiftIncidentHandler::class);
            $container->removeDefinition(LiftExpiredIncidentsHandler::class);
            $container->setAlias(LiftScheduler::class, NoLiftScheduler::class);
        }
    }
}
