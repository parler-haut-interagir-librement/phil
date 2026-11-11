<?php

declare(strict_types=1);

namespace Phil\CircuitBreakerBundle;

use InvalidArgumentException;
use Override;
use Phil\CircuitBreaker\CircuitBreakers;
use Phil\CircuitBreaker\CircuitOverrides;
use Phil\CircuitBreaker\Contract\CircuitOverride;
use Phil\CircuitBreakerBundle\Infrastructure\Cache\CacheCircuitStateStore;
use Phil\CircuitBreakerBundle\Infrastructure\Configuration\ConfiguredCircuitBreakers;
use Phil\CircuitBreakerBundle\Infrastructure\HttpClient\CircuitBreakingHttpClient;
use Phil\CircuitBreakerBundle\Infrastructure\Profiler\CircuitBreakerDataCollector;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * `phil/circuit-breaker` in a Symfony application, from its configuration:
 *
 * ```yaml
 * phil_circuit_breaker:
 *     cache_pool: cache.app            # or state_store: App\Circuits\CircuitStore
 *     circuits:
 *         http.payments:
 *             failure_rate_percent: 50
 *             minimum_calls: 10
 *             window_seconds: 60
 *             open_seconds: 30
 *             slow_call_milliseconds: 2000   # optional: slow calls count too
 *             slow_call_rate_percent: 50
 *             http_client: payments.client
 * ```
 *
 * The bundle wires one breaker per declared circuit in `CircuitBreakers`, the states in a PSR-6 pool shared by the
 * workers or in the application's store, the overrides tagged `OVERRIDE_TAG` asked together, the HTTP clients put
 * behind their circuits, and the profiler panel. The keys of the declared circuits are the parameter `CIRCUIT_KEYS`,
 * for a bundle that derives something per circuit, such as a flag that forces it open.
 */
final class PhilCircuitBreakerBundle extends AbstractBundle
{
    /**
     * The tag of the services implementing `CircuitOverride`: a circuit is forced open as soon as one of them forces
     * it. A service of the application implementing the interface is tagged by autoconfiguration.
     */
    public const string OVERRIDE_TAG = 'phil_circuit_breaker.override';

    /**
     * The parameter that holds the keys of the declared circuits, a list of strings, empty without circuits.
     */
    public const string CIRCUIT_KEYS = 'phil_circuit_breaker.circuit_keys';

    public const string HTTP_CLIENT_PREFIX = 'phil_circuit_breaker.http_client.';

    #[Override]
    protected string $extensionAlias = 'phil_circuit_breaker';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('clock')
                    ->info('Service id of the PSR-20 clock of the windows and open durations.')
                    ->defaultValue('clock')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('state_store')
                    ->info('Service id of the store of circuit states; null keeps them in the cache pool cache_pool.')
                    ->defaultNull()
                ->end()
                ->scalarNode('cache_pool')
                    ->info('Service id of the PSR-6 cache pool the circuit states are kept in, when no state_store is given.')
                    ->defaultValue('cache.app')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('circuits')
                    ->info('One circuit breaker per key, each with its policy.')
                    ->useAttributeAsKey('key')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->integerNode('failure_rate_percent')->isRequired()->end()
                            ->integerNode('minimum_calls')->isRequired()->end()
                            ->integerNode('window_seconds')->isRequired()->end()
                            ->integerNode('open_seconds')->isRequired()->end()
                            ->integerNode('half_open_successes')->defaultValue(1)->end()
                            ->integerNode('slow_call_milliseconds')
                                ->info('A call that lasts longer is slow; null counts no call as slow.')
                                ->defaultNull()
                            ->end()
                            ->integerNode('slow_call_rate_percent')
                                ->info('The share of slow calls in a window that opens the circuit, 1 to 100.')
                                ->defaultValue(100)
                            ->end()
                            ->scalarNode('http_client')
                                ->info('Service id of the HTTP client to put behind this circuit: "payments.client".')
                                ->defaultNull()
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
        $circuits = \is_array($config['circuits'] ?? null) ? $config['circuits'] : [];

        // Read once here so that a bad key or policy fails the container build rather than the first call to its
        // third party. The library refuses it with its own exception, an `InvalidArgumentException`.
        try {
            ConfiguredCircuitBreakers::validate($circuits);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new InvalidConfigurationException('phil_circuit_breaker.'.$invalidArgumentException->getMessage(), 0, $invalidArgumentException);
        }

        $builder->setParameter(self::CIRCUIT_KEYS, array_map(strval(...), array_keys($circuits)));
        $builder->registerForAutoconfiguration(CircuitOverride::class)->addTag(self::OVERRIDE_TAG);

        if ([] === $circuits) {
            return;
        }

        $services = $container->services();
        $store = $this->optionalId($config, 'state_store');

        if (null === $store) {
            $store = CacheCircuitStateStore::class;
            $services->set(CacheCircuitStateStore::class)->args([service($this->id($config, 'cache_pool'))]);
        }

        $services->set(CircuitOverrides::class)
            ->args([tagged_iterator(self::OVERRIDE_TAG)]);

        $services->set(CircuitBreakers::class)
            ->factory(ConfiguredCircuitBreakers::fromConfiguration(...))
            ->args([$circuits, service($store), service(CircuitOverrides::class), service($this->id($config, 'clock'))]);

        foreach ($circuits as $key => $circuit) {
            $client = \is_array($circuit) ? ($circuit['http_client'] ?? null) : null;

            if (\is_string($client)) {
                $services->set(self::HTTP_CLIENT_PREFIX.$key, CircuitBreakingHttpClient::class)
                    ->decorate($client)
                    ->args([service('.inner'), service(CircuitBreakers::class), (string) $key]);
            }
        }

        // Without WebProfilerBundle nothing collects, and the service is removed.
        $services->set(CircuitBreakerDataCollector::class)
            ->args([service(CircuitBreakers::class)])
            ->tag('data_collector', ['template' => CircuitBreakerDataCollector::TEMPLATE, 'id' => 'phil_circuit_breaker']);
    }

    /**
     * A scalar node also accepts an integer or a boolean; a service id is only ever a string.
     *
     * @param array<array-key, mixed> $config
     */
    private function id(array $config, string $key): string
    {
        $id = $config[$key] ?? null;

        if (!\is_string($id)) {
            throw new InvalidConfigurationException(\sprintf('phil_circuit_breaker.%s must be a service id.', $key));
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
}
