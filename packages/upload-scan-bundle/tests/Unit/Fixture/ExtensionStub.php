<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * An extension under the alias of another bundle's, so that the bundle sees that bundle registered without booting it.
 */
final class ExtensionStub extends Extension
{
    public function __construct(
        private readonly string $alias,
    ) {
    }

    #[Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
    }

    #[Override]
    public function getAlias(): string
    {
        return $this->alias;
    }
}
