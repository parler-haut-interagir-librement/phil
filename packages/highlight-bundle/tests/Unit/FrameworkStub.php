<?php

declare(strict_types=1);

namespace Phil\HighlightBundle\Tests\Unit;

use Override;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * An extension under the alias of FrameworkBundle's, so the bundle sees the framework without booting it.
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
