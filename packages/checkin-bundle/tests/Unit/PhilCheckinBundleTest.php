<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Tests\Unit;

use DateTimeZone;
use Phil\Checkin\AdmissionPolicy;
use Phil\Checkin\Contract\CheckInLedger;
use Phil\Checkin\Contract\CredentialCatalog;
use Phil\Checkin\Contract\CredentialResolver;
use Phil\Checkin\CredentialResolvers;
use Phil\Checkin\InMemoryCheckInLedger;
use Phil\Checkin\Offline\OfflineSync;
use Phil\CheckinBundle\Application\Contract\AdmissionRules;
use Phil\CheckinBundle\Application\Contract\CredentialAttributes;
use Phil\CheckinBundle\Infrastructure\Catalog\CredentialCatalogs;
use Phil\CheckinBundle\Infrastructure\DependencyInjection\CheckinServices;
use Phil\CheckinBundle\Infrastructure\DependencyInjection\CredentialCatalogPass;
use Phil\CheckinBundle\Infrastructure\DependencyInjection\CsrfProtectionPass;
use Phil\CheckinBundle\Infrastructure\Doctrine\CheckInSchemaListener;
use Phil\CheckinBundle\Infrastructure\Doctrine\DoctrineCheckInLedger;
use Phil\CheckinBundle\Infrastructure\Http\CheckinController;
use Phil\CheckinBundle\Infrastructure\Http\CheckinRequest;
use Phil\CheckinBundle\Infrastructure\Http\DeskConfig;
use Phil\CheckinBundle\Infrastructure\Rules\CheckpointDesks;
use Phil\CheckinBundle\Infrastructure\Rules\CheckpointRules;
use Phil\CheckinBundle\Infrastructure\Rules\ConfiguredAdmissionRules;
use Phil\CheckinBundle\Infrastructure\Rules\NoCredentialAttributes;
use Phil\CheckinBundle\PhilCheckinBundle;
use Phil\CheckinBundle\Tests\Unit\Fixture\FixedCredentials;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\AssetMapper\AssetMapperBundle;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilCheckinBundle::class)]
#[CoversClass(CheckinServices::class)]
#[CoversClass(CsrfProtectionPass::class)]
#[CoversClass(CredentialCatalogPass::class)]
final class PhilCheckinBundleTest extends TestCase
{
    public function testTheDefaultsKeepTheCheckInsInTheDatabaseAndCheckInOnlineOnly(): void
    {
        $container = $this->load([]);
        self::assertEquals([new TaggedIteratorArgument('phil_checkin.credential_catalog')], $container->getDefinition(CredentialCatalogs::class)->getArguments());
        self::assertEquals(new Reference(CredentialCatalogs::class), $container->getDefinition(CheckpointDesks::class)->getArgument(5));
        self::assertEquals(new Reference(CredentialCatalogs::class), $container->getDefinition(OfflineSync::class)->getArgument(0));

        // No catalog named and none tagged: the pass turns offline off.
        new CredentialCatalogPass()->process($container);

        self::assertSame('phil_checkin', $this->extension()->getAlias());
        self::assertSame(
            ['service_container', DoctrineCheckInLedger::class, CheckInSchemaListener::class, CredentialResolvers::class, AdmissionPolicy::class, ConfiguredAdmissionRules::class, NoCredentialAttributes::class, CheckpointRules::class, CheckpointDesks::class, CheckinRequest::class, DeskConfig::class, CheckinController::class],
            array_keys($container->getDefinitions()),
        );

        $connection = new Reference('doctrine.dbal.default_connection');
        self::assertEquals([$connection], $container->getDefinition(DoctrineCheckInLedger::class)->getArguments());
        self::assertEquals([$connection, new Reference(DoctrineCheckInLedger::class)], $container->getDefinition(CheckInSchemaListener::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'default']]], $container->getDefinition(CheckInSchemaListener::class)->getTags());
        self::assertSame(DoctrineCheckInLedger::class, (string) $container->getAlias(CheckInLedger::class));

        self::assertEquals([new TaggedIteratorArgument('phil_checkin.credential_resolver')], $container->getDefinition(CredentialResolvers::class)->getArguments());
        self::assertSame([0, true], $container->getDefinition(AdmissionPolicy::class)->getArguments());
        self::assertEquals([new Reference(CredentialResolvers::class), new Reference(DoctrineCheckInLedger::class), new Reference('clock'), new Reference(AdmissionPolicy::class), new Reference(CheckpointRules::class), null, 43_200, 10_000, false, 64], $container->getDefinition(CheckpointDesks::class)->getArguments());
        self::assertSame([[]], $container->getDefinition(ConfiguredAdmissionRules::class)->getArguments());
        self::assertSame(ConfiguredAdmissionRules::class, (string) $container->getAlias(AdmissionRules::class));
        self::assertSame(NoCredentialAttributes::class, (string) $container->getAlias(CredentialAttributes::class));
        self::assertEquals([new Reference(AdmissionRules::class), new Reference('clock'), new Reference(CredentialAttributes::class), null], $container->getDefinition(CheckpointRules::class)->getArguments());
        self::assertFalse($container->hasDefinition(OfflineSync::class));
        self::assertEquals([new Reference('security.csrf.token_manager')], $container->getDefinition(CheckinRequest::class)->getArguments());
        self::assertEquals([new Reference(CheckinRequest::class), new Reference('router', ContainerInterface::NULL_ON_INVALID_REFERENCE), false], $container->getDefinition(DeskConfig::class)->getArguments());

        $controller = $container->getDefinition(CheckinController::class);
        self::assertEquals([
            new Reference(CheckpointDesks::class),
            new Reference(CheckinRequest::class),
            new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            null,
            500,
            false,
            new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ], $controller->getArguments());
        self::assertTrue($controller->isPublic());
        self::assertSame(['controller.service_arguments' => [[]]], $controller->getTags());

        self::assertSame(['phil_checkin.credential_resolver' => [[]]], $container->getAutoconfiguredInstanceof()[CredentialResolver::class]->getTags());
        self::assertSame(['phil_checkin.credential_catalog' => [[]]], $container->getAutoconfiguredInstanceof()[CredentialCatalog::class]->getTags());
        self::assertSame('phil_checkin.credential_resolver', PhilCheckinBundle::RESOLVER_TAG);
        self::assertSame('phil_checkin.credential_catalog', PhilCheckinBundle::CATALOG_TAG);
        self::assertSame('PHIL_CHECKIN_SCAN', PhilCheckinBundle::SCAN);
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load([
            'enabled' => true,
            'clock' => 'app.clock',
            'connection' => 'checkin',
            'catalog' => FixedCredentials::class,
            'policy' => ['early_seconds' => 1800, 'anti_passback' => false],
            'offline' => ['manifest_seconds' => 3600, 'max_credentials' => 50, 'labels' => true, 'min_code_bits' => 96, 'batch_limit' => 100, 'clock_skew_seconds' => 30, 'max_age_seconds' => 7200],
        ]);
        $ledger = new Reference(DoctrineCheckInLedger::class);
        $clock = new Reference('app.clock');
        $policy = new Reference(AdmissionPolicy::class);
        $catalog = new Reference(FixedCredentials::class);

        self::assertEquals([new Reference('doctrine.dbal.checkin_connection')], $container->getDefinition(DoctrineCheckInLedger::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'checkin']]], $container->getDefinition(CheckInSchemaListener::class)->getTags());
        self::assertSame([1800, false], $container->getDefinition(AdmissionPolicy::class)->getArguments());
        self::assertEquals([new Reference(CredentialResolvers::class), $ledger, $clock, $policy, new Reference(CheckpointRules::class), $catalog, 3600, 50, true, 96], $container->getDefinition(CheckpointDesks::class)->getArguments());
        self::assertEquals([$catalog, $ledger, $clock, $policy, 30, 7200], $container->getDefinition(OfflineSync::class)->getArguments());
        self::assertTrue($container->getDefinition(DeskConfig::class)->getArgument(2));

        $arguments = $container->getDefinition(CheckinController::class)->getArguments();
        self::assertEquals([new Reference(OfflineSync::class), 100, true], \array_slice($arguments, 3, 3));

        // The catalog named replaces the tagged ones, and the pass leaves it alone.
        self::assertFalse($container->hasDefinition(CredentialCatalogs::class));
        new CredentialCatalogPass()->process($container);
        self::assertTrue($container->hasDefinition(OfflineSync::class));
    }

    public function testTheTaggedCatalogsAreAskedTogetherWhenNoneIsNamed(): void
    {
        $container = $this->load([]);
        $container->register('app.tickets')->addTag('phil_checkin.credential_catalog');

        new CredentialCatalogPass()->process($container);

        self::assertTrue($container->hasDefinition(CredentialCatalogs::class));
        self::assertTrue($container->hasDefinition(OfflineSync::class));
        self::assertEquals(new Reference(CredentialCatalogs::class), $container->getDefinition(CheckpointDesks::class)->getArgument(5));
        self::assertTrue($container->getDefinition(DeskConfig::class)->getArgument(2));
        self::assertEquals(new Reference(OfflineSync::class), $container->getDefinition(CheckinController::class)->getArgument(3));

        $passes = array_filter($this->passes(), static fn (object $pass): bool => $pass instanceof CredentialCatalogPass);
        self::assertCount(1, $passes);
    }

    public function testTheLedgerCanBeKeptInMemoryOrByTheApplication(): void
    {
        $memory = $this->load(['store' => 'memory']);
        self::assertSame(InMemoryCheckInLedger::class, (string) $memory->getAlias(CheckInLedger::class));
        self::assertSame(InMemoryCheckInLedger::class, $memory->getDefinition(InMemoryCheckInLedger::class)->getClass());
        self::assertFalse($memory->hasDefinition(DoctrineCheckInLedger::class));

        $own = $this->load(['store' => 'app.ledger']);
        self::assertSame('app.ledger', (string) $own->getAlias(CheckInLedger::class));
        self::assertFalse($own->hasDefinition(DoctrineCheckInLedger::class));
        self::assertFalse($own->hasDefinition(InMemoryCheckInLedger::class));
        self::assertEquals(new Reference('app.ledger'), $own->getDefinition(CheckpointDesks::class)->getArgument(1));
    }

    public function testABadConfigurationIsRefused(): void
    {
        foreach ([
            ['store' => ''],
            ['connection' => ''],
            ['clock' => ''],
            ['policy' => ['early_seconds' => -1]],
            ['policy' => ['early_seconds' => 86_401]],
            ['offline' => ['manifest_seconds' => 59]],
            ['offline' => ['manifest_seconds' => 604_801]],
            ['offline' => ['max_credentials' => 0]],
            ['offline' => ['max_credentials' => 100_001]],
            ['offline' => ['min_code_bits' => -1]],
            ['offline' => ['min_code_bits' => 129]],
            ['offline' => ['batch_limit' => 0]],
            ['offline' => ['batch_limit' => 501]],
            ['offline' => ['clock_skew_seconds' => -1]],
            ['offline' => ['clock_skew_seconds' => 3601]],
            ['offline' => ['max_age_seconds' => 59]],
            ['offline' => ['max_age_seconds' => 2_592_001]],
            ['rules' => ['checkpoints' => ['hall' => '']]],
            ['rules' => ['checkpoints' => ['hall' => str_repeat('1', 1_001)]]],
            ['rules' => ['checkpoints' => ['hall' => ['a list']]]],
            ['rules' => ['timezone' => 'Mars/Olympus']],
        ] as $config) {
            try {
                $this->load($config);
                self::fail((string) json_encode($config));
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString('phil_checkin', $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testTheBoundsOfTheConfigurationAreKept(): void
    {
        $container = $this->load([
            'catalog' => 'app.catalog',
            'policy' => ['early_seconds' => 86_400],
            'offline' => ['manifest_seconds' => 604_800, 'max_credentials' => 100_000, 'min_code_bits' => 128, 'batch_limit' => 1, 'clock_skew_seconds' => 3600, 'max_age_seconds' => 2_592_000],
        ]);

        self::assertSame(86_400, $container->getDefinition(AdmissionPolicy::class)->getArgument(0));
        self::assertSame(604_800, $container->getDefinition(CheckpointDesks::class)->getArgument(6));
        self::assertSame(100_000, $container->getDefinition(CheckpointDesks::class)->getArgument(7));
        self::assertFalse($container->getDefinition(CheckpointDesks::class)->getArgument(8));
        self::assertSame(128, $container->getDefinition(CheckpointDesks::class)->getArgument(9));
        self::assertSame(3600, $container->getDefinition(OfflineSync::class)->getArgument(4));
        self::assertSame(2_592_000, $container->getDefinition(OfflineSync::class)->getArgument(5));
        self::assertSame(1, $container->getDefinition(CheckinController::class)->getArgument(4));

        $lowest = $this->load(['catalog' => 'app.catalog', 'offline' => ['manifest_seconds' => 60, 'max_credentials' => 1, 'min_code_bits' => 0, 'clock_skew_seconds' => 0, 'max_age_seconds' => 60]]);
        self::assertSame(60, $lowest->getDefinition(CheckpointDesks::class)->getArgument(6));
        self::assertSame(1, $lowest->getDefinition(CheckpointDesks::class)->getArgument(7));
        self::assertSame(0, $lowest->getDefinition(CheckpointDesks::class)->getArgument(9));
        self::assertSame(0, $lowest->getDefinition(OfflineSync::class)->getArgument(4));
        self::assertSame(60, $lowest->getDefinition(OfflineSync::class)->getArgument(5));

        self::assertSame(0, $this->load(['policy' => ['early_seconds' => 0]])->getDefinition(AdmissionPolicy::class)->getArgument(0));

        $defaults = $this->load(['catalog' => 'app.catalog']);
        self::assertSame([43_200, 10_000, false, 64], \array_slice($defaults->getDefinition(CheckpointDesks::class)->getArguments(), 6));
        self::assertSame([300, 172_800], \array_slice($defaults->getDefinition(OfflineSync::class)->getArguments(), 4));
    }

    public function testTheRulesOfTheConfigurationAreCheckedAndReachTheirEvaluator(): void
    {
        $container = $this->load(['rules' => [
            'checkpoints' => ['bar' => "attributes['age'] >= 18", 'Main.Door' => "now.format('H') < '22'"],
            'timezone' => 'America/Toronto',
            'attributes' => 'app.attributes',
        ]]);

        self::assertSame([['bar' => "attributes['age'] >= 18", 'Main.Door' => "now.format('H') < '22'"]], $container->getDefinition(ConfiguredAdmissionRules::class)->getArguments());
        self::assertSame('app.attributes', (string) $container->getAlias(CredentialAttributes::class));
        self::assertFalse($container->hasDefinition(NoCredentialAttributes::class));
        $timezone = $container->getDefinition(CheckpointRules::class)->getArgument(3);
        self::assertInstanceOf(Definition::class, $timezone);
        self::assertSame([DateTimeZone::class, ['America/Toronto']], [$timezone->getClass(), $timezone->getArguments()]);

        $provided = $this->load(['rules' => ['provider' => 'app.rules']]);
        self::assertSame('app.rules', (string) $provided->getAlias(AdmissionRules::class));
    }

    public function testTheNamesOfTheCheckpointsAndARuleOfTheLongestSizeAreKept(): void
    {
        $longest = 'true'.str_repeat(' ', CheckpointRules::MAX_LENGTH - 4);
        $container = $this->load(['rules' => ['checkpoints' => ['main-door' => $longest]]]);

        self::assertSame([['main-door' => $longest]], $container->getDefinition(ConfiguredAdmissionRules::class)->getArguments());
    }

    public function testARuleThatDoesNotParseStopsTheContainer(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The admission rule of the checkpoint "bar" is not valid: Variable "age" is not valid');

        $this->load(['rules' => ['checkpoints' => ['bar' => 'age >= 18']]]);
    }

    public function testTheTokenAndTheAssetsArePrepended(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container = new ContainerBuilder();
        $container->registerExtension(new FrameworkExtension());
        $container->registerExtension(new AssetMapperBundle()->getContainerExtension() ?? throw new \LogicException('AssetMapper has an extension.'));
        $container->registerExtension($extension);

        $extension->prepend($container);

        self::assertSame([['csrf_protection' => ['stateless_token_ids' => ['phil_checkin']]]], $container->getExtensionConfig('framework'));
        self::assertSame([['paths' => [
            PhilCheckinBundle::CONTROLLERS_DIR => '@phil/checkin-bundle',
            PhilCheckinBundle::STYLES_DIR => 'phil-checkin',
        ]]], $container->getExtensionConfig('asset_mapper'));
        self::assertFileExists(PhilCheckinBundle::CONTROLLERS_DIR.'/checkin_desk_controller.js');
        self::assertFileExists(PhilCheckinBundle::STYLES_DIR.'/checkin-desk.css');

        $framework = new ContainerBuilder();
        $framework->registerExtension(new FrameworkExtension());
        $framework->registerExtension($extension);

        $extension->prepend($framework);
        self::assertSame([['csrf_protection' => ['stateless_token_ids' => ['phil_checkin']]]], $framework->getExtensionConfig('framework'));
        self::assertSame([], $framework->getExtensionConfig('asset_mapper'));

        $bare = new ContainerBuilder();
        $bare->registerExtension($extension);

        $extension->prepend($bare);
        self::assertSame([], $bare->getExtensionConfig('framework'));
    }

    public function testTheContainerStopsWhenTheCsrfProtectionIsOff(): void
    {
        $passes = array_filter($this->passes(), static fn (object $pass): bool => $pass instanceof CsrfProtectionPass);
        self::assertCount(1, $passes);

        $pass = new CsrfProtectionPass();
        $pass->process(new ContainerBuilder());

        $protected = new ContainerBuilder();
        $protected->register(CheckinRequest::class);
        $protected->register('security.csrf.token_manager');

        $pass->process($protected);

        $unprotected = new ContainerBuilder();
        $unprotected->register(CheckinRequest::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('"framework.csrf_protection" is false');

        $pass->process($unprotected);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load([$config], $container);

        return $container;
    }

    /**
     * @return list<object>
     */
    private function passes(): array
    {
        $container = new ContainerBuilder();
        new PhilCheckinBundle()->build($container);

        return array_values($container->getCompilerPassConfig()->getPasses());
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilCheckinBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
