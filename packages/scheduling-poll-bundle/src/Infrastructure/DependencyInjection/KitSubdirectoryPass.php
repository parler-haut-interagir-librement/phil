<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\SchedulingPollBundle\Infrastructure\Twig\SchedulingPollTwig;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Names the recipes of the kit after the components subdirectory of the Symfony UX Toolkit, unless the bundle's own
 * configuration says it.
 */
final readonly class KitSubdirectoryPass implements CompilerPassInterface
{
    private const string TOOLKIT_SUBDIRECTORY = '.ux_toolkit.components.subdirectory';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(SchedulingPollTwig::class)) {
            return;
        }

        $definition = $container->getDefinition(SchedulingPollTwig::class);

        if (null !== $definition->getArgument(1)) {
            return;
        }

        $subdirectory = $container->hasParameter(self::TOOLKIT_SUBDIRECTORY) ? $container->getParameter(self::TOOLKIT_SUBDIRECTORY) : null;
        $definition->setArgument(1, \is_string($subdirectory) ? $subdirectory : '');
    }
}
