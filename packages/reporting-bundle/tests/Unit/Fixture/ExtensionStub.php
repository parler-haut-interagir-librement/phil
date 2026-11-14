<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * An extension answering to the alias of another bundle's, so that the bundle sees that bundle installed.
 */
final class ExtensionStub extends Extension
{
    public function __construct(private readonly string $alias)
    {
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
