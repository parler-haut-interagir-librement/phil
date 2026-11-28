<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit;

use LogicException;
use Phil\MonitoringBundle\Application\Contract\MonitoringStatus;
use Phil\SitesConsoleBundle\Application\Agent\AgentEndpoints;
use Phil\SitesConsoleBundle\Application\Agent\ConsoleKeyRing;
use Phil\SitesConsoleBundle\Application\Agent\RequestVerifier;
use Phil\SitesConsoleBundle\Application\Console\ConsoleSigning;
use Phil\SitesConsoleBundle\Application\Console\Puller;
use Phil\SitesConsoleBundle\Application\Contract\SiteHealth;
use Phil\SitesConsoleBundle\Domain\Alert\AlertRules;
use Phil\SitesConsoleBundle\Domain\Contract\SiteRepository;
use Phil\SitesConsoleBundle\Infrastructure\Cache\CacheConsoleKeyStore;
use Phil\SitesConsoleBundle\Infrastructure\Composer\LockFilePackages;
use Phil\SitesConsoleBundle\Infrastructure\Console\KeysCommand;
use Phil\SitesConsoleBundle\Infrastructure\Console\KeysRotateCommand;
use Phil\SitesConsoleBundle\Infrastructure\Console\PurgeCommand;
use Phil\SitesConsoleBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\SitesConsoleBundle\Infrastructure\DependencyInjection\MonitoringPass;
use Phil\SitesConsoleBundle\Infrastructure\DependencyInjection\SitesConsoleServices;
use Phil\SitesConsoleBundle\Infrastructure\Doctrine\DoctrineSiteRepository;
use Phil\SitesConsoleBundle\Infrastructure\Http\AgentController;
use Phil\SitesConsoleBundle\Infrastructure\Http\ConsoleController;
use Phil\SitesConsoleBundle\Infrastructure\Http\HttpAgentClient;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemorySiteRepository;
use Phil\SitesConsoleBundle\Infrastructure\Messenger\PullSiteHandler;
use Phil\SitesConsoleBundle\Infrastructure\Monitoring\MonitoringSiteHealth;
use Phil\SitesConsoleBundle\Infrastructure\Monitoring\UnknownSiteHealth;
use Phil\SitesConsoleBundle\Infrastructure\Twig\KitComponents;
use Phil\SitesConsoleBundle\PhilSitesConsoleBundle;
use Phil\SitesConsoleBundle\Tests\Unit\Support\ExpectedWiring;
use Phil\SitesConsoleBundle\Tests\Unit\Support\Keys;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilSitesConsoleBundle::class)]
#[CoversClass(SitesConsoleServices::class)]
#[CoversClass(MonitoringPass::class)]
#[CoversClass(KitSubdirectoryPass::class)]
final class PhilSitesConsoleBundleTest extends TestCase
{
    public function testByDefaultOnlyTheKeysCommand(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_sites_console', $this->extension()->getAlias());
        self::assertTrue($container->hasDefinition(KeysCommand::class));
        self::assertTrue($container->getDefinition(KeysCommand::class)->isAutoconfigured());
        self::assertFalse($container->hasDefinition(ConsoleController::class));
        self::assertFalse($container->hasDefinition(AgentController::class));
        self::assertFalse($container->hasParameter(SitesConsoleServices::HEALTH_PARAMETER));
    }

    public function testTheConsoleOnDoctrine(): void
    {
        $container = $this->load([['console' => ['private_key' => Keys::CONSOLE_SEED, 'entity_manager' => 'sites', 'roles' => ['viewer' => 'ROLE_V']]]]);

        self::assertSame(DoctrineSiteRepository::class, (string) $container->getAlias(SiteRepository::class));
        self::assertTrue($container->getAlias(SiteRepository::class)->isPublic());
        self::assertEquals([new Reference('doctrine.orm.sites_entity_manager')], $container->getDefinition(DoctrineSiteRepository::class)->getArguments());
        self::assertSame([Keys::CONSOLE_SEED], $container->getDefinition('phil_sites_console.console_key')->getArguments());
        self::assertFalse($container->hasDefinition('phil_sites_console.console_next_key'));
        self::assertNull($container->getDefinition(ConsoleSigning::class)->getArgument(1));
        self::assertTrue($container->getDefinition(KeysRotateCommand::class)->isAutoconfigured());
        $controller = $container->getDefinition(ConsoleController::class);
        self::assertTrue($controller->isPublic());
        self::assertSame('ROLE_V', $controller->getArgument(9));
        self::assertSame('ROLE_SITES_CONSOLE_OPERATOR', $controller->getArgument(10));
        self::assertSame('@PhilSitesConsole/layout.html.twig', $controller->getArgument(11));
        self::assertTrue($controller->hasTag('controller.service_arguments'));
        self::assertTrue($container->getDefinition(Puller::class)->isPublic());
        self::assertSame(['phil/*', 'symfony/http-kernel'], $container->getDefinition(AlertRules::class)->getArgument(0));
        self::assertTrue($container->getDefinition(PullSiteHandler::class)->hasTag('messenger.message_handler'));
        self::assertSame(90, $container->getDefinition(PurgeCommand::class)->getArgument(2));
        self::assertNull($container->getDefinition(KitComponents::class)->getArgument(0));
    }

    public function testTheConsoleInMemory(): void
    {
        $container = $this->load([['console' => ['private_key' => Keys::CONSOLE_SEED, 'store' => 'memory', 'expected_versions' => ['phil/url' => '0.1.5'], 'timeout' => 5.5, 'update_timeout' => 60.5]]]);

        self::assertSame(InMemorySiteRepository::class, (string) $container->getAlias(SiteRepository::class));
        self::assertSame([], $container->getDefinition(InMemorySiteRepository::class)->getArguments());
        self::assertSame(['phil/url' => '0.1.5'], $container->getDefinition(AlertRules::class)->getArgument(1));
        $client = $container->getDefinition(HttpAgentClient::class);
        self::assertSame(5.5, $client->getArgument(3));
        self::assertSame(60.5, $client->getArgument(4));
    }

    public function testTheAgent(): void
    {
        $container = $this->load([['agent' => ['site_id' => 'shop', 'private_key' => Keys::SITE_SEED, 'console_public_key' => 'PUBLIC', 'configuration_keys' => ['mail.*'], 'configuration_applier' => 'app.applier', 'packages' => ['phil/*']]]]);

        self::assertSame('auto', $container->getParameter(SitesConsoleServices::HEALTH_PARAMETER));
        $endpoints = $container->getDefinition(AgentEndpoints::class);
        self::assertSame('shop', $endpoints->getArgument(0));
        self::assertSame(['phil/*'], $endpoints->getArgument(5));
        self::assertSame(['mail.*'], $endpoints->getArgument(6));
        self::assertEquals(new Reference('app.applier'), $endpoints->getArgument(7));
        self::assertNull($endpoints->getArgument(8));
        self::assertSame(['%kernel.project_dir%/composer.lock', false], $container->getDefinition(LockFilePackages::class)->getArguments());
        self::assertSame(300, $container->getDefinition(RequestVerifier::class)->getArgument(4));
        self::assertFalse($container->hasDefinition('phil_sites_console.console_next_public_key'));
        self::assertNull($container->getDefinition(ConsoleKeyRing::class)->getArgument(1));
        self::assertEquals([new Reference('cache.app')], $container->getDefinition(CacheConsoleKeyStore::class)->getArguments());
        self::assertFalse($container->hasDefinition(KeysRotateCommand::class), 'The rotation is a command of the console.');
        self::assertTrue($container->getDefinition(AgentController::class)->isPublic());
        self::assertSame([SitesConsoleServices::IDEMPOTENCY_RETENTION, SitesConsoleServices::IDEMPOTENCY_RESERVATION], \array_slice($container->getDefinition('phil_sites_console.idempotency')->getArguments(), 2));
    }

    public function testAnAgentWithARunner(): void
    {
        $endpoints = $this->load([['agent' => ['site_id' => 'shop', 'private_key' => 'a', 'console_public_key' => 'b', 'update_runner' => 'app.runner']]])->getDefinition(AgentEndpoints::class);

        self::assertNull($endpoints->getArgument(7));
        self::assertEquals(new Reference('app.runner'), $endpoints->getArgument(8));
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalid')]
    public function testAnInvalidConfiguration(array $config, string $message): void
    {
        try {
            $this->load([$config]);
            self::fail('The configuration is refused.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertStringContainsString($message, $invalidConfigurationException->getMessage());
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalid(): iterable
    {
        yield 'console without key' => [['console' => ['enabled' => true]], 'The console signs its requests: set console.private_key.'];
        yield 'agent without site' => [['agent' => ['private_key' => 'a', 'console_public_key' => 'b']], 'set agent.site_id, agent.private_key and agent.console_public_key'];
        yield 'agent without key' => [['agent' => ['site_id' => 'shop', 'console_public_key' => 'b']], 'set agent.site_id'];
        yield 'agent without console key' => [['agent' => ['site_id' => 'shop', 'private_key' => 'a']], 'set agent.site_id'];
        yield 'timeout under its lowest' => [['console' => ['private_key' => 'a', 'timeout' => 0.5]], 'timeout'];
        yield 'timeout over its highest' => [['console' => ['private_key' => 'a', 'timeout' => 121]], 'timeout'];
        yield 'update timeout under its lowest' => [['console' => ['private_key' => 'a', 'update_timeout' => 0.5]], 'update_timeout'];
        yield 'update timeout over its highest' => [['console' => ['private_key' => 'a', 'update_timeout' => 3601]], 'update_timeout'];
        yield 'no day of retention' => [['console' => ['private_key' => 'a', 'snapshot_retention_days' => 0]], 'snapshot_retention_days'];
        yield 'skew under its lowest' => [['agent' => ['site_id' => 'shop', 'private_key' => 'a', 'console_public_key' => 'b', 'max_clock_skew' => 0]], 'max_clock_skew'];
        yield 'skew over its highest' => [['agent' => ['site_id' => 'shop', 'private_key' => 'a', 'console_public_key' => 'b', 'max_clock_skew' => 3601]], 'max_clock_skew'];
        yield 'bad site id' => [['agent' => ['site_id' => 'Shop']], 'The identifier of a site is 1 to 64'];
        yield 'bad expected version' => [['console' => ['private_key' => 'a', 'expected_versions' => ['phil/url' => '1 0']]], 'An expected version is a version'];
        yield 'bad expected package' => [['console' => ['private_key' => 'a', 'expected_versions' => ['Phil' => '1.0']]], 'The expected versions are keyed by package name'];
    }

    public function testTheWholeWiringOfTheConsoleAndTheAgent(): void
    {
        $container = $this->load([[
            'console' => ['private_key' => Keys::CONSOLE_SEED, 'next_private_key' => Keys::NEXT_SEED, 'entity_manager' => 'sites', 'http_client' => 'app.http', 'allow_insecure_urls' => true, 'snapshot_retention_days' => 30, 'kit_subdirectory' => 'kit'],
            'agent' => ['site_id' => 'shop', 'private_key' => Keys::SITE_SEED, 'console_public_key' => 'PUBLIC', 'console_next_public_key' => 'NEXT', 'configuration_applier' => 'app.applier', 'update_runner' => 'app.runner', 'cache_pool' => 'app.pool', 'key_pool' => 'app.keys', 'lock_factory' => 'app.locks', 'max_clock_skew' => 60],
        ]]);

        self::assertSame(ExpectedWiring::all(), $this->describe($container));
    }

    public function testTheStoresInMemoryAreWiredWithoutArguments(): void
    {
        $container = $this->load([['console' => ['private_key' => Keys::CONSOLE_SEED, 'store' => 'memory']]]);

        foreach (['Site', 'Snapshot', 'ConfigurationPush', 'UpdatePlan', 'Journal', 'KeyRotation'] as $name) {
            $class = 'Phil\\SitesConsoleBundle\\Infrastructure\\Memory\\InMemory'.$name.'Repository';
            self::assertTrue($container->hasDefinition($class));
            self::assertSame([], $container->getDefinition($class)->getArguments());
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('valid')]
    public function testAValidConfiguration(array $config): void
    {
        self::assertTrue($this->load([$config])->hasDefinition(KeysCommand::class));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function valid(): iterable
    {
        yield 'doctrine store' => [['console' => ['private_key' => 'a', 'store' => 'doctrine']]];
        yield 'timeout at its lowest' => [['console' => ['private_key' => 'a', 'timeout' => 1]]];
        yield 'timeout at its highest' => [['console' => ['private_key' => 'a', 'timeout' => 120]]];
        yield 'update timeout at its lowest' => [['console' => ['private_key' => 'a', 'update_timeout' => 1]]];
        yield 'update timeout at its highest' => [['console' => ['private_key' => 'a', 'update_timeout' => 3600]]];
        yield 'one day of retention' => [['console' => ['private_key' => 'a', 'snapshot_retention_days' => 1]]];
        yield 'health auto' => [['agent' => ['site_id' => 'shop', 'private_key' => 'a', 'console_public_key' => 'b', 'health' => 'auto']]];
        yield 'health monitoring' => [['agent' => ['site_id' => 'shop', 'private_key' => 'a', 'console_public_key' => 'b', 'health' => 'monitoring']]];
        yield 'health none' => [['agent' => ['site_id' => 'shop', 'private_key' => 'a', 'console_public_key' => 'b', 'health' => 'none']]];
        yield 'skew at its lowest' => [['agent' => ['site_id' => 'shop', 'private_key' => 'a', 'console_public_key' => 'b', 'max_clock_skew' => 1]]];
        yield 'skew at its highest' => [['agent' => ['site_id' => 'shop', 'private_key' => 'a', 'console_public_key' => 'b', 'max_clock_skew' => 3600]]];
    }

    public function testTheKeysOfTheExpectedVersionsAreKeptAsWritten(): void
    {
        $container = $this->load([['console' => ['private_key' => 'a', 'expected_versions' => ['acme/my-lib' => '1.0']]]]);

        self::assertSame(['acme/my-lib' => '1.0'], $container->getDefinition(AlertRules::class)->getArgument(1));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function describe(ContainerBuilder $container): array
    {
        $normalise = static function (mixed $value) use (&$normalise): mixed {
            return match (true) {
                $value instanceof Reference => '@'.$value.(ContainerInterface::NULL_ON_INVALID_REFERENCE === $value->getInvalidBehavior() ? '?' : ''),
                \is_array($value) => array_map($normalise, $value),
                default => $value,
            };
        };

        $described = [];

        foreach ($container->getDefinitions() as $id => $definition) {
            $tags = array_keys($definition->getTags());
            sort($tags);
            $described[$id] = [
                'class' => $definition->getClass(),
                'factory' => $definition->getFactory(),
                'arguments' => $normalise($definition->getArguments()),
                'public' => $definition->isPublic(),
                'autoconfigured' => $definition->isAutoconfigured(),
                'tags' => $tags,
            ];
        }

        foreach ($container->getAliases() as $id => $alias) {
            $described['alias '.$id] = ['to' => (string) $alias, 'public' => $alias->isPublic()];
        }

        ksort($described);

        return $described;
    }

    public function testTheDoctrineMappingIsPrependedForTheConsoleOnDoctrineOnly(): void
    {
        self::assertSame([['orm' => ['mappings' => ['PhilSitesConsole' => ['type' => 'attribute', 'is_bundle' => false, 'dir' => \dirname(__DIR__, 2).'/src/Domain/Model', 'prefix' => 'Phil\SitesConsoleBundle\Domain\Model']]]]], $this->prepend([['console' => ['private_key' => 'a']]]));
        self::assertSame([['orm' => ['mappings' => ['PhilSitesConsole' => ['type' => 'attribute', 'is_bundle' => false, 'dir' => \dirname(__DIR__, 2).'/src/Domain/Model', 'prefix' => 'Phil\SitesConsoleBundle\Domain\Model']]]]], $this->prepend([['console' => ['enabled' => true, 'store' => 'memory']], ['console' => ['store' => 'doctrine']]]));
        self::assertSame([], $this->prepend([['console' => ['enabled' => true, 'store' => 'memory']]]));
        self::assertSame([], $this->prepend([['console' => ['enabled' => false]]]));
        self::assertSame([], $this->prepend([['agent' => ['enabled' => true]], 'x']));
        self::assertSame([], $this->prepend([['console' => ['private_key' => 'a']]], false));
    }

    public function testTheHealthComesFromMonitoringWhenItIsThere(): void
    {
        $container = $this->agentContainer('auto');
        $container->register(MonitoringStatus::class);
        new MonitoringPass()->process($container);

        self::assertSame(MonitoringSiteHealth::class, (string) $container->getAlias(SiteHealth::class));
        self::assertEquals([new Reference(MonitoringStatus::class)], $container->getDefinition(MonitoringSiteHealth::class)->getArguments());
    }

    public function testTheHealthIsUnknownWithoutMonitoringOrWhenAskedSo(): void
    {
        $without = $this->agentContainer('auto');
        new MonitoringPass()->process($without);
        self::assertSame(UnknownSiteHealth::class, (string) $without->getAlias(SiteHealth::class));
        self::assertTrue($without->hasDefinition(UnknownSiteHealth::class));

        $none = $this->agentContainer('none');
        $none->register(MonitoringStatus::class);
        new MonitoringPass()->process($none);
        self::assertSame(UnknownSiteHealth::class, (string) $none->getAlias(SiteHealth::class));
        self::assertFalse($none->hasDefinition(MonitoringSiteHealth::class));

        $console = new ContainerBuilder();
        new MonitoringPass()->process($console);
        self::assertFalse($console->hasAlias(SiteHealth::class));
    }

    public function testMonitoringAskedButMissingFails(): void
    {
        $this->expectExceptionObject(new LogicException('phil_sites_console.agent.health "monitoring" reads phil/monitoring-bundle: register PhilMonitoringBundle, or choose "auto" or "none".'));
        new MonitoringPass()->process($this->agentContainer('monitoring'));
    }

    public function testTheKitSubdirectoryComesFromTheToolkit(): void
    {
        $container = new ContainerBuilder();
        new KitSubdirectoryPass()->process($container);

        $container->register(KitComponents::class)->setArguments([null]);
        new KitSubdirectoryPass()->process($container);
        self::assertSame('', $container->getDefinition(KitComponents::class)->getArgument(0));

        $container->getDefinition(KitComponents::class)->setArguments([null]);
        $container->setParameter('.ux_toolkit.components.subdirectory', 'ui');
        new KitSubdirectoryPass()->process($container);
        self::assertSame('ui', $container->getDefinition(KitComponents::class)->getArgument(0));

        $container->getDefinition(KitComponents::class)->setArguments(['kit']);
        new KitSubdirectoryPass()->process($container);
        self::assertSame('kit', $container->getDefinition(KitComponents::class)->getArgument(0));
    }

    public function testThePassesAreAdded(): void
    {
        $container = new ContainerBuilder();
        new PhilSitesConsoleBundle()->build($container);
        $passes = array_map(static fn (object $pass): string => $pass::class, $container->getCompilerPassConfig()->getBeforeOptimizationPasses());

        self::assertContains(KitSubdirectoryPass::class, $passes);
        self::assertContains(MonitoringPass::class, $passes);
    }

    private function agentContainer(string $health): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(SitesConsoleServices::HEALTH_PARAMETER, $health);

        return $container;
    }

    /**
     * @param list<mixed> $configs
     *
     * @return list<array<array-key, mixed>>
     */
    private function prepend(array $configs, bool $doctrine = true): array
    {
        $container = new ContainerBuilder();

        if ($doctrine) {
            $container->registerExtension(new class extends Extension {
                public function load(array $configs, ContainerBuilder $container): void
                {
                }

                public function getAlias(): string
                {
                    return 'doctrine';
                }
            });
        }

        $extension = $this->extension();
        $container->registerExtension($extension);

        $reflection = new ReflectionProperty($container, 'extensionConfigs');
        $configsSet = $reflection->getValue($container);
        \assert(\is_array($configsSet));
        $configsSet['phil_sites_console'] = $configs;
        $reflection->setValue($container, $configsSet);

        Assert::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        if (!$doctrine) {
            return [];
        }

        /** @var list<array<array-key, mixed>> $prepended */
        $prepended = $container->getExtensionConfig('doctrine');

        return $prepended;
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilSitesConsoleBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
