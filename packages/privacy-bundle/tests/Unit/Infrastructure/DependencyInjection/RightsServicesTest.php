<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\DependencyInjection;

use Phil\PrivacyBundle\Application\Export\PlainExportReadme;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\RightsServices;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

#[CoversClass(RightsServices::class)]
final class RightsServicesTest extends TestCase
{
    public function testThePlainReadmeIsDeclaredForAnApplicationWithoutTwig(): void
    {
        $bundle = new PhilPrivacyBundle('php://memory');
        $container = new ContainerBuilder();
        $instanceof = [];
        $extension = $bundle->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], $container);
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);
        $bundle->loadExtension(new Processor()->processConfiguration($configuration, [[]]), new ContainerConfigurator($container, new PhpFileLoader($container, new FileLocator()), $instanceof, __FILE__, __FILE__), $container);

        self::assertTrue($container->hasDefinition(PlainExportReadme::class));
        self::assertSame([], $container->getDefinition(PlainExportReadme::class)->getArguments());
    }
}
