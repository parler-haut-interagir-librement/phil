<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\ModerationBundle\Application\Contract\MemberProfiles;
use Phil\ModerationBundle\Application\ProfileTarget;
use Phil\ModerationBundle\Infrastructure\IncidentMode\IncidentProposal;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * What depends on the application's own services, once they are all known:
 *
 * - the profiles become a target of type `profile` when the application gives a `MemberProfiles`;
 * - the queue offers the incident mode only when `phil/incident-mode-bundle` has its administration on, whose role
 *   the parameter `phil_incident_mode.admin.role` holds.
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

        if ($container->hasDefinition(IncidentProposal::class) && !$container->hasParameter(ModerationServices::INCIDENT_ROLE_PARAMETER)) {
            $container->removeDefinition(IncidentProposal::class);
        }
    }
}
