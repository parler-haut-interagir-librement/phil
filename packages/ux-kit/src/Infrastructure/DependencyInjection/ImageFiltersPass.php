<?php

declare(strict_types=1);

namespace Phil\UxKit\Infrastructure\DependencyInjection;

use Override;
use Phil\UxKit\Infrastructure\LiipImagine\ImageFilters;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Leaves `ImageFilters` out when the services of LiipImagine are not there, its classes installed but its bundle not
 * registered: `image_filters()` then says that LiipImagine is missing.
 */
final readonly class ImageFiltersPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('liip_imagine.cache.manager') || !$container->has('liip_imagine.filter.configuration')) {
            $container->removeDefinition(ImageFilters::class);
        }
    }
}
