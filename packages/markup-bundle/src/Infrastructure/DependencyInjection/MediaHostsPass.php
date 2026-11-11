<?php

declare(strict_types=1);

namespace Phil\MarkupBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\Markup\Adapter\SanitizerPurifier;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Gives every `SanitizerPurifier` built by `defaultPolicies()` the media hosts of the configuration, once every bundle
 * is loaded: another bundle that registers the same adapter under the same identifier, as `phil/content-bundle` does,
 * may load after this one and replace its definition. A purifier the application built otherwise — another factory,
 * another class — is left alone.
 */
final readonly class MediaHostsPass implements CompilerPassInterface
{
    public const string PARAMETER = 'phil_markup.media_hosts';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        // Read from the bag as it is: the parameter exists only when the bundle's configuration was loaded.
        $parameters = $container->getParameterBag()->all();

        if (!\array_key_exists(self::PARAMETER, $parameters)) {
            return;
        }

        foreach ($container->getDefinitions() as $definition) {
            if (SanitizerPurifier::class === $definition->getClass() && [SanitizerPurifier::class, 'defaultPolicies'] === $definition->getFactory()) {
                $definition->setArguments([$parameters[self::PARAMETER]]);
            }
        }
    }
}
