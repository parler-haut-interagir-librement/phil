<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle;

use InvalidArgumentException;
use Override;
use Phil\CircuitBreakerBundle\PhilCircuitBreakerBundle;
use Phil\FeatureFlagsBundle\Application\Admin\ActorProvider;
use Phil\FeatureFlagsBundle\Application\Circuit\FlagCircuitOverride;
use Phil\FeatureFlagsBundle\Application\Edit\FlagEditor;
use Phil\FeatureFlagsBundle\Application\Flag\AnonymousContextResolver;
use Phil\FeatureFlagsBundle\Application\Flag\EvaluationContextResolver;
use Phil\FeatureFlagsBundle\Application\Flag\FlagCatalog;
use Phil\FeatureFlagsBundle\Application\Flag\FlagEvaluator;
use Phil\FeatureFlagsBundle\Application\Toggle\FlagToggler;
use Phil\FeatureFlagsBundle\Application\Toggle\ToggleAuditor;
use Phil\FeatureFlagsBundle\Application\Toggle\ToggleAuthorizer;
use Phil\FeatureFlagsBundle\Infrastructure\Configuration\ConfiguredFlagProvider;
use Phil\FeatureFlagsBundle\Infrastructure\Console\ListFlagsCommand;
use Phil\FeatureFlagsBundle\Infrastructure\DependencyInjection\ForceOpenFlagsPass;
use Phil\FeatureFlagsBundle\Infrastructure\Doctrine\DoctrineFlagDefinitions;
use Phil\FeatureFlagsBundle\Infrastructure\Doctrine\DoctrineFlagStore;
use Phil\FeatureFlagsBundle\Infrastructure\Doctrine\FlagSchemaListener;
use Phil\FeatureFlagsBundle\Infrastructure\Http\FeatureGateListener;
use Phil\FeatureFlagsBundle\Infrastructure\Http\FlagAdminController;
use Phil\FeatureFlagsBundle\Infrastructure\Profiler\FeatureFlagsDataCollector;
use Phil\FeatureFlagsBundle\Infrastructure\Routing\FlagRoutingCondition;
use Phil\FeatureFlagsBundle\Infrastructure\Twig\FeatureFlagsExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ReferenceConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Feature flags and A/B tests, wired from the application's configuration:
 *
 * ```yaml
 * phil_feature_flags:
 *     context_resolver: App\Flags\RequestContextResolver
 *     doctrine: true
 *     toggle:
 *         authorizer: App\Flags\PermissionAuthorizer
 *         auditor: App\Flags\AuditTrailAuditor
 *     admin:
 *         actor_provider: App\Flags\OperatorProvider
 *     flags:
 *         new-checkout: { active: true, rules: [{ attribute: tenant, percentage: 20 }], owner: checkout-team }
 * ```
 *
 * The bundle wires what the design decides: the evaluator, reset between requests and between messages; the
 * providers, the configuration first and the database store after it; the catalog of the current
 * configuration; the audited toggle and editor; the admin screens; the controller attribute, the route
 * condition and the Twig functions; and the override that forces a circuit of `phil/circuit-breaker-bundle` open
 * while its flag, `circuit-open.<key>`, is on, each declared circuit declaring that flag. The adapters stay the
 * application's: the context of a request, the authorizer and auditor of a change, the operator of the admin, the
 * recorder of A/B exposures.
 */
final class PhilFeatureFlagsBundle extends AbstractBundle
{
    public const string CONFIGURATION_PROVIDER = 'phil_feature_flags.provider.configuration';

    public const string DOCTRINE_PROVIDER = 'phil_feature_flags.provider.doctrine';

    public const string DOCTRINE_STATE_STORE = 'phil_feature_flags.state_store.doctrine';

    public const string ANONYMOUS_CONTEXT_RESOLVER = 'phil_feature_flags.context_resolver.anonymous';

    public const string DEFAULT_LAYOUT = '@PhilFeatureFlags/admin/layout.html.twig';

    #[Override]
    protected string $extensionAlias = 'phil_feature_flags';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ForceOpenFlagsPass(self::CONFIGURATION_PROVIDER));
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('context_resolver')
                    ->info(\sprintf('Service id of the resolver of the evaluation context of a request. "%s" gives every request the same empty context, for flags that are on or off for everyone.', self::ANONYMOUS_CONTEXT_RESOLVER))
                    ->defaultValue(EvaluationContextResolver::class)
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('doctrine')
                    ->info(\sprintf('The database store: the flags created from the admin ("%s", after the configuration) and the active states ("%s").', self::DOCTRINE_PROVIDER, self::DOCTRINE_STATE_STORE))
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('connection')->info('The DBAL connection of the two tables.')->defaultValue('default')->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->arrayNode('providers')
                    ->info('Service ids of the flag providers, in order: the first one that knows a flag gives its definition. Defaults to the configuration, then the database store when enabled.')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->scalarNode('state_store')
                    ->info('Service id of the store of active states, read over the state each provider declares; a writable store when the toggle is enabled. Defaults to the database store when enabled.')
                    ->defaultNull()
                ->end()
                ->scalarNode('definition_store')
                    ->info('Service id of the store the admin creates and edits flags in. Defaults to the database store when enabled.')
                    ->defaultNull()
                ->end()
                ->scalarNode('exposure_recorder')
                    ->info('Service id of the recorder of A/B exposures: told the flag and the variant, never the context.')
                    ->defaultNull()
                ->end()
                ->arrayNode('toggle')
                    ->info('The audited toggle and edit of a flag; it needs a state store.')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('authorizer')->info('Service id of the authorizer of a change.')->defaultValue(ToggleAuthorizer::class)->cannotBeEmpty()->end()
                        ->scalarNode('auditor')->info('Service id of the auditor of a change.')->defaultValue(ToggleAuditor::class)->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->arrayNode('admin')
                    ->info('The admin screens, mounted from the routes of the bundle behind the firewall; they need the toggle.')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('actor_provider')->info('Service id of the provider of the operator using the admin.')->defaultValue(ActorProvider::class)->cannotBeEmpty()->end()
                        ->scalarNode('layout')->info('The Twig layout the admin templates extend; it defines the "title" and "content" blocks.')->defaultValue(self::DEFAULT_LAYOUT)->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->scalarNode('clock')
                    ->info('Service id of the PSR-20 clock of the changes and the expiry dates.')
                    ->defaultValue('clock')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('flags')
                    ->info('The flags of the configuration provider, by name; "name: true" is short for an active flag without rules.')
                    ->useAttributeAsKey('name')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->beforeNormalization()
                            ->ifTrue(static fn (mixed $flag): bool => \is_bool($flag))
                            ->then(static fn (bool $active): array => ['active' => $active])
                        ->end()
                        ->children()
                            ->booleanNode('active')->defaultFalse()->end()
                            ->arrayNode('rules')
                                ->info('Active with rules, the flag is on when any rule matches; without rules, it is on for every context.')
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('attribute')->info('tenant, project or subject')->isRequired()->end()
                                        ->arrayNode('members')->scalarPrototype()->end()->end()
                                        ->integerNode('percentage')->defaultNull()->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->variableNode('experiment')
                                ->info('An A/B test among the contexts the flag is on for: the attribute it splits on, and the weight of each variant.')
                                ->defaultNull()
                            ->end()
                            ->scalarNode('description')->defaultNull()->end()
                            ->scalarNode('owner')->defaultNull()->end()
                            ->scalarNode('kind')->info('release, experiment, ops or permission')->defaultNull()->end()
                            ->scalarNode('expires_on')
                                ->info('The day the flag should be gone, YYYY-MM-DD.')
                                ->defaultNull()
                                // An unquoted YAML date is read as a timestamp.
                                ->beforeNormalization()
                                    ->ifTrue(static fn (mixed $date): bool => \is_int($date))
                                    ->then(static fn (int $timestamp): string => gmdate('Y-m-d', $timestamp))
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
        $flags = $this->map($config, 'flags');

        // Built once here so that a bad flag fails the container build rather than the first evaluation. The domain
        // refuses it with its own exception, an `InvalidArgumentException`.
        try {
            ConfiguredFlagProvider::fromConfiguration($flags);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new InvalidConfigurationException('phil_feature_flags.'.$invalidArgumentException->getMessage(), 0, $invalidArgumentException);
        }

        $doctrine = $this->section($config, 'doctrine');
        $doctrineEnabled = $this->enabled($doctrine);
        $clock = service($this->id($config, 'clock'));
        $services = $container->services();

        $services->set(self::CONFIGURATION_PROVIDER, ConfiguredFlagProvider::class)
            ->factory([ConfiguredFlagProvider::class, 'fromConfiguration'])
            ->args([$flags, []]);

        $services->set(self::ANONYMOUS_CONTEXT_RESOLVER, AnonymousContextResolver::class);

        if ($doctrineEnabled) {
            $connection = $this->id($doctrine, 'connection', 'doctrine.');

            // Read once per request and kept: `kernel.reset` ends the request.
            $services->set(self::DOCTRINE_PROVIDER, DoctrineFlagDefinitions::class)
                ->args([service(\sprintf('doctrine.dbal.%s_connection', $connection)), $clock])
                ->tag('kernel.reset', ['method' => 'reset']);

            $services->set(self::DOCTRINE_STATE_STORE, DoctrineFlagStore::class)
                ->args([service(\sprintf('doctrine.dbal.%s_connection', $connection)), $clock])
                ->tag('kernel.reset', ['method' => 'reset']);

            $services->set(FlagSchemaListener::class)
                ->args([service(\sprintf('doctrine.dbal.%s_connection', $connection))])
                ->tag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $connection]);
        }

        $providers = $this->ids($config, 'providers');

        if ([] === $providers) {
            $providers = $doctrineEnabled ? [self::CONFIGURATION_PROVIDER, self::DOCTRINE_PROVIDER] : [self::CONFIGURATION_PROVIDER];
        }

        $stateStore = $this->optionalId($config, 'state_store') ?? ($doctrineEnabled ? self::DOCTRINE_STATE_STORE : null);
        $definitionStore = $this->optionalId($config, 'definition_store') ?? ($doctrineEnabled ? self::DOCTRINE_PROVIDER : null);
        $exposures = $this->optionalId($config, 'exposure_recorder');
        $states = null === $stateStore ? null : service($stateStore);

        // `reset()` ends the request: a worker that serves several requests, or consumes several messages,
        // must not carry one request's memoized flags into the next.
        $services->set(FlagEvaluator::class)
            ->args([
                array_map(service(...), $providers),
                service($this->id($config, 'context_resolver')),
                $states,
                null === $exposures ? null : service($exposures),
                service('logger')->nullOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'feature_flags'])
            ->tag('kernel.reset', ['method' => 'reset']);

        $services->set(FlagCatalog::class)
            ->args([array_combine($providers, array_map(service(...), $providers)), $states]);

        $services->set(ListFlagsCommand::class)
            ->args([service(FlagCatalog::class), $clock])
            ->tag('console.command');

        $services->set(FeatureGateListener::class)
            ->args([service(FlagEvaluator::class)])
            ->tag('kernel.event_listener', ['event' => 'kernel.controller', 'method' => 'onKernelController']);

        $services->set(FlagRoutingCondition::class)
            ->args([service(FlagEvaluator::class)])
            ->tag('routing.condition_service', ['alias' => 'feature_flags']);

        $services->set(FeatureFlagsExtension::class)
            ->args([service(FlagEvaluator::class)])
            ->tag('twig.attribute_extension')
            ->tag('twig.runtime');

        $this->loadChanges($config, $container, $stateStore, $definitionStore, $clock);
        // A circuit of phil/circuit-breaker-bundle is forced open while its flag is on; the keys of the circuits reach
        // the configuration provider through `ForceOpenFlagsPass`.
        $services->set(FlagCircuitOverride::class)
            ->args([service(FlagEvaluator::class)])
            ->tag(PhilCircuitBreakerBundle::OVERRIDE_TAG);

        // The profiler shows the flags a request evaluated; without WebProfilerBundle nothing collects, and the
        // service is removed.
        $services->set(FeatureFlagsDataCollector::class)
            ->args([service(FlagEvaluator::class)])
            ->tag('data_collector', ['template' => FeatureFlagsDataCollector::TEMPLATE, 'id' => 'phil_feature_flags']);
    }

    /**
     * The toggle, the editor and the admin screens.
     *
     * @param array<array-key, mixed> $config
     */
    private function loadChanges(array $config, ContainerConfigurator $container, ?string $stateStore, ?string $definitionStore, ReferenceConfigurator $clock): void
    {
        $toggle = $this->section($config, 'toggle');
        $admin = $this->section($config, 'admin');
        $services = $container->services();

        if (!$this->enabled($toggle)) {
            if ($this->enabled($admin)) {
                throw new InvalidConfigurationException('phil_feature_flags.admin needs the toggle: every change it makes is authorized and audited there.');
            }

            return;
        }

        if (null === $stateStore) {
            throw new InvalidConfigurationException('phil_feature_flags.toggle needs a state_store: a toggle writes to the store the evaluator reads.');
        }

        $authorizer = service($this->id($toggle, 'authorizer', 'toggle.'));
        $auditor = service($this->id($toggle, 'auditor', 'toggle.'));

        $services->set(FlagToggler::class)
            ->args([$authorizer, service($stateStore), $auditor, $clock, service(FlagCatalog::class)]);

        if (null !== $definitionStore) {
            $services->set(FlagEditor::class)
                ->args([$authorizer, service($definitionStore), service($stateStore), $auditor, $clock, service(FlagCatalog::class)]);
        }

        if (!$this->enabled($admin)) {
            return;
        }

        $services->set(FlagAdminController::class)
            ->args([
                service(FlagCatalog::class),
                service(FlagToggler::class),
                service($this->id($admin, 'actor_provider', 'admin.')),
                service('twig'),
                service('router'),
                $clock,
                $this->id($admin, 'layout', 'admin.'),
                null === $definitionStore ? null : service(FlagEditor::class),
                null === $definitionStore ? null : service($definitionStore),
                $definitionStore,
            ])
            ->public()
            ->tag('controller.service_arguments');
    }

    /**
     * @param array<array-key, mixed> $config
     *
     * @return array<array-key, mixed>
     */
    private function map(array $config, string $key): array
    {
        $value = $config[$key] ?? [];

        if (!\is_array($value)) {
            throw new InvalidConfigurationException(\sprintf('phil_feature_flags.%s must be a map.', $key));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $config
     *
     * @return array<array-key, mixed>
     */
    private function section(array $config, string $key): array
    {
        return \is_array($config[$key] ?? null) ? $config[$key] : [];
    }

    /**
     * Whether a section that `canBeEnabled()` declares is enabled; the processed configuration always holds its
     * `enabled` key.
     *
     * @param array<array-key, mixed> $section
     */
    private function enabled(array $section): bool
    {
        return isset($section['enabled']) && true === $section['enabled'];
    }

    /**
     * A scalar node also accepts an integer or a boolean; a service id is only ever a string.
     *
     * @param array<array-key, mixed> $config
     */
    private function id(array $config, string $key, string $path = ''): string
    {
        $id = $config[$key] ?? null;

        if (!\is_string($id)) {
            throw new InvalidConfigurationException(\sprintf('phil_feature_flags.%s%s must be a service id.', $path, $key));
        }

        return $id;
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private function optionalId(array $config, string $key): ?string
    {
        return null === ($config[$key] ?? null) ? null : $this->id($config, $key);
    }

    /**
     * @param array<array-key, mixed> $config
     *
     * @return list<string>
     */
    private function ids(array $config, string $key): array
    {
        $ids = [];

        foreach ($this->map($config, $key) as $id) {
            if (!\is_string($id)) {
                throw new InvalidConfigurationException(\sprintf('phil_feature_flags.%s must be a list of service ids.', $key));
            }

            $ids[] = $id;
        }

        return $ids;
    }
}
