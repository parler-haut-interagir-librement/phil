<?php

declare(strict_types=1);

namespace Phil\HighlightBundle\Tests\Unit;

use Override;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * An extension under the alias of AssetMapperBundle's, so the bundle sees AssetMapper without booting it.
 */
final class AssetMapperStub extends Extension
{
    #[Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
    }

    #[Override]
    public function getAlias(): string
    {
        return 'asset_mapper';
    }
}
