<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\DependencyInjection;

use LogicException;
use Phil\PrivacyBundle\Application\Contract\EmailTrackingStore;
use Phil\PrivacyBundle\Application\EmailTracking\EmailTrackingConsents;
use Phil\PrivacyBundle\Infrastructure\Database\DbalEmailTrackingConsents;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\PrivacyServicesPass;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\RightsConfiguration;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\RightsServices;
use Phil\PrivacyBundle\Infrastructure\Http\PreferencesController;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(RightsConfiguration::class)]
#[CoversClass(RightsServices::class)]
#[CoversClass(PrivacyServicesPass::class)]
final class EmailTrackingWiringTest extends TestCase
{
    public function testOffByDefaultNothingIsAskedNorKept(): void
    {
        $container = $this->load([]);

        self::assertFalse($container->hasDefinition(EmailTrackingConsents::class));
        self::assertFalse($container->hasAlias(EmailTrackingStore::class));

        $controller = $container->getDefinition(PreferencesController::class)->getArgument(6);
        self::assertInstanceOf(Reference::class, $controller);
        self::assertSame(EmailTrackingConsents::class, (string) $controller);
    }

    public function testTurnedOnTheConsentsArePartOfTheExportAndTheErasure(): void
    {
        $container = $this->load(['email_tracking' => ['enabled' => true, 'version' => '2026-11']]);
        $consents = $container->getDefinition(EmailTrackingConsents::class);

        self::assertTrue($consents->isPublic());
        self::assertTrue($consents->hasTag(PhilPrivacyBundle::EXPORTER_TAG));
        self::assertTrue($consents->hasTag(PhilPrivacyBundle::ERASER_TAG));
        self::assertEquals(new Reference(EmailTrackingStore::class), $consents->getArgument(0));
        self::assertEquals(new Reference('clock'), $consents->getArgument(1));
        self::assertSame('2026-11', $consents->getArgument(2));
        self::assertSame(DbalEmailTrackingConsents::class, (string) $container->getAlias(EmailTrackingStore::class));
        self::assertEquals(new Reference('database_connection'), $container->getDefinition(DbalEmailTrackingConsents::class)->getArgument(0));
    }

    public function testAStoreOfTheApplicationCanBeNamed(): void
    {
        $container = $this->load(['email_tracking' => ['enabled' => true, 'store' => 'app.email_tracking']]);

        self::assertSame('app.email_tracking', (string) $container->getAlias(EmailTrackingStore::class));
        self::assertSame('1', $container->getDefinition(EmailTrackingConsents::class)->getArgument(2));
    }

    public function testABadVersionStopsTheBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_privacy.email_tracking.version: letters, digits, ".", "_" and "-", 32 at most, starting with a letter or a digit.');

        $this->load(['email_tracking' => ['enabled' => true, 'version' => 'version two']]);
    }

    public function testAnEmptyVersionStopsTheBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['email_tracking' => ['enabled' => true, 'version' => '']]);
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function missingParts(): iterable
    {
        yield 'database' => [['clock'], 'needs an EmailTrackingStore: Doctrine DBAL for the table of the bundle, or email_tracking.store'];
        yield 'clock' => [['database_connection'], 'needs the clock component (symfony/clock)'];
    }

    /**
     * @param list<string> $present
     */
    #[DataProvider('missingParts')]
    public function testTurnedOnItNeedsAStoreAndAClock(array $present, string $message): void
    {
        $container = $this->load(['email_tracking' => ['enabled' => true]]);

        foreach ($present as $id) {
            $container->register($id, stdClass::class);
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('phil_privacy.email_tracking is enabled and '.$message.'.');

        new PrivacyServicesPass()->process($container);
    }

    public function testWithAStoreAndAClockTheConsentsStay(): void
    {
        $container = $this->load(['email_tracking' => ['enabled' => true]]);
        $container->register('database_connection', stdClass::class);
        $container->register('clock', stdClass::class);

        new PrivacyServicesPass()->process($container);

        self::assertTrue($container->hasDefinition(EmailTrackingConsents::class));
        self::assertTrue($container->hasDefinition(DbalEmailTrackingConsents::class));
    }

    public function testWithoutADatabaseTheTableStoreGoesAway(): void
    {
        $container = $this->load([]);

        new PrivacyServicesPass()->process($container);

        self::assertFalse($container->hasDefinition(DbalEmailTrackingConsents::class));
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
