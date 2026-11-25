<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Infrastructure\DependencyInjection;

use Override;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Twig\Extra\Intl\IntlExtension;

/**
 * Keeps one extension of twig/intl-extra in Twig: the bundle's own is removed when another Twig extension of that class
 * is registered, the one of TwigExtraBundle with its intl part enabled or the application's own. Twig refuses the same
 * extension twice.
 *
 * It runs before the pass of TwigBundle that adds the extensions to the environment.
 */
final readonly class IntlExtensionPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        $others = array_filter(
            array_keys($container->findTaggedServiceIds('twig.extension')),
            fn (string $id): bool => LegalNoticesServices::INTL_EXTENSION !== $id && $this->isIntl($container, $id),
        );

        if ([] !== $others) {
            $container->removeDefinition(LegalNoticesServices::INTL_EXTENSION);
        }
    }

    private function isIntl(ContainerBuilder $container, string $id): bool
    {
        $class = $container->getParameterBag()->resolveValue($container->findDefinition($id)->getClass() ?? $id);

        return IntlExtension::class === $class;
    }
}
