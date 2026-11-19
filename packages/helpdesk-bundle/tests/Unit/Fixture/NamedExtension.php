<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * An extension of the given name that loads nothing: the bundles the help desk configures, present.
 */
final class NamedExtension extends Extension
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
