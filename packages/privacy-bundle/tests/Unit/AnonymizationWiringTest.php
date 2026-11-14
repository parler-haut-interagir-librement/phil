<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Phil\PrivacyBundle\Application\Anonymization\AnonymizationRun;
use Phil\PrivacyBundle\Application\Anonymization\ProductionGuard;
use Phil\PrivacyBundle\Domain\Anonymization\Anonymizers;
use Phil\PrivacyBundle\Domain\Anonymization\DatabaseIdentity;
use Phil\PrivacyBundle\Domain\Anonymization\Locale\LocalePack;
use Phil\PrivacyBundle\Domain\Anonymization\Locale\LocalePacks;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Infrastructure\Console\AnonymizeDatabaseCommand;
use Phil\PrivacyBundle\Infrastructure\Database\DbalAnonymizationTarget;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\AnonymizationServices;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\DatabaseServicesPass;
use Phil\PrivacyBundle\Infrastructure\Doctrine\AnonymizationPlanReader;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Phil\PrivacyBundle\Tests\Unit\Fixture\VatNumberAnonymizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The anonymization services the bundle declares, compiled into a container with and without a
 * connection.
 */
#[CoversClass(PhilPrivacyBundle::class)]
#[CoversClass(DatabaseServicesPass::class)]
#[CoversClass(AnonymizationServices::class)]
final class AnonymizationWiringTest extends TestCase
{
    public function testTheDefaultsWireEveryService(): void
    {
        $container = $this->load([]);

        self::assertEquals([new Reference('database_connection')], $container->getDefinition(PhilPrivacyBundle::DATABASE_IDENTITY)->getArguments());
        self::assertEquals([new Reference('database_connection'), 500], $container->getDefinition(DbalAnonymizationTarget::class)->getArguments());
        self::assertEquals([new Reference('doctrine', ContainerInterface::NULL_ON_INVALID_REFERENCE), [], []], $container->getDefinition(AnonymizationPlanReader::class)->getArguments());
        self::assertSame(['%kernel.environment%', ['prod'], [], []], $container->getDefinition(ProductionGuard::class)->getArguments());
        self::assertSame('en_US', $container->getDefinition(AnonymizationRun::class)->getArgument(2));
        self::assertTrue($container->getDefinition(AnonymizationRun::class)->isPublic());

        self::assertTrue($container->getDefinition(AnonymizeDatabaseCommand::class)->hasTag('console.command'));
        self::assertSame(['phil_privacy.anonymizer', 'phil_privacy.locale_pack', 'phil_privacy.database_identity'], [PhilPrivacyBundle::ANONYMIZER_TAG, PhilPrivacyBundle::LOCALE_PACK_TAG, PhilPrivacyBundle::DATABASE_IDENTITY]);
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load([
            'database' => ['connection' => 'doctrine.dbal.legacy_connection'],
            'anonymization' => [
                'locale' => 'fr_CA',
                'batch_size' => 1,
                'categories' => ['email' => 'email', 'person-name' => 'name'],
                'tables' => ['member' => ['email' => 'hash', 'note' => ['type' => 'lorem', 'options' => ['words' => 3]]], 'legacy-log' => ['remote-ip' => ['type' => 'pattern', 'options' => ['pattern' => '10.0.0.#', 'my-option' => 1]]]],
                'guard' => ['environments' => ['prod', 'staging'], 'hosts' => ['db.example.com'], 'databases' => ['app_prod']],
            ],
        ]);

        self::assertEquals(new Reference('doctrine.dbal.legacy_connection'), $container->getDefinition(PhilPrivacyBundle::DATABASE_IDENTITY)->getArgument(0));
        self::assertEquals(new Reference('doctrine.dbal.legacy_connection'), $container->getDefinition(DbalAnonymizationTarget::class)->getArgument(0));
        self::assertSame(1, $container->getDefinition(DbalAnonymizationTarget::class)->getArgument(1));
        self::assertSame([['email' => 'email', 'person-name' => 'name'], ['member' => ['email' => ['type' => 'hash', 'options' => []], 'note' => ['type' => 'lorem', 'options' => ['words' => 3]]], 'legacy-log' => ['remote-ip' => ['type' => 'pattern', 'options' => ['pattern' => '10.0.0.#', 'my-option' => 1]]]]], \array_slice($container->getDefinition(AnonymizationPlanReader::class)->getArguments(), 1));
        self::assertSame(['%kernel.environment%', ['prod', 'staging'], ['db.example.com'], ['app_prod']], $container->getDefinition(ProductionGuard::class)->getArguments());
        self::assertSame('fr_CA', $container->getDefinition(AnonymizationRun::class)->getArgument(2));
    }

    public function testAMalformedTableRuleFailsTheBuild(): void
    {
        foreach ([
            'phil_privacy.anonymization: "E-mail" is not an anonymizer type' => ['member' => ['email' => 'E-mail']],
            'phil_privacy.anonymization.tables.member.email needs a type, and named options whose values are scalars or null.' => ['member' => ['email' => ['type' => 'lorem', 'options' => ['words' => [3]]]]],
            'phil_privacy.anonymization.tables.member.note needs a type, and named options whose values are scalars or null.' => ['member' => ['note' => ['type' => 'lorem', 'options' => [3]]]],
        ] as $message => $tables) {
            try {
                $this->load(['anonymization' => ['tables' => $tables]]);
                self::fail($message);
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringStartsWith($message, $invalidConfigurationException->getMessage());
            }
        }

        try {
            $this->load(['anonymization' => ['tables' => ['member' => ['email' => 'E-mail']]]]);
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertInstanceOf(InvalidPrivacyValue::class, $invalidConfigurationException->getPrevious());
            self::assertSame(0, $invalidConfigurationException->getCode());
        }
    }

    public function testTheProcessedTreeRefusesWhatTheBundleCannotRead(): void
    {
        foreach ([
            ['anonymization' => ['batch_size' => 0]],
            ['anonymization' => ['tables' => ['member' => ['email' => ['options' => []]]]]],
            ['database' => ['connection' => '']],
        ] as $config) {
            try {
                $this->load($config);
                self::fail(json_encode($config, \JSON_THROW_ON_ERROR));
            } catch (InvalidConfigurationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * The extension's own checks, behind the tree: maps of strings and string table names.
     */
    public function testTheExtensionReadsOnlyStrings(): void
    {
        $bundle = new PhilPrivacyBundle();

        foreach ([
            'phil_privacy.anonymization.categories must map names to strings.' => ['anonymization' => ['categories' => ['email' => 5]]],
            'phil_privacy.anonymization.tables.5.email needs a type' => ['anonymization' => ['tables' => [5 => ['email' => ['type' => 'hash']]]]],
            'phil_privacy.anonymization.tables.member.5 needs a type' => ['anonymization' => ['tables' => ['member' => [5 => ['type' => 'hash']]]]],
            'phil_privacy.anonymization.tables.member.email needs a type' => ['anonymization' => ['tables' => ['member' => ['email' => 'hash']]]],
            'phil_privacy.anonymization.guard.hosts must be a list of strings.' => ['anonymization' => ['guard' => ['hosts' => [5]]]],
        ] as $message => $config) {
            try {
                $this->loadRaw($bundle, $config);
                self::fail($message);
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringStartsWith($message, $invalidConfigurationException->getMessage());
            }
        }

        $container = $this->loadRaw($bundle, ['database' => 'none', 'anonymization' => ['guard' => 'none', 'tables' => ['member' => 'none', 'note' => ['body' => ['type' => 'lorem', 'options' => 'none']]]]]);
        self::assertEquals(new Reference('database_connection'), $container->getDefinition(PhilPrivacyBundle::DATABASE_IDENTITY)->getArgument(0));
        self::assertSame(500, $container->getDefinition(DbalAnonymizationTarget::class)->getArgument(1));
        self::assertSame('en_US', $container->getDefinition(AnonymizationRun::class)->getArgument(2));
        self::assertSame(['note' => ['body' => ['type' => 'lorem', 'options' => []]]], $container->getDefinition(AnonymizationPlanReader::class)->getArgument(2));
        self::assertSame(['%kernel.environment%', [], [], []], $container->getDefinition(ProductionGuard::class)->getArguments());
    }

    /**
     * Compiled with a connection: an application's anonymizer and locale pack join the built-in ones, and the
     * commands are there.
     */
    public function testACompiledContainerRunsWithTheApplicationsAnonymizersAndPacks(): void
    {
        $path = sys_get_temp_dir().'/phil-privacy-'.bin2hex(random_bytes(6)).'.sqlite';
        $container = $this->container([]);
        $container->register('database_connection', Connection::class)->setFactory([DriverManager::class, 'getConnection'])->setArguments([['driver' => 'pdo_sqlite', 'path' => $path]]);
        $container->register(VatNumberAnonymizer::class)->setAutoconfigured(true);
        $container->register('app.locale.fr_BE', LocalePack::class)->setAutoconfigured(true)->setArguments(['fr_BE', ['Lucie'], ['Peeters'], ['de la Loi'], ['{number}, rue {street}'], ['Liège'], ['Wallonie'], ['####'], [], 'BE', 'Belgique', '{line}']);

        foreach ([Anonymizers::class, LocalePacks::class, PhilPrivacyBundle::DATABASE_IDENTITY, AnonymizeDatabaseCommand::class] as $id) {
            $container->getDefinition($id)->setPublic(true);
        }

        $container->compile(true);

        $services = [];

        foreach ([Anonymizers::class => Anonymizers::class, LocalePacks::class => LocalePacks::class, PhilPrivacyBundle::DATABASE_IDENTITY => DatabaseIdentity::class, AnonymizeDatabaseCommand::class => AnonymizeDatabaseCommand::class] as $id => $class) {
            $services[$id] = $this->service($container, $id, $class);
        }

        self::assertSame([...Anonymizers::builtIn()->types(), 'vat_number'], $this->service($container, Anonymizers::class, Anonymizers::class)->types());
        self::assertSame([...LocalePacks::builtIn()->codes(), 'fr_BE'], $this->service($container, LocalePacks::class, LocalePacks::class)->codes());
        self::assertCount(4, $services);
        self::assertSame($path, $this->service($container, PhilPrivacyBundle::DATABASE_IDENTITY, DatabaseIdentity::class)->name);
        self::assertFileDoesNotExist($path, 'Reading the identity opens no connection.');
    }

    /**
     * Without the connection service, the database services are removed, and the rest still compiles.
     */
    public function testWithoutTheConnectionTheDatabaseServicesAreRemoved(): void
    {
        $container = $this->container([]);

        foreach ([AnonymizationRun::class, PhilPrivacyBundle::DATABASE_IDENTITY, DbalAnonymizationTarget::class, AnonymizeDatabaseCommand::class] as $id) {
            $container->getDefinition($id)->setPublic(true);
        }

        $container->compile(true);

        foreach ([PhilPrivacyBundle::DATABASE_IDENTITY, DbalAnonymizationTarget::class, AnonymizeDatabaseCommand::class] as $id) {
            self::assertFalse($container->has($id), $id);
        }

        self::assertInstanceOf(AnonymizationRun::class, $this->service($container, AnonymizationRun::class, AnonymizationRun::class));

        $alias = $this->container([]);
        $alias->register('real_connection', Connection::class)->setFactory([DriverManager::class, 'getConnection'])->setArguments([['driver' => 'pdo_sqlite', 'memory' => true]]);
        $alias->setAlias('database_connection', 'real_connection');
        new DatabaseServicesPass()->process($alias);
        self::assertTrue($alias->hasDefinition(AnonymizeDatabaseCommand::class), 'An alias is a service too.');

        $other = $this->container([]);
        $other->getDefinition(PhilPrivacyBundle::DATABASE_IDENTITY)->setArgument(0, 'not a reference');
        new DatabaseServicesPass()->process($other);
        self::assertFalse($other->hasDefinition(DbalAnonymizationTarget::class));

        $none = new ContainerBuilder();
        new DatabaseServicesPass()->process($none);
        self::assertSame(['service_container'], $none->getServiceIds());
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(ContainerBuilder $container, string $id, string $class): object
    {
        $service = $container->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function container(array $config): ContainerBuilder
    {
        $container = $this->load($config);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        new PhilPrivacyBundle()->build($container);

        return $container;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $extension = new PhilPrivacyBundle()->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load([$config], $container);

        return $container;
    }

    /**
     * The extension's `loadExtension` given a configuration the tree did not process.
     *
     * @param array<string, mixed> $config
     */
    private function loadRaw(PhilPrivacyBundle $bundle, array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $instanceof = [];
        $configurator = new ContainerConfigurator($container, new PhpFileLoader($container, new FileLocator()), $instanceof, __FILE__, __FILE__);
        $extension = $bundle->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], $container);
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);
        $defaults = new Processor()->processConfiguration($configuration, []);
        $bundle->loadExtension($config + array_diff_key($defaults, ['log_redaction' => true, 'register' => true, 'database' => true, 'anonymization' => true]), $configurator, $container);

        return $container;
    }
}
