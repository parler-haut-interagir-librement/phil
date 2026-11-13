<?php

declare(strict_types=1);

namespace Phil\PrintPreflightBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\FontsBundle\Application\FontCatalogue;
use Phil\PrintPreflightBundle\Application\Port\FontResolver;
use Phil\PrintPreflightBundle\Infrastructure\Fonts\CatalogueFontResolver;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Removes the font resolver of phil/fonts-bundle when the package is installed but its bundle is not enabled, unless
 * the application aliased the port to a resolver of its own.
 */
final readonly class FontCataloguePass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(CatalogueFontResolver::class) || $container->has(FontCatalogue::class)) {
            return;
        }

        $container->removeDefinition(CatalogueFontResolver::class);

        if ($container->hasAlias(FontResolver::class) && CatalogueFontResolver::class === (string) $container->getAlias(FontResolver::class)) {
            $container->removeAlias(FontResolver::class);
        }
    }
}
