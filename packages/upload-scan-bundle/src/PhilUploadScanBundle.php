<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle;

use Override;
use Phil\MediaBundle\Application\MediaStorage;
use Phil\MonitoringBundle\PhilMonitoringBundle;
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
use Phil\UploadScanBundle\Infrastructure\DependencyInjection\CsrfProtectionPass;
use Phil\UploadScanBundle\Infrastructure\DependencyInjection\QuarantineInventoryPass;
use Phil\UploadScanBundle\Infrastructure\Media\ScanningMediaStorage;
use Phil\UploadScanBundle\Infrastructure\Monitoring\ScannerHealthCheck;
use Phil\UploadScanBundle\Infrastructure\Quarantine\FilesystemQuarantine;
use Phil\UploadScanBundle\Infrastructure\Validator\CleanFileValidator;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * `phil/upload-scan` in a Symfony application, from its configuration:
 *
 * ```yaml
 * phil_upload_scan:
 *     scanner: clamd                  # fake, null, or the id of a Scanner service
 *     clamd:
 *         address: tcp://clamav:3310  # or unix:///run/clamav/clamd.ctl
 *         timeout: 30
 *     max_bytes: 26214400
 *     on_error: reject                # or accept_and_flag
 *     quarantine:
 *         directory: '%kernel.project_dir%/var/quarantine'
 *         tighten_open_directory: true  # false refuses a directory open to other users
 *         retention_days: 30          # read by phil:upload-scan:purge-quarantine
 *     rescan:
 *         transport: async            # RescanFile routed to this Messenger transport
 *     admin:
 *         enabled: true               # false leaves the administration, and its CSRF check, out
 *         role: ROLE_ADMIN
 * ```
 *
 * The bundle wires the `Scanner`, the `ScanPolicy`, the quarantine and the `UploadScanner`; the `#[CleanFile]`
 * constraint; the handler of `RescanFile` when Messenger is installed, and its routing to a transport; the quarantine's
 * administration when Twig and the CSRF component are installed and the quarantine can be listed, and its purge
 * command when the Console component is; and, when their bundles are registered, the
 * storage of `phil/media-bundle` behind the antivirus and the health check of `phil/monitoring-bundle`.
 * `phil/document-import-bundle` scans the documents it reads through `UploadScanner` itself.
 */
final class PhilUploadScanBundle extends AbstractBundle
{
    /**
     * The tag of the services implementing `RescanTarget`. A service of the application implementing the interface is
     * tagged by autoconfiguration.
     */
    public const string RESCAN_TARGET_TAG = 'phil_upload_scan.rescan_target';

    private const array ADAPTERS = ['clamd', 'fake', 'null'];

    #[Override]
    protected string $extensionAlias = 'phil_upload_scan';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('scanner')
                    ->info('clamd, fake (finds the EICAR test file, for tests), null (scans nothing), or the id of a Scanner service.')
                    ->defaultValue('clamd')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('clamd')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('address')
                            ->info('Where clamd listens: tcp://<host>:<port> or unix://<path>.')
                            ->defaultValue('tcp://127.0.0.1:3310')
                            ->cannotBeEmpty()
                        ->end()
                        ->floatNode('timeout')
                            ->info('How long each read or write on the connection to clamd may wait, in seconds.')
                            ->min(0.001)
                            ->defaultValue(30.0)
                        ->end()
                        ->floatNode('connect_timeout')
                            ->info('How long the connection to clamd may take, in seconds.')
                            ->min(0.001)
                            ->defaultValue(5.0)
                        ->end()
                        ->integerNode('chunk_bytes')
                            ->info('The size of each chunk of a file sent to clamd.')
                            ->min(1)
                            ->defaultValue(ClamdScanner::DEFAULT_CHUNK_BYTES)
                        ->end()
                    ->end()
                ->end()
                ->integerNode('max_bytes')
                    ->info('The largest file scanned, at most the StreamMaxLength of clamd; a larger one cannot be scanned.')
                    ->min(1)
                    ->defaultValue(ClamdScanner::DEFAULT_MAX_BYTES)
                ->end()
                ->enumNode('on_error')
                    ->info('What becomes of a file that could not be scanned: reject, or accept_and_flag.')
                    ->values(array_column(OnScanError::cases(), 'value'))
                    ->defaultValue(OnScanError::Reject->value)
                ->end()
                ->arrayNode('quarantine')
                    ->info('Where infected files are kept: never a directory the web server serves.')
                    ->addDefaultsIfNotSet()
                    ->canBeDisabled()
                    ->children()
                        ->scalarNode('directory')
                            ->defaultValue('%kernel.project_dir%/var/quarantine')
                            ->cannotBeEmpty()
                        ->end()
                        ->booleanNode('tighten_open_directory')
                            ->info('Whether a directory open to its group or to other users is narrowed to its owner; false refuses it.')
                            ->defaultTrue()
                        ->end()
                        ->scalarNode('service')
                            ->info('The id of a Quarantine service, instead of the directory.')
                            ->defaultNull()
                        ->end()
                        ->integerNode('retention_days')
                            ->info('How long phil:upload-scan:purge-quarantine keeps a file, in days.')
                            ->min(1)
                            ->defaultValue(30)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('rescan')
                    ->info('The scans on a worker, by RescanFile.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('transport')
                            ->info('The Messenger transport RescanFile is routed to; null leaves its routing to the application.')
                            ->defaultNull()
                            ->validate()
                                ->ifTrue(static fn (mixed $value): bool => null !== $value && (!\is_string($value) || 1 !== preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $value)))
                                ->thenInvalid('The transport is the name of a Messenger transport, such as async: %s.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('admin')
                    ->info('The administration of the quarantine, once its routes are imported; registered with Twig and symfony/security-csrf unless disabled.')
                    ->addDefaultsIfNotSet()
                    ->canBeDisabled()
                    ->children()
                        ->scalarNode('role')
                            ->info('The role, or the attribute of a voter, that opens it.')
                            ->defaultValue('ROLE_ADMIN')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('layout')
                            ->info('The template its page extends, with title, stylesheets, javascripts and content blocks.')
                            ->defaultValue('@PhilUploadScan/admin/layout.html.twig')
                            ->cannotBeEmpty()
                        ->end()
                        ->integerNode('page_size')->min(1)->max(500)->defaultValue(50)->end()
                    ->end()
                ->end()
                ->arrayNode('integrations')
                    ->info('auto turns each one on when its bundle is registered; on requires it.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('media')->values(['auto', 'on', 'off'])->defaultValue('auto')->end()
                        ->enumNode('monitoring')->values(['auto', 'on', 'off'])->defaultValue('auto')->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{scanner: string, clamd: array{address: string, timeout: float, connect_timeout: float, chunk_bytes: int}, max_bytes: int, on_error: string, quarantine: array{enabled: bool, directory: string, tighten_open_directory: bool, service: ?string, retention_days: int<1, max>}, rescan: array{transport: ?string}, admin: array{enabled: bool, role: string, layout: string, page_size: int<1, 500>}, integrations: array{media: string, monitoring: string}} $config */
        $services = $container->services();
        $adapter = \in_array($config['scanner'], self::ADAPTERS, true) ? $config['scanner'] : null;

        $builder->registerForAutoconfiguration(RescanTarget::class)->addTag(self::RESCAN_TARGET_TAG);

        if ('clamd' === $adapter) {
            // Read once here, so that a bad address fails the container build rather than the first upload.
            try {
                new SocketConnector($config['clamd']['address']);
            } catch (InvalidArgument $invalidArgument) {
                throw new InvalidConfigurationException('phil_upload_scan.clamd.address: '.$invalidArgument->getMessage(), 0, $invalidArgument);
            }
        }

        match ($adapter) {
            'clamd' => $services->set(ClamdScanner::class)->args([
                inline_service(SocketConnector::class)->args([$config['clamd']['address'], $config['clamd']['connect_timeout']]),
                $config['clamd']['timeout'],
                $config['max_bytes'],
                $config['clamd']['chunk_bytes'],
            ]),
            'fake' => $services->set(FakeScanner::class)->args([[], null, $config['max_bytes']]),
            'null' => $services->set(NullScanner::class),
            default => null,
        };

        $services->alias(Scanner::class, match ($adapter) {
            'clamd' => ClamdScanner::class,
            'fake' => FakeScanner::class,
            'null' => NullScanner::class,
            default => $config['scanner'],
        });

        $services->set(ScanPolicy::class)->args([OnScanError::from($config['on_error'])]);

        $quarantine = $this->quarantine($config['quarantine'], $builder);

        if (FilesystemQuarantine::class === $quarantine) {
            $services->set(FilesystemQuarantine::class)->args([$config['quarantine']['directory'], service('clock')->nullOnInvalid(), $config['quarantine']['tighten_open_directory']]);
        }

        if (null !== $quarantine) {
            $services->alias(Quarantine::class, $quarantine);
        }

        if (null !== $quarantine && class_exists(Command::class)) {
            // Removed by QuarantineInventoryPass with the purger when the quarantine cannot be listed.
            $services->set(QuarantinePurger::class)->args([
                service(QuarantineInventory::class),
                service('clock')->nullOnInvalid(),
                service('logger')->nullOnInvalid(),
            ]);
            $services->set(PurgeQuarantineCommand::class)
                ->args([service(QuarantinePurger::class), $config['quarantine']['retention_days']])
                ->tag('console.command', ['command' => PurgeQuarantineCommand::NAME, 'description' => 'Deletes the quarantined files kept longer than the retention.']);
        }

        if (null !== $quarantine && $config['admin']['enabled'] && $this->adminCanBeRegistered()) {
            // Removed by QuarantineInventoryPass when the quarantine cannot be listed.
            $services->set(QuarantineAdminController::class)
                ->args([
                    service(QuarantineInventory::class),
                    service('twig'),
                    service('router'),
                    service('security.csrf.token_manager'),
                    service('security.authorization_checker')->nullOnInvalid(),
                    $config['admin']['role'],
                    $config['admin']['layout'],
                    $config['admin']['page_size'],
                    service('logger')->nullOnInvalid(),
                ])
                ->tag('controller.service_arguments')
                ->public();
        }

        $services->set(UploadScanner::class)->args([
            service(Scanner::class),
            service(ScanPolicy::class),
            null === $quarantine ? null : service(Quarantine::class),
            service('event_dispatcher')->nullOnInvalid(),
            service('logger')->nullOnInvalid(),
        ]);

        $services->set(CleanFileValidator::class)
            ->args([service(UploadScanner::class)])
            ->tag('validator.constraint_validator');

        if (interface_exists(MessageBusInterface::class)) {
            $services->set(RescanFileHandler::class)
                ->args([service(UploadScanner::class), tagged_iterator(self::RESCAN_TARGET_TAG), service('event_dispatcher')->nullOnInvalid()])
                ->tag('messenger.message_handler');
        }

        if ($this->integrates($builder, $config['integrations']['media'], 'phil_media', 'media')) {
            $services->set(ScanningMediaStorage::class)
                ->decorate(MediaStorage::class)
                ->args([service('.inner'), service(UploadScanner::class)]);
        }

        if (\in_array($adapter, ['clamd', 'fake'], true) && $this->integrates($builder, $config['integrations']['monitoring'], 'phil_monitoring', 'monitoring')) {
            $services->set(ScannerHealthCheck::class)
                ->args([service(Scanner::class)])
                ->tag(PhilMonitoringBundle::HEALTH_CHECK_TAG);
        }
    }

    /**
     * Lists the administration's CSRF token among FrameworkBundle's stateless ones, and routes `RescanFile` to the
     * transport `rescan.transport` names, before FrameworkBundle reads its configuration: the application's own routing
     * of the message, given after, still wins.
     */
    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('framework') && $this->adminCanBeRegistered()) {
            // The administration's token is checked by the request's origin, without a session.
            $builder->prependExtensionConfig('framework', ['csrf_protection' => ['stateless_token_ids' => [QuarantineAdminController::CSRF_TOKEN_ID]]]);
        }

        if (!$builder->hasExtension('framework') || !interface_exists(MessageBusInterface::class)) {
            return;
        }

        $transport = null;

        foreach ($builder->getExtensionConfig('phil_upload_scan') as $config) {
            $rescan = \is_array($config) ? $config['rescan'] ?? null : null;

            if (\is_array($rescan) && \array_key_exists('transport', $rescan)) {
                $transport = $rescan['transport'];
            }
        }

        if (\is_string($transport) && '' !== $transport) {
            $builder->prependExtensionConfig('framework', ['messenger' => ['routing' => [RescanFile::class => $transport]]]);
        }
    }

    /**
     * Gives the quarantine's inventory once every service is known.
     */
    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new QuarantineInventoryPass());
        $container->addCompilerPass(new CsrfProtectionPass());
    }

    /**
     * The id of the quarantine service, or `null` without one. The default directory is refused under `public/`: a
     * quarantined file is never served.
     *
     * @param array{enabled: bool, directory: string, tighten_open_directory: bool, service: ?string, retention_days: int<1, max>} $config
     */
    private function quarantine(array $config, ContainerBuilder $builder): ?string
    {
        if (!$config['enabled']) {
            return null;
        }

        if (null !== $config['service']) {
            return $config['service'];
        }

        $projectDir = $builder->hasParameter('kernel.project_dir') ? $builder->getParameter('kernel.project_dir') : null;

        if (!\is_string($projectDir)) {
            return FilesystemQuarantine::class;
        }

        $directory = $builder->getParameterBag()->resolveValue($config['directory']);

        if (\is_string($directory) && Path::isBasePath(Path::join($projectDir, 'public'), Path::makeAbsolute($directory, $projectDir))) {
            throw new InvalidConfigurationException(\sprintf('phil_upload_scan.quarantine.directory "%s" is under public/, where the web server would serve the infected files: choose a directory outside it, such as var/quarantine.', $directory));
        }

        return FilesystemQuarantine::class;
    }

    /**
     * The administration renders its page with Twig and checks its deletions with the CSRF component.
     */
    private function adminCanBeRegistered(): bool
    {
        return class_exists(Environment::class) && interface_exists(CsrfTokenManagerInterface::class);
    }

    private function integrates(ContainerBuilder $builder, string $mode, string $extension, string $name): bool
    {
        if ('off' === $mode) {
            return false;
        }

        if ($builder->hasExtension($extension)) {
            return true;
        }

        if ('on' === $mode) {
            throw new InvalidConfigurationException(\sprintf('phil_upload_scan.integrations.%s is on, but the bundle of %s is not registered.', $name, $extension));
        }

        return false;
    }
}
