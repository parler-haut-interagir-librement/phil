<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\Lti\Contract\Roster;
use Phil\Lti\Platform\Nrps\InMemoryRoster;
use Phil\LtiBundle\Application\Contract\LaunchMessages;
use Phil\LtiBundle\Infrastructure\Twig\KitComponents;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * What the application gives the bundle, or its default: an empty roster when it has none, so that the services of the
 * platform answer 404 for every context; a stop with a message when the platform side is enabled without
 * `LaunchMessages`; and the recipes of the kit named after the components subdirectory of the Symfony UX Toolkit,
 * unless the configuration names one.
 */
final readonly class DefaultsPass implements CompilerPassInterface
{
    private const string TOOLKIT_SUBDIRECTORY = '.ux_toolkit.components.subdirectory';

    /**
     * @throws LogicException when the platform side lacks the application's LaunchMessages
     */
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (true === ($container->hasParameter(LtiServices::PLATFORM_PARAMETER) ? $container->getParameter(LtiServices::PLATFORM_PARAMETER) : false)) {
            if (!$container->has(Roster::class)) {
                $container->register(InMemoryRoster::class, InMemoryRoster::class);
                $container->setAlias(Roster::class, InMemoryRoster::class)->setPublic(true);
            }

            if (!$container->has(LaunchMessages::class)) {
                throw new LogicException(\sprintf('The platform side of phil/lti-bundle builds its launches with the application\'s "%s": implement it and alias it to its service.', LaunchMessages::class));
            }
        }

        if (!$container->hasDefinition(KitComponents::class)) {
            return;
        }

        $definition = $container->getDefinition(KitComponents::class);

        if (null !== $definition->getArgument(0)) {
            return;
        }

        $subdirectory = $container->hasParameter(self::TOOLKIT_SUBDIRECTORY) ? $container->getParameter(self::TOOLKIT_SUBDIRECTORY) : null;
        $definition->setArgument(0, \is_string($subdirectory) ? $subdirectory : '');
    }
}
