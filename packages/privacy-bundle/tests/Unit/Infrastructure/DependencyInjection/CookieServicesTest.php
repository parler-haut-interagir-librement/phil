<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\DependencyInjection;

use Override;
use Phil\PrivacyBundle\Domain\Cookie\CookieInventory;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\CookieServices;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Yaml\Exception\ParseException;

#[CoversClass(CookieServices::class)]
final class CookieServicesTest extends TestCase
{
    private string $file;

    #[Override]
    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir().'/phil-privacy-cookies-'.bin2hex(random_bytes(8)).'.yaml';
    }

    #[Override]
    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testEveryCategoryOfTheFileIsRead(): void
    {
        file_put_contents($this->file, "analytics:\n    _a: { purpose: Counts visits, duration: P13M }\nmarketing:\n    _m: { purpose: Shows ads, duration: P1M }\n");

        $container = $this->load(['consent' => ['categories' => ['analytics' => [], 'marketing' => []]], 'cookies' => ['inventory' => $this->file]]);

        $data = $container->getDefinition(CookieInventory::class)->getArgument(0);
        self::assertIsArray($data);
        self::assertSame(['analytics', 'marketing'], array_keys($data));
    }

    public function testACategoryNamedByANumberStopsTheBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_privacy.consent: "7" is not a consent category');

        $this->load(['consent' => ['categories' => ['7' => []]]]);
    }

    public function testAnInventoryThatDeclaresTheConsentCookieStopsTheBuild(): void
    {
        file_put_contents($this->file, "analytics:\n    phil_privacy_consent: { purpose: Keeps the choice, duration: P6M }\n");

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_privacy.cookies.inventory: The cookie "phil_privacy_consent" is declared twice.');

        $this->load(['consent' => ['categories' => ['analytics' => []]], 'cookies' => ['inventory' => $this->file]]);
    }

    public function testBrokenYamlStopsTheBuildWithTheParsersMessage(): void
    {
        file_put_contents($this->file, "analytics: [\n");

        try {
            $this->load(['cookies' => ['inventory' => $this->file]]);
            self::fail('Broken YAML must stop the build.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            $previous = $invalidConfigurationException->getPrevious();
            self::assertInstanceOf(ParseException::class, $previous);
            self::assertNotSame('', $previous->getMessage());
            self::assertSame('phil_privacy.cookies.inventory: '.$previous->getMessage(), $invalidConfigurationException->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $bundle = new PhilPrivacyBundle('php://memory');
        $container = new ContainerBuilder();
        $instanceof = [];
        $extension = $bundle->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], $container);
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);
        $bundle->loadExtension(new Processor()->processConfiguration($configuration, [$config]), new ContainerConfigurator($container, new PhpFileLoader($container, new FileLocator()), $instanceof, __FILE__, __FILE__), $container);

        return $container;
    }
}
