<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * An extension answering to the alias of FrameworkBundle's, so that the bundle sees the framework installed.
 */
final class FrameworkStub extends Extension
{
    #[Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
    }

    #[Override]
    public function getAlias(): string
    {
        return 'framework';
    }
}
