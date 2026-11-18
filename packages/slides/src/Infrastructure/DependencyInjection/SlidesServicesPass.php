<?php

declare(strict_types=1);

namespace Phil\Slides\Infrastructure\DependencyInjection;

use Override;
use Phil\PagedMediaBundle\Application\PagedMedia;
use Phil\Slides\Infrastructure\Http\PresenterController;
use Phil\Slides\Infrastructure\Print\DeckPdf;
use Phil\Slides\Infrastructure\Print\DeckPrint;
use Phil\Slides\Infrastructure\Twig\SlidesExtension;
use Phil\Ujo\Ujo;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Completes the services once every bundle is loaded: stops the container, with what to change, when no `Ujo` service
 * renders the slides; removes the print of a deck and the presenter routes without Twig, the PDF without
 * `phil/paged-media-bundle`, the presenter routes without a router; and names the recipes of the kit after the
 * subdirectory of the Symfony UX Toolkit when the configuration names none.
 */
final class SlidesServicesPass implements CompilerPassInterface
{
    private const string TOOLKIT_SUBDIRECTORY = '.ux_toolkit.components.subdirectory';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(Ujo::class)) {
            throw new LogicException('phil/slides renders each slide with the Ujo service of phil/ujo-bundle, which is not registered: enable Phil\UjoBundle\PhilUjoBundle in config/bundles.php.');
        }

        if (!$container->has('twig')) {
            $container->removeDefinition(DeckPrint::class);
            $container->removeDefinition(DeckPdf::class);
            $container->removeDefinition(PresenterController::class);

            return;
        }

        if (!$container->has(PagedMedia::class)) {
            $container->removeDefinition(DeckPdf::class);
        }

        if (!$container->has('router')) {
            $container->removeDefinition(PresenterController::class);
        }

        $this->nameKitSubdirectory($container);
    }

    /**
     * Names the recipes of the kit after the components subdirectory of the Symfony UX Toolkit, unless the bundle's own
     * configuration says it.
     */
    private function nameKitSubdirectory(ContainerBuilder $container): void
    {
        $definition = $container->getDefinition(SlidesExtension::class);

        if (null !== $definition->getArgument(3)) {
            return;
        }

        $subdirectory = $container->hasParameter(self::TOOLKIT_SUBDIRECTORY) ? $container->getParameter(self::TOOLKIT_SUBDIRECTORY) : null;
        $definition->setArgument(3, \is_string($subdirectory) ? $subdirectory : '');
    }
}
