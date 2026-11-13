<?php

declare(strict_types=1);

namespace Phil\PagedMediaWeasyprint\Tests\Unit;

use Phil\PagedMediaBundle\Application\Contract\PrintEngine;
use Phil\PagedMediaWeasyprint\LocalAssets;
use Phil\PagedMediaWeasyprint\WeasyPrintEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * The engine activated as the installation guide says: one line of `services.yaml`, read and compiled by Symfony.
 */
#[CoversClass(WeasyPrintEngine::class)]
#[CoversClass(LocalAssets::class)]
final class ActivationTest extends TestCase
{
    public function testOneLineOfServicesYamlRegistersTheEngineUnderThePort(): void
    {
        $engine = $this->compile('services.yaml')->get(PrintEngine::class);

        self::assertInstanceOf(WeasyPrintEngine::class, $engine);
        self::assertNull(new ReflectionProperty(WeasyPrintEngine::class, 'assets')->getValue($engine));
        self::assertNull(new ReflectionProperty(WeasyPrintEngine::class, 'pdf')->getValue($engine));
        self::assertSame('weasyprint', new ReflectionProperty(WeasyPrintEngine::class, 'binary')->getValue($engine));
    }

    public function testALocalAssetsServiceIsGivenToTheEngineByAutowiring(): void
    {
        $engine = $this->compile('services-with-assets.yaml', ['assets_directory' => __DIR__])->get(PrintEngine::class);

        self::assertInstanceOf(WeasyPrintEngine::class, $engine);
        self::assertInstanceOf(LocalAssets::class, new ReflectionProperty(WeasyPrintEngine::class, 'assets')->getValue($engine));
    }

    /**
     * @param array<string, string> $parameters
     */
    private function compile(string $file, array $parameters = []): ContainerBuilder
    {
        $container = new ContainerBuilder();

        foreach ($parameters as $name => $value) {
            $container->setParameter($name, $value);
        }

        new YamlFileLoader($container, new FileLocator(__DIR__.'/Fixture'))->load($file);
        $container->compile(true);

        return $container;
    }
}
