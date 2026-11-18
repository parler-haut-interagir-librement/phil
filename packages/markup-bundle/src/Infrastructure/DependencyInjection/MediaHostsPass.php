<?php

declare(strict_types=1);

namespace Phil\MarkupBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\Markup\Adapter\AmmoniaPurifier;
use Phil\Markup\Adapter\Purifiers;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\HtmlPurifier;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Gives every purifier built by `defaultPolicies()` — of `SanitizerPurifier`, `AmmoniaPurifier` or `Purifiers` — the
 * media hosts of the configuration, and its `purifier_max_bytes` when it is set, once every bundle is loaded: another bundle that registers the same adapter under
 * the same identifier, as `phil/content-bundle` does, may load after this one and replace its definition. A purifier
 * the application built otherwise — another factory, another class — is left alone.
 *
 * When such a bundle points `HtmlPurifier` back to `SanitizerPurifier`, the purifier the configuration chose takes it
 * again: `purifier: sanitizer` is the way to keep the sanitizer.
 */
final readonly class MediaHostsPass implements CompilerPassInterface
{
    public const string PARAMETER = 'phil_markup.media_hosts';

    /** The ceiling of an HTML purified, in bytes, from `purifier_max_bytes`. */
    public const string MAX_BYTES_PARAMETER = 'phil_markup.purifier_max_bytes';

    /** The factories of the purifiers, each with the class of the service it builds. */
    private const array PURIFIERS = [
        [[SanitizerPurifier::class, 'defaultPolicies'], SanitizerPurifier::class],
        [[AmmoniaPurifier::class, 'defaultPolicies'], AmmoniaPurifier::class],
        [[Purifiers::class, 'defaultPolicies'], HtmlPurifier::class],
    ];

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        // Read from the bag as it is: the parameter exists only when the bundle's configuration was loaded.
        $parameters = $container->getParameterBag()->all();

        if (!\array_key_exists(self::PARAMETER, $parameters)) {
            return;
        }

        $arguments = [$parameters[self::PARAMETER]];

        if (\array_key_exists(self::MAX_BYTES_PARAMETER, $parameters)) {
            $arguments['$maxBytes'] = $parameters[self::MAX_BYTES_PARAMETER];
        }

        foreach ($container->getDefinitions() as $definition) {
            if (\in_array([$definition->getFactory(), $definition->getClass()], self::PURIFIERS, true)) {
                $definition->setArguments($arguments);
            }
        }

        if ($container->hasDefinition(MarkupServices::PURIFIER) && $container->hasAlias(HtmlPurifier::class) && SanitizerPurifier::class === (string) $container->getAlias(HtmlPurifier::class)) {
            $container->setAlias(HtmlPurifier::class, MarkupServices::PURIFIER);
        }
    }
}
