<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle;

use InvalidArgumentException;
use Override;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Application\PrivacyRights;
use Phil\PrivacyBundle\Application\Register\ProcessingRegister;
use Phil\PrivacyBundle\Application\SubjectErasure;
use Phil\PrivacyBundle\Application\SubjectExport;
use Phil\PrivacyBundle\Infrastructure\Console\EraseSubjectCommand;
use Phil\PrivacyBundle\Infrastructure\Console\ExportSubjectCommand;
use Phil\PrivacyBundle\Infrastructure\Console\ProcessingRegisterCommand;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\AnonymizationServices;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\DatabaseServicesPass;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\PrivacyServicesPass;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\RightsConfiguration;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\RightsServices;
use Phil\PrivacyBundle\Infrastructure\Http\PrivacyPages;
use Phil\PrivacyBundle\Infrastructure\Monolog\RedactingProcessor;
use Phil\PrivacyBundle\Infrastructure\Routing\GuestLinkOrigin;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Request;

/**
 * Privacy law in code. The bundle wires the log redaction from the application's configuration:
 *
 * ```yaml
 * phil_privacy:
 *     log_redaction:
 *         denylist: [password, token, secret, authorization, cookie]
 *         token_path_prefixes: ['/p/']
 *         short_options: { p: password }
 * ```
 *
 * It autoconfigures the export and erasure ports, `PersonalDataExporter` and `PersonalDataEraser`, with
 * {@see self::EXPORTER_TAG} and {@see self::ERASER_TAG}, and registers `SubjectExport` and `SubjectErasure` over
 * them, `ProcessingRegister`, and the commands `phil:privacy:export`, `phil:privacy:erase` and
 * `phil:privacy:register`: which modules take part is decided by which adapters the application registers.
 *
 * It also applies the law on its own: a retention schedule read from the configuration or a named source, which also
 * declares the register of processing, the account deletion workflow, the consent to cookies with their inventory and
 * the third-party frames held until it, a member's export and erasure from their preferences, which are on until the
 * sentence of `PrivacyRights` is written in the configuration, the requests of people without an account, and the
 * register of confidentiality incidents.
 *
 * @phpstan-import-type Config from RightsServices as RightsConfig
 */
final class PhilPrivacyBundle extends AbstractBundle
{
    public const string EXPORTER_TAG = RightsServices::EXPORTER_TAG;

    public const string ERASER_TAG = RightsServices::ERASER_TAG;

    public const string ANONYMIZER_TAG = AnonymizationServices::ANONYMIZER_TAG;

    public const string LOCALE_PACK_TAG = AnonymizationServices::LOCALE_PACK_TAG;

    public const string DATABASE_IDENTITY = AnonymizationServices::DATABASE_IDENTITY;

    /** Whether the requests without an account send their links through the bundle's mailer sender, read when the kernel boots. */
    public const string MAILED_LINKS = 'phil_privacy.guest_requests.mailed_links';

    /**
     * The Stimulus controllers, which AssetMapper serves under `@phil/privacy-bundle`.
     */
    public const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    #[Override]
    protected string $extensionAlias = 'phil_privacy';

    public function __construct(
        private readonly string $warningStream = 'php://stderr',
    ) {
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('log_redaction')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('denylist')
                            ->info('Keys whose values never reach a log line, compared without case or separators; an entry that starts or ends with "*", such as "*seed*", is a pattern.')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                        ->arrayNode('token_path_prefixes')
                            ->info('Path prefixes, such as "/p/", whose next segment is a token.')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                        ->arrayNode('short_options')
                            ->info('Short command line options to redact, each letter to the long option name it stands for, such as { p: password }; the value is redacted when the denylist denies that name. Empty, no short option is read: a letter says nothing about itself.')
                            ->useAttributeAsKey('letter')
                            ->normalizeKeys(false)
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('public_origin')
                    ->info('The canonical address of the site, such as https://example.com, that the links sent by email point to; null takes the host of the request, which framework.trusted_hosts must then bound. In the prod environment, the bundle refuses to boot when the requests without an account send their links by email and neither is set.')
                    ->defaultNull()
                ->end()
                ->scalarNode('retention_schedule_source')
                    ->info('Service id of the RetentionScheduleSource the register of processing reads; null, the register names the rules without their periods.')
                    ->defaultNull()
                ->end()
                ->append(RightsConfiguration::retention())
                ->append(RightsConfiguration::accountDeletion())
                ->append(RightsConfiguration::consent())
                ->append(RightsConfiguration::cookies())
                ->append(RightsConfiguration::guestRequests())
                ->append(RightsConfiguration::incidents())
                ->append(RightsConfiguration::emailTracking())
                ->append(RightsConfiguration::rights())
                ->append(RightsConfiguration::pages())
                ->arrayNode('register')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('classes')
                            ->info('Classes whose #[PersonalData] the register reads, beside every Doctrine entity.')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('database')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('connection')
                            ->info('Service id of the Doctrine DBAL connection that is anonymized.')
                            ->defaultValue('database_connection')
                            ->cannotBeEmpty()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('anonymization')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('locale')
                            ->info('The locale pack the anonymizers draw from, unless a column gives a "locale" option.')
                            ->defaultValue('en_US')
                            ->cannotBeEmpty()
                        ->end()
                        ->integerNode('batch_size')
                            ->info('Rows read per query.')
                            ->defaultValue(500)
                            ->min(1)
                        ->end()
                        ->arrayNode('categories')
                            ->info('The anonymizer of each personal data category, for #[PersonalData] fields without #[Anonymize], such as { email: email, person_name: name }.')
                            ->useAttributeAsKey('category')
                            ->normalizeKeys(false)
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                        ->arrayNode('tables')
                            ->info('Columns to anonymize by table, each an anonymizer type or { type, options }; they replace the rules of the attributes.')
                            ->useAttributeAsKey('table')
                            ->normalizeKeys(false)
                            ->arrayPrototype()
                                ->useAttributeAsKey('column')
                                ->normalizeKeys(false)
                                ->arrayPrototype()
                                    ->beforeNormalization()->ifString()->then(static fn (string $type): array => ['type' => $type])->end()
                                    ->children()
                                        ->scalarNode('type')->isRequired()->cannotBeEmpty()->end()
                                        ->arrayNode('options')
                                            ->normalizeKeys(false)
                                            ->variablePrototype()->end()
                                            ->defaultValue([])
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->defaultValue([])
                        ->end()
                        ->arrayNode('guard')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('environments')
                                    ->info('Kernel environments in which anonymization is refused.')
                                    ->scalarPrototype()->end()
                                    ->defaultValue(['prod'])
                                ->end()
                                ->arrayNode('hosts')
                                    ->info('Database hosts on which anonymization is refused, such as the production server.')
                                    ->scalarPrototype()->end()
                                    ->defaultValue([])
                                ->end()
                                ->arrayNode('databases')
                                    ->info('Database names, or SQLite files, on which anonymization is refused.')
                                    ->scalarPrototype()->end()
                                    ->defaultValue([])
                                ->end()
                            ->end()
                        ->end()
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
        $redaction = \is_array($config['log_redaction'] ?? null) ? $config['log_redaction'] : [];
        $denylist = $this->strings($redaction, 'denylist');
        $prefixes = $this->strings($redaction, 'token_path_prefixes');
        $shortOptions = $this->shortOptions($redaction);

        // Built once here so that a bad entry fails the container build rather than the first log line. The
        // domain refuses a malformed key or prefix with its own exception, an `InvalidArgumentException`.
        try {
            RedactingProcessor::fromConfiguration($denylist, $prefixes, $shortOptions);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new InvalidConfigurationException('phil_privacy.log_redaction: '.$invalidArgumentException->getMessage(), 0, $invalidArgumentException);
        }

        $guest = \is_array($config['guest_requests'] ?? null) ? $config['guest_requests'] : [];
        $builder->setParameter(GuestLinkOrigin::OPTION, \is_string($config['public_origin'] ?? null) ? $config['public_origin'] : null);
        $builder->setParameter(self::MAILED_LINKS, true === ($guest['enabled'] ?? false) && null === ($guest['sender'] ?? null));

        $services = $container->services();
        $services
            ->set(RedactingProcessor::class)
            ->factory([RedactingProcessor::class, 'fromConfiguration'])
            ->args([$denylist, $prefixes, $shortOptions])
            ->tag('monolog.processor');

        // Each module's exporter and eraser, found by autoconfiguration; the answers assembled in module order.
        $builder->registerForAutoconfiguration(PersonalDataExporter::class)->addTag(self::EXPORTER_TAG);
        $builder->registerForAutoconfiguration(PersonalDataEraser::class)->addTag(self::ERASER_TAG);
        $services->set(SubjectExport::class)->args([new TaggedIteratorArgument(self::EXPORTER_TAG)])->public();
        $services->set(SubjectErasure::class)->args([new TaggedIteratorArgument(self::ERASER_TAG)])->public();

        /** @phpstan-var RightsConfig $rights the keys RightsConfiguration appends, as the tree normalizes them */
        $rights = $config;
        $services->set(ProcessingRegister::class)
            ->args([RightsServices::load($rights, $services, $builder, AnonymizationServices::connection($config))])
            ->public();
        $this->warnAboutDisabledRights($rights['rights']);

        if (class_exists(Command::class)) {
            $classes = \is_array($config['register'] ?? null) ? $this->strings($config['register'], 'classes', 'register') : [];
            $services->set(ExportSubjectCommand::class)->args([new Reference(SubjectExport::class)])->tag('console.command');
            $services->set(EraseSubjectCommand::class)->args([new Reference(SubjectErasure::class)])->tag('console.command');
            $services->set(ProcessingRegisterCommand::class)
                ->args([new Reference(ProcessingRegister::class), $classes, new Reference('doctrine', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
                ->tag('console.command');
        }

        AnonymizationServices::load($config, $services, $builder);
    }

    /**
     * Refuses to boot, in the `prod` environment, when the requests without an account send their links by email and
     * those links would take the host of the request: no `public_origin` and no `framework.trusted_hosts`. The origin is
     * read here, once its environment variable is known.
     */
    #[Override]
    public function boot(): void
    {
        // Unset until the kernel gives the container, which it does before booting a bundle.
        $container = $this->container ?? null;

        if (!$container instanceof ContainerInterface || !$container->hasParameter(self::MAILED_LINKS) || true !== $container->getParameter(self::MAILED_LINKS)) {
            return;
        }

        $origin = $container->getParameter(GuestLinkOrigin::OPTION);
        $origin = \is_string($origin) && '' !== $origin ? $origin : null;

        $environment = $container->getParameter('kernel.environment');
        GuestLinkOrigin::origin($origin, true === $container->getParameter('kernel.debug'));
        GuestLinkOrigin::assertBound($origin, \is_string($environment) ? $environment : '', Request::getTrustedHosts());
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new DatabaseServicesPass());
        $container->addCompilerPass(new PrivacyServicesPass());
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('framework')) {
            $container->extension('framework', ['csrf_protection' => ['stateless_token_ids' => [PrivacyPages::CSRF_TOKEN_ID]]]);
        }

        // AssetMapperBundle has its own extension since Symfony 8.2; `framework.asset_mapper` is only an alias of it.
        if ($builder->hasExtension('asset_mapper')) {
            $container->extension('asset_mapper', ['paths' => [self::CONTROLLERS_DIR => '@phil/privacy-bundle']]);
        }
    }

    /**
     * Written at every build, on the standard error, where a deployment shows it.
     *
     * @param array{export: array{disable: scalar|null}, erasure: array{disable: scalar|null}} $rights
     */
    private function warnAboutDisabledRights(array $rights): void
    {
        $warnings = new PrivacyRights(null === $rights['export']['disable'], null === $rights['erasure']['disable'])->warnings();

        if ([] === $warnings) {
            return;
        }

        $border = str_repeat('!', 100);
        file_put_contents($this->warningStream, "\n".$border."\n!! DANGER: ".implode("\n!! DANGER: ", $warnings)."\n".$border."\n\n");
    }

    /**
     * @param array<array-key, mixed> $config
     *
     * @return array<array-key, string>
     */
    private function shortOptions(array $config): array
    {
        $values = $config['short_options'] ?? [];

        if (!\is_array($values)) {
            throw new InvalidConfigurationException('phil_privacy.log_redaction.short_options must map letters to long option names.');
        }

        $table = [];

        foreach ($values as $letter => $name) {
            if (!\is_string($name)) {
                throw new InvalidConfigurationException('phil_privacy.log_redaction.short_options must map letters to long option names.');
            }

            $table[$letter] = $name;
        }

        return $table;
    }

    /**
     * @param array<array-key, mixed> $config
     *
     * @return list<string>
     */
    private function strings(array $config, string $key, string $section = 'log_redaction'): array
    {
        $values = $config[$key] ?? [];

        if (!\is_array($values)) {
            throw new InvalidConfigurationException(\sprintf('phil_privacy.%s.%s must be a list of strings.', $section, $key));
        }

        $strings = [];

        foreach ($values as $value) {
            if (!\is_string($value)) {
                throw new InvalidConfigurationException(\sprintf('phil_privacy.%s.%s must be a list of strings.', $section, $key));
            }

            $strings[] = $value;
        }

        return $strings;
    }
}
