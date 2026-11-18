<?php

declare(strict_types=1);

namespace Phil\MarkupBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\Markup\Adapter\DefaultCompiler;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Gives every `DefaultCompiler` the ceilings of the configuration, `max_bytes` and `ujo_max_bytes`, once every bundle
 * is loaded: another bundle that registers the compiler under the same identifier, as `phil/content-bundle` does, may
 * load after this one and replace its definition. A compiler whose ceilings are given by position — by the
 * application, which chose them —, or built by a factory, is left alone.
 */
final readonly class MaxBytesPass implements CompilerPassInterface
{
    /** The ceiling of a source in a written format, in bytes, from `max_bytes`. */
    public const string PARAMETER = 'phil_markup.max_bytes';

    /** The ceiling of a UJO source, in bytes, from `ujo_max_bytes`. */
    public const string UJO_PARAMETER = 'phil_markup.ujo_max_bytes';

    /** The positions of the ceilings among the arguments of `DefaultCompiler`. */
    private const array POSITIONS = [2, 3];

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        // Read from the bag as it is: the parameters exist only when the bundle's configuration was loaded.
        $parameters = $container->getParameterBag()->all();

        if (!\array_key_exists(self::PARAMETER, $parameters) || !\array_key_exists(self::UJO_PARAMETER, $parameters)) {
            return;
        }

        foreach ($container->getDefinitions() as $definition) {
            if (DefaultCompiler::class !== $definition->getClass() || null !== $definition->getFactory() || [] !== array_intersect_key($definition->getArguments(), array_flip(self::POSITIONS))) {
                continue;
            }

            $definition->setArgument('$maxBytes', $parameters[self::PARAMETER]);
            $definition->setArgument('$ujoMaxBytes', $parameters[self::UJO_PARAMETER]);
        }
    }
}
