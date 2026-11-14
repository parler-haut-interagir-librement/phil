<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Tests\Unit;

use Phil\MediaBundle\Application\MediaStorage;
use Phil\UploadScan\Clamd\ClamdScanner;
use Phil\UploadScan\Clamd\SocketConnector;
use Phil\UploadScan\Contract\Scanner;
use Phil\UploadScan\Exception\InvalidArgument;
use Phil\UploadScan\FakeScanner;
use Phil\UploadScan\NullScanner;
use Phil\UploadScan\OnScanError;
use Phil\UploadScan\ScanPolicy;
use Phil\UploadScanBundle\Application\Contract\Quarantine;
use Phil\UploadScanBundle\Application\Contract\QuarantineInventory;
use Phil\UploadScanBundle\Application\Contract\RescanTarget;
use Phil\UploadScanBundle\Application\Message\RescanFile;
use Phil\UploadScanBundle\Application\QuarantinePurger;
use Phil\UploadScanBundle\Application\RescanFileHandler;
use Phil\UploadScanBundle\Application\UploadScanner;
use Phil\UploadScanBundle\Infrastructure\Admin\QuarantineAdminController;
use Phil\UploadScanBundle\Infrastructure\Console\PurgeQuarantineCommand;
use Phil\UploadScanBundle\Infrastructure\DependencyInjection\QuarantineInventoryPass;
use Phil\UploadScanBundle\Infrastructure\Media\ScanningMediaStorage;
use Phil\UploadScanBundle\Infrastructure\Monitoring\ScannerHealthCheck;
use Phil\UploadScanBundle\Infrastructure\Quarantine\FilesystemQuarantine;
use Phil\UploadScanBundle\Infrastructure\Validator\CleanFileValidator;
use Phil\UploadScanBundle\PhilUploadScanBundle;
use Phil\UploadScanBundle\Tests\Unit\Fixture\ExtensionStub;
use Phil\UploadScanBundle\Tests\Unit\Fixture\MemoryMediaStorage;
use Phil\UploadScanBundle\Tests\Unit\Fixture\RecordingQuarantine;
use Phil\UploadScanBundle\Tests\Unit\Fixture\Temporary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The wiring the bundle declares: read on the `ContainerBuilder` for what it registers, and on a compiled container
 * for what the services then do.
 */
#[CoversClass(PhilUploadScanBundle::class)]
#[CoversClass(QuarantineInventoryPass::class)]
final class PhilUploadScanBundleTest extends TestCase
{
    public function testTheExtensionAliasIsPhilUploadScan(): void
    {
        $extension = new PhilUploadScanBundle()->getContainerExtension();

        self::assertNotNull($extension);
        self::assertSame('phil_upload_scan', $extension->getAlias());
    }

    public function testByDefaultClamdScansOnTheLocalPortAndAFileThatCouldNotBeScannedIsRejected(): void
    {
        $container = $this->load([]);

        self::assertSame(ClamdScanner::class, (string) $container->getAlias(Scanner::class));
        $arguments = $container->getDefinition(ClamdScanner::class)->getArguments();
        $connector = $arguments[0] ?? null;
        self::assertInstanceOf(Definition::class, $connector);
        self::assertSame([SocketConnector::class, ['tcp://127.0.0.1:3310', 5.0]], [$connector->getClass(), $connector->getArguments()]);
        self::assertSame([30.0, 26_214_400, 65_536], \array_slice($arguments, 1));
        self::assertEquals([OnScanError::Reject], $container->getDefinition(ScanPolicy::class)->getArguments());
    }

    public function testClamdIsConfigured(): void
    {
        $container = $this->load(['clamd' => ['address' => 'unix:///run/clamav/clamd.ctl', 'timeout' => 10.0, 'connect_timeout' => 1.0, 'chunk_bytes' => 4096], 'max_bytes' => 1024, 'on_error' => 'accept_and_flag']);

        $arguments = $container->getDefinition(ClamdScanner::class)->getArguments();
        $connector = $arguments[0] ?? null;
        self::assertInstanceOf(Definition::class, $connector);
        self::assertSame(['unix:///run/clamav/clamd.ctl', 1.0], $connector->getArguments());
        self::assertSame([10.0, 1024, 4096], \array_slice($arguments, 1));
        self::assertEquals([OnScanError::AcceptAndFlag], $container->getDefinition(ScanPolicy::class)->getArguments());
    }

    public function testABadAddressFailsTheBuild(): void
    {
        try {
            $this->load(['clamd' => ['address' => 'clamav']]);
            self::fail('A bad address was accepted.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertSame('phil_upload_scan.clamd.address: A clamd address is unix://<path> or tcp://<host>:<port>; "clamav" is not.', $invalidConfigurationException->getMessage());
            self::assertSame(0, $invalidConfigurationException->getCode());
            self::assertInstanceOf(InvalidArgument::class, $invalidConfigurationException->getPrevious());
        }
    }

    public function testTheSizesAreAtLeastOneByte(): void
    {
        $container = $this->load(['max_bytes' => 1, 'clamd' => ['chunk_bytes' => 1]]);
        self::assertSame([1, 1], \array_slice($container->getDefinition(ClamdScanner::class)->getArguments(), 2));

        foreach ([['max_bytes' => 0], ['clamd' => ['chunk_bytes' => 0]]] as $config) {
            try {
                $this->load($config);
                self::fail('A size of 0 bytes was accepted.');
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString('Should be greater than or equal to 1', $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testAnIntegrationMayBeSetToAuto(): void
    {
        $container = $this->load(['integrations' => ['media' => 'auto', 'monitoring' => 'auto']], ['phil_media', 'phil_monitoring']);

        self::assertTrue($container->hasDefinition(ScanningMediaStorage::class));
        self::assertTrue($container->hasDefinition(ScannerHealthCheck::class));
    }

    public function testTheOtherScanners(): void
    {
        $fake = $this->load(['scanner' => 'fake', 'max_bytes' => 10, 'clamd' => ['address' => 'not checked']]);
        self::assertSame(FakeScanner::class, (string) $fake->getAlias(Scanner::class));
        self::assertSame([[], null, 10], $fake->getDefinition(FakeScanner::class)->getArguments());
        self::assertFalse($fake->hasDefinition(ClamdScanner::class));

        $null = $this->load(['scanner' => 'null']);
        self::assertSame(NullScanner::class, (string) $null->getAlias(Scanner::class));
        self::assertTrue($null->hasDefinition(NullScanner::class));

        $custom = $this->load(['scanner' => 'app.scanner']);
        self::assertSame('app.scanner', (string) $custom->getAlias(Scanner::class));
        self::assertFalse($custom->hasDefinition(ClamdScanner::class));
        self::assertFalse($custom->hasDefinition(FakeScanner::class));
        self::assertFalse($custom->hasDefinition(NullScanner::class));
    }

    public function testTheUploadScannerAndTheConstraint(): void
    {
        $container = $this->load([]);

        self::assertEquals([
            new Reference(Scanner::class),
            new Reference(ScanPolicy::class),
            new Reference(Quarantine::class),
            new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ], $container->getDefinition(UploadScanner::class)->getArguments());

        $validator = $container->getDefinition(CleanFileValidator::class);
        self::assertEquals([new Reference(UploadScanner::class)], $validator->getArguments());
        self::assertTrue($validator->hasTag('validator.constraint_validator'));
    }

    public function testTheQuarantineIsADirectoryOrAServiceOrNone(): void
    {
        $default = $this->load([]);
        self::assertSame(FilesystemQuarantine::class, (string) $default->getAlias(Quarantine::class));
        self::assertEquals(['%kernel.project_dir%/var/quarantine', new Reference('clock', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $default->getDefinition(FilesystemQuarantine::class)->getArguments());

        $service = $this->load(['quarantine' => ['service' => 'app.quarantine']]);
        self::assertSame('app.quarantine', (string) $service->getAlias(Quarantine::class));
        self::assertFalse($service->hasDefinition(FilesystemQuarantine::class));

        $none = $this->load(['quarantine' => false]);
        self::assertFalse($none->hasAlias(Quarantine::class));
        self::assertFalse($none->hasDefinition(FilesystemQuarantine::class));
        self::assertNull($none->getDefinition(UploadScanner::class)->getArgument(2));
    }

    public function testTheQuarantineIsNeverUnderPublic(): void
    {
        foreach (['%kernel.project_dir%/public/quarantine', 'public', 'public/uploads/../q', '/srv/app/public'] as $directory) {
            try {
                $this->load(['quarantine' => ['directory' => $directory]], projectDir: '/srv/app');
                self::fail(\sprintf('The quarantine %s was accepted.', $directory));
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString('is under public/, where the web server would serve the infected files', $invalidConfigurationException->getMessage());
            }
        }

        foreach (['%kernel.project_dir%/var/quarantine', 'publications', '/srv/other/public', '/srv/app/var/public'] as $directory) {
            self::assertTrue($this->load(['quarantine' => ['directory' => $directory]], projectDir: '/srv/app')->hasDefinition(FilesystemQuarantine::class));
        }

        self::assertTrue($this->load(['quarantine' => ['directory' => '/srv/app/public']])->hasDefinition(FilesystemQuarantine::class));
        self::assertTrue($this->load(['quarantine' => ['directory' => '%app.directories%']], projectDir: '/srv/app', parameters: ['app.directories' => ['/srv/app/public']])->hasDefinition(FilesystemQuarantine::class));
    }

    public function testRescansAreHandledOnAWorker(): void
    {
        $container = $this->load([]);

        $handler = $container->getDefinition(RescanFileHandler::class);
        self::assertTrue($handler->hasTag('messenger.message_handler'));
        $targets = $handler->getArgument(1);
        self::assertInstanceOf(TaggedIteratorArgument::class, $targets);
        self::assertSame('phil_upload_scan.rescan_target', $targets->getTag());
        self::assertEquals(new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE), $handler->getArgument(2));
        self::assertSame(PhilUploadScanBundle::RESCAN_TARGET_TAG, $targets->getTag());

        $autoconfigured = $container->getAutoconfiguredInstanceof()[RescanTarget::class] ?? null;
        self::assertNotNull($autoconfigured);
        self::assertSame([PhilUploadScanBundle::RESCAN_TARGET_TAG => [[]]], $autoconfigured->getTags());
    }

    public function testRescanFileIsRoutedToTheConfiguredTransport(): void
    {
        $container = $this->prepended([['rescan' => ['transport' => 'async']], ['rescan' => ['transport' => 'scans']]]);
        self::assertSame([['messenger' => ['routing' => [RescanFile::class => 'scans']]], ['secret' => 'test']], $container->getExtensionConfig('framework'));

        foreach ([[], [['rescan' => ['transport' => 'async']], ['rescan' => ['transport' => null]]], [['rescan' => []]], [['rescan' => 'async']]] as $configs) {
            self::assertSame([['secret' => 'test']], $this->prepended($configs)->getExtensionConfig('framework'));
        }

        self::assertSame([], $this->prepended([['rescan' => ['transport' => 'async']]], false)->getExtensionConfig('framework'));
    }

    public function testTheTransportIsTheNameOfATransport(): void
    {
        self::assertTrue($this->load(['rescan' => ['transport' => 'async_scans.v-2']])->hasDefinition(RescanFileHandler::class));

        foreach (['', 'two words', str_repeat('t', 65), 12, "async\n"] as $transport) {
            try {
                $this->load(['rescan' => ['transport' => $transport]]);
                self::fail('The transport was accepted.');
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString('The transport is the name of a Messenger transport', $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testTheQuarantineIsPurgedByACommand(): void
    {
        $container = $this->load(['quarantine' => ['retention_days' => 90]]);

        self::assertEquals([
            new Reference(QuarantineInventory::class),
            new Reference('clock', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ], $container->getDefinition(QuarantinePurger::class)->getArguments());
        $command = $container->getDefinition(PurgeQuarantineCommand::class);
        self::assertEquals([new Reference(QuarantinePurger::class), 90], $command->getArguments());
        self::assertSame([['command' => 'phil:upload-scan:purge-quarantine', 'description' => 'Deletes the quarantined files kept longer than the retention.']], $command->getTag('console.command'));
        self::assertSame(30, $this->load([])->getDefinition(PurgeQuarantineCommand::class)->getArgument(1));
        self::assertFalse($this->load(['quarantine' => false])->hasDefinition(PurgeQuarantineCommand::class));
        self::assertFalse($this->load(['quarantine' => false])->hasDefinition(QuarantinePurger::class));

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('retention_days');
        $this->load(['quarantine' => ['retention_days' => 0]]);
    }

    public function testOneDayOfRetentionIsAccepted(): void
    {
        self::assertSame(1, $this->load(['quarantine' => ['retention_days' => 1]])->getDefinition(PurgeQuarantineCommand::class)->getArgument(1));
    }

    public function testWithoutTheirBundlesNoIntegrationIsWired(): void
    {
        $container = $this->load([]);

        self::assertFalse($container->hasDefinition(ScanningMediaStorage::class));
        self::assertFalse($container->hasDefinition(ScannerHealthCheck::class));
    }

    public function testWithTheirBundlesTheIntegrationsAreWired(): void
    {
        $container = $this->load([], ['phil_media', 'phil_document_import', 'phil_monitoring']);
        self::assertCount(0, array_filter(array_keys($container->getDefinitions()), static fn (string $id): bool => str_contains($id, 'DocumentImport')), 'Documents are scanned by phil/document-import-bundle itself.');

        $media = $container->getDefinition(ScanningMediaStorage::class);
        self::assertSame([MediaStorage::class, null, 0], $media->getDecoratedService());
        self::assertEquals([new Reference('.inner'), new Reference(UploadScanner::class)], $media->getArguments());

        $check = $container->getDefinition(ScannerHealthCheck::class);
        self::assertEquals([new Reference(Scanner::class)], $check->getArguments());
        self::assertTrue($check->hasTag('phil_monitoring.health_check'));
        self::assertTrue($this->load(['scanner' => 'fake'], ['phil_monitoring'])->hasDefinition(ScannerHealthCheck::class));
        self::assertFalse($this->load(['scanner' => 'null'], ['phil_monitoring'])->hasDefinition(ScannerHealthCheck::class));
        self::assertFalse($this->load(['scanner' => 'app.scanner'], ['phil_monitoring'])->hasDefinition(ScannerHealthCheck::class));
    }

    public function testAnIntegrationCanBeTurnedOff(): void
    {
        $container = $this->load(['integrations' => ['media' => 'off', 'monitoring' => 'off']], ['phil_media', 'phil_monitoring']);

        self::assertFalse($container->hasDefinition(ScanningMediaStorage::class));
        self::assertFalse($container->hasDefinition(ScannerHealthCheck::class));
        self::assertTrue($this->load(['integrations' => ['media' => 'on']], ['phil_media'])->hasDefinition(ScanningMediaStorage::class));
    }

    public function testAnIntegrationTurnedOnRequiresItsBundle(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_upload_scan.integrations.monitoring is on, but the bundle of phil_monitoring is not registered.');

        $this->load(['integrations' => ['monitoring' => 'on']]);
    }

    public function testTheDocumentImportIsNotAnIntegration(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('Unrecognized option "document_import" under "phil_upload_scan.integrations"');

        $this->load(['integrations' => ['document_import' => 'auto']]);
    }

    public function testTheCompiledServicesScanAndQuarantine(): void
    {
        $directory = Temporary::directory();
        $container = $this->load(['scanner' => 'fake', 'quarantine' => ['directory' => $directory]], ['phil_media']);
        $container->register(MemoryMediaStorage::class, MemoryMediaStorage::class);
        $container->setAlias(MediaStorage::class, MemoryMediaStorage::class)->setPublic(true);
        $container->getDefinition(UploadScanner::class)->setPublic(true);
        // Without Twig, a router and the CSRF token manager in this container, the administration is left out.
        $container->removeDefinition(QuarantineAdminController::class);
        new PhilUploadScanBundle()->build($container);
        $container->compile(true);

        try {
            $scanner = $container->get(UploadScanner::class);
            self::assertInstanceOf(UploadScanner::class, $scanner);
            $inspection = $scanner->inspectContents(Temporary::eicar(), 'x');
            self::assertNotNull($inspection->quarantineId);
            self::assertFileExists($directory.'/'.$inspection->quarantineId.'.quarantine');
            self::assertInstanceOf(ScanningMediaStorage::class, $container->get(MediaStorage::class));
        } finally {
            Temporary::remove($directory);
        }
    }

    public function testTheAdministrationOfTheQuarantine(): void
    {
        $container = $this->load(['admin' => ['role' => 'ROLE_SECURITY', 'layout' => 'admin/base.html.twig', 'page_size' => 20]]);
        $admin = $container->getDefinition(QuarantineAdminController::class);
        self::assertEquals([
            new Reference(QuarantineInventory::class),
            new Reference('twig'),
            new Reference('router'),
            new Reference('security.csrf.token_manager'),
            new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            'ROLE_SECURITY',
            'admin/base.html.twig',
            20,
            new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ], $admin->getArguments());
        self::assertTrue($admin->hasTag('controller.service_arguments'));
        self::assertTrue($admin->isPublic());

        $defaults = $this->load([])->getDefinition(QuarantineAdminController::class)->getArguments();
        self::assertSame(['ROLE_ADMIN', '@PhilUploadScan/admin/layout.html.twig', 50], \array_slice($defaults, 5, 3));
        self::assertFalse($this->load(['quarantine' => false])->hasDefinition(QuarantineAdminController::class));

        foreach ([1, 500] as $size) {
            self::assertSame($size, $this->load(['admin' => ['page_size' => $size]])->getDefinition(QuarantineAdminController::class)->getArgument(7));
        }

        foreach ([0, 501] as $size) {
            try {
                $this->load(['admin' => ['page_size' => $size]]);
                self::fail('A page holds 1 to 500 files.');
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString('page_size', $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testTheBundleRegistersThePassThatGivesTheInventory(): void
    {
        $container = new ContainerBuilder();
        new PhilUploadScanBundle()->build($container);

        $passes = array_filter($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof QuarantineInventoryPass);
        self::assertCount(1, $passes);
    }

    public function testTheInventoryIsTheQuarantineWhenItCanBeListed(): void
    {
        $directory = $this->compiled([]);
        self::assertSame(Quarantine::class, (string) $directory->getAlias(QuarantineInventory::class));
        self::assertTrue($directory->hasDefinition(QuarantineAdminController::class));

        $listed = $this->compiled(['quarantine' => ['service' => 'app.quarantine']], static fn (ContainerBuilder $container): Definition => $container->register('app.quarantine', '%app.quarantine_class%')->setPublic(true), ['app.quarantine_class' => FilesystemQuarantine::class]);
        self::assertSame(Quarantine::class, (string) $listed->getAlias(QuarantineInventory::class));

        self::assertTrue($directory->hasDefinition(PurgeQuarantineCommand::class));

        $hidden = $this->compiled(['quarantine' => ['service' => 'app.quarantine']], static fn (ContainerBuilder $container): Definition => $container->register('app.quarantine', RecordingQuarantine::class)->setPublic(true));
        self::assertFalse($hidden->has(QuarantineInventory::class));
        self::assertFalse($hidden->hasDefinition(QuarantineAdminController::class));
        self::assertFalse($hidden->hasDefinition(QuarantinePurger::class));
        self::assertFalse($hidden->hasDefinition(PurgeQuarantineCommand::class));

        $own = $this->compiled(['quarantine' => ['service' => 'app.quarantine']], static function (ContainerBuilder $container): void {
            $container->register('app.quarantine', FilesystemQuarantine::class)->setArguments(['/tmp'])->setPublic(true);
            $container->register('app.inventory', FilesystemQuarantine::class)->setArguments(['/tmp']);
            $container->setAlias(QuarantineInventory::class, 'app.inventory');
        });
        self::assertSame('app.inventory', (string) $own->getAlias(QuarantineInventory::class));
        self::assertTrue($own->hasDefinition(QuarantineAdminController::class));

        $chained = $this->compiled(['quarantine' => ['service' => 'app.quarantine']], static function (ContainerBuilder $container): void {
            $container->setAlias('app.quarantine', 'app.real_quarantine');
            $container->register('app.real_quarantine', FilesystemQuarantine::class)->setArguments(['/tmp']);
        });
        self::assertSame(Quarantine::class, (string) $chained->getAlias(QuarantineInventory::class));

        $missing = $this->compiled(['quarantine' => ['service' => 'app.missing']]);
        self::assertFalse($missing->has(QuarantineInventory::class));
        self::assertFalse($missing->hasDefinition(QuarantineAdminController::class));

        $notAClass = $this->compiled(['quarantine' => ['service' => 'app.quarantine']], static fn (ContainerBuilder $container): Definition => $container->register('app.quarantine', '%app.quarantine_class%'), ['app.quarantine_class' => [FilesystemQuarantine::class]]);
        self::assertFalse($notAClass->has(QuarantineInventory::class));

        $none = $this->compiled(['quarantine' => false]);
        self::assertFalse($none->has(QuarantineInventory::class));
    }

    /**
     * The container with the bundle's compiler pass run, the services the administration takes registered as stand-ins.
     *
     * @param array<string, mixed>                    $config
     * @param array<string, list<string>|string>|null $parameters
     */
    private function compiled(array $config, ?callable $prepare = null, ?array $parameters = null): ContainerBuilder
    {
        $container = $this->load(['scanner' => 'null', ...$config], parameters: $parameters ?? []);

        foreach (['twig', 'router', 'security.csrf.token_manager'] as $id) {
            $container->register($id)->setSynthetic(true);
        }

        if (null !== $prepare) {
            $prepare($container);
        }

        new PhilUploadScanBundle()->build($container);
        new QuarantineInventoryPass()->process($container);

        return $container;
    }

    /**
     * The configuration of FrameworkBundle once the bundle prepended its own to it.
     *
     * @param list<array<string, mixed>> $configs the configurations of the bundle, in order
     */
    private function prepended(array $configs, bool $framework = true): ContainerBuilder
    {
        $container = new ContainerBuilder();

        if ($framework) {
            $container->registerExtension(new ExtensionStub('framework'));
            $container->loadFromExtension('framework', ['secret' => 'test']);
        }

        $bundle = new PhilUploadScanBundle();
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);
        $container->registerExtension($extension);

        foreach ($configs as $config) {
            $container->loadFromExtension('phil_upload_scan', $config);
        }

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__));
        $instanceof = [];
        $bundle->prependExtension(new ContainerConfigurator($container, $loader, $instanceof, __DIR__, __FILE__), $container);

        return $container;
    }

    /**
     * @param array<string, mixed>               $config
     * @param list<string>                       $extensions the aliases of the bundles registered beside it
     * @param array<string, list<string>|string> $parameters
     */
    private function load(array $config, array $extensions = [], ?string $projectDir = null, array $parameters = []): ContainerBuilder
    {
        $container = new ContainerBuilder();

        foreach ($parameters as $name => $value) {
            $container->setParameter($name, $value);
        }

        if (null !== $projectDir) {
            $container->setParameter('kernel.project_dir', $projectDir);
        }

        foreach ($extensions as $alias) {
            $container->registerExtension(new ExtensionStub($alias));
        }

        $extension = new PhilUploadScanBundle()->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load([$config], $container);

        return $container;
    }
}
