<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Infrastructure\DependencyInjection;

use Override;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Removes a service of an integration whose package is installed but whose bundle is not enabled: `EpubFonts` without
 * the `FontCatalogue` of phil/fonts-bundle, `MediaAssets` without the `MediaLibrary` of phil/media-bundle.
 */
final readonly class RequiredServicesPass implements CompilerPassInterface
{
    /**
     * @param array<string, string> $requirements the service that needs another, by the id of the one it needs
     */
    public function __construct(
        private array $requirements,
    ) {
    }

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        foreach ($this->requirements as $service => $required) {
            if (!$container->has($required)) {
                $container->removeDefinition($service);
            }
        }
    }
}
