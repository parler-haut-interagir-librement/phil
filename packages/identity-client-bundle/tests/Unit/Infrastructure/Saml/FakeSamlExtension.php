<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Unit\Infrastructure\Saml;

use Override;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

final class FakeSamlExtension implements ExtensionInterface
{
    #[Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
    }

    #[Override]
    public function getAlias(): string
    {
        return 'phil_saml_sp';
    }
}
