<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Tests\Unit;

use Override;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * An extension under the alias of phil/media-bundle's, so the bundle sees the media library without booting it.
 */
final class MediaStub extends Extension
{
    #[Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
    }

    #[Override]
    public function getAlias(): string
    {
        return 'phil_media';
    }
}
