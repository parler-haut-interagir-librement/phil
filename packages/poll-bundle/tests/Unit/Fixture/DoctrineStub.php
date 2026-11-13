<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * An extension answering to the alias of DoctrineBundle's, so that the bundle sees Doctrine installed.
 */
final class DoctrineStub extends Extension
{
    #[Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
    }

    #[Override]
    public function getAlias(): string
    {
        return 'doctrine';
    }
}
