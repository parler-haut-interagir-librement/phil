<?php

declare(strict_types=1);

namespace Phil\CircuitBreakerBundle\Tests\Unit;

use Phil\CircuitBreaker\CircuitBreakers;
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\CircuitOverrides;
use Phil\CircuitBreaker\CircuitState;
use Phil\CircuitBreaker\Contract\CircuitOverride;
use Phil\CircuitBreaker\Exception\InvalidCircuit;
use Phil\CircuitBreakerBundle\Infrastructure\Cache\CacheCircuitStateStore;
use Phil\CircuitBreakerBundle\Infrastructure\HttpClient\CircuitBreakingHttpClient;
use Phil\CircuitBreakerBundle\Infrastructure\Profiler\CircuitBreakerDataCollector;
use Phil\CircuitBreakerBundle\PhilCircuitBreakerBundle;
use Phil\CircuitBreakerBundle\Tests\Unit\Fixture\FrozenClock;
use Phil\CircuitBreakerBundle\Tests\Unit\Fixture\InMemoryCircuitStore;
use Phil\CircuitBreakerBundle\Tests\Unit\Fixture\PaymentsForcedOpen;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The wiring the bundle declares: read on the `ContainerBuilder` for what it registers, and on a compiled
 * container, with the application's adapters as synthetic services, for what the services then do.
 */
#[CoversClass(PhilCircuitBreakerBundle::class)]
final class PhilCircuitBreakerBundleTest extends TestCase
{
    private const array POLICY = ['failure_rate_percent' => 50, 'minimum_calls' => 10, 'window_seconds' => 60, 'open_seconds' => 30];

    public function testTheExtensionAliasIsPhilCircuitBreaker(): void
    {
        $extension = new PhilCircuitBreakerBundle()->getContainerExtension();

        self::assertNotNull($extension);
        self::assertSame('phil_circuit_breaker', $extension->getAlias());
    }

    public function testWithoutCircuitsNothingIsWiredButTheEmptyListOfKeys(): void
    {
        $container = $this->load([]);

        self::assertSame([], $container->getParameter(PhilCircuitBreakerBundle::CIRCUIT_KEYS));
        self::assertFalse($container->hasDefinition(CircuitBreakers::class));
        self::assertFalse($container->hasDefinition(CircuitOverrides::class));
        self::assertFalse($container->hasDefinition(CacheCircuitStateStore::class));
        self::assertFalse($container->hasDefinition(CircuitBreakerDataCollector::class));
    }

    public function testTheKeysOfTheCircuitsAreAParameter(): void
    {
        $container = $this->load(['circuits' => ['http.payments' => self::POLICY, '42' => self::POLICY]]);

        self::assertSame(['http.payments', '42'], $container->getParameter(PhilCircuitBreakerBundle::CIRCUIT_KEYS));
    }

    public function testWithoutAStoreTheCircuitsAreKeptInACachePool(): void
    {
        $container = $this->load(['circuits' => ['http.payments' => [...self::POLICY, 'http_client' => 'payments.client'], 'slack-webhook' => self::POLICY]]);

        self::assertEquals([new Reference('cache.app')], $container->getDefinition(CacheCircuitStateStore::class)->getArguments());
        self::assertEquals(
            [new Reference(CacheCircuitStateStore::class), new Reference(CircuitOverrides::class), new Reference('clock')],
            \array_slice($container->getDefinition(CircuitBreakers::class)->getArguments(), 1),
        );
        self::assertEquals([new Reference('app.pool')], $this->load(['cache_pool' => 'app.pool', 'circuits' => ['http.payments' => self::POLICY]])->getDefinition(CacheCircuitStateStore::class)->getArguments());

        $custom = $this->load(['state_store' => 'app.circuits', 'clock' => 'app.clock', 'circuits' => ['http.payments' => self::POLICY]]);
        self::assertFalse($custom->hasDefinition(CacheCircuitStateStore::class));
        self::assertEquals([new Reference('app.circuits'), new Reference(CircuitOverrides::class), new Reference('app.clock')], \array_slice($custom->getDefinition(CircuitBreakers::class)->getArguments(), 1));
    }

    public function testAClientIsPutBehindItsCircuit(): void
    {
        $container = $this->load(['circuits' => ['http.payments' => [...self::POLICY, 'http_client' => 'payments.client'], 'slack-webhook' => self::POLICY]]);

        $client = $container->getDefinition('phil_circuit_breaker.http_client.http.payments');
        self::assertSame([CircuitBreakingHttpClient::class, ['payments.client', null, 0]], [$client->getClass(), $client->getDecoratedService()]);
        self::assertEquals([new Reference('.inner'), new Reference(CircuitBreakers::class), 'http.payments'], $client->getArguments());
        self::assertFalse($container->hasDefinition('phil_circuit_breaker.http_client.slack-webhook'));
        self::assertSame('42', $this->load(['circuits' => ['42' => [...self::POLICY, 'http_client' => 'numbers.client']]])->getDefinition('phil_circuit_breaker.http_client.42')->getArgument(2));
    }

    public function testTheOverridesAreTheTaggedServicesAndTheInterfaceIsAutoconfigured(): void
    {
        $container = $this->load(['circuits' => ['http.payments' => self::POLICY]]);

        $argument = $container->getDefinition(CircuitOverrides::class)->getArgument(0);
        self::assertInstanceOf(TaggedIteratorArgument::class, $argument);
        self::assertSame(PhilCircuitBreakerBundle::OVERRIDE_TAG, $argument->getTag());
        self::assertSame('phil_circuit_breaker.override', PhilCircuitBreakerBundle::OVERRIDE_TAG);

        $autoconfigured = $container->getAutoconfiguredInstanceof()[CircuitOverride::class] ?? null;
        self::assertNotNull($autoconfigured);
        self::assertSame([PhilCircuitBreakerBundle::OVERRIDE_TAG => [[]]], $autoconfigured->getTags());
    }

    public function testTheProfilerCollectsTheCircuits(): void
    {
        $collector = $this->load(['circuits' => ['http.payments' => self::POLICY]])->getDefinition(CircuitBreakerDataCollector::class);

        self::assertEquals([new Reference(CircuitBreakers::class)], $collector->getArguments());
        self::assertSame([['template' => '@PhilCircuitBreaker/profiler/collector.html.twig', 'id' => 'phil_circuit_breaker']], $collector->getTag('data_collector'));
    }

    public function testACircuitPolicyDefaultsToOneHalfOpenSuccess(): void
    {
        $circuits = $this->load(['circuits' => ['http.payments' => self::POLICY]])->getDefinition(CircuitBreakers::class)->getArgument(0);

        self::assertIsArray($circuits);
        $policy = $circuits['http.payments'] ?? null;
        self::assertIsArray($policy);
        self::assertSame(1, $policy['half_open_successes'] ?? null);
        self::assertArrayHasKey('slow_call_milliseconds', $policy);
        self::assertNull($policy['slow_call_milliseconds']);
        self::assertSame(100, $policy['slow_call_rate_percent'] ?? null);
    }

    public function testTheSlownessOfACircuitIsConfigured(): void
    {
        $circuits = $this->load(['circuits' => ['http.payments' => ['slow_call_milliseconds' => 2000, 'slow_call_rate_percent' => 50] + self::POLICY]])->getDefinition(CircuitBreakers::class)->getArgument(0);

        self::assertIsArray($circuits);
        $policy = $circuits['http.payments'] ?? null;
        self::assertIsArray($policy);
        self::assertSame([2000, 50], [$policy['slow_call_milliseconds'] ?? null, $policy['slow_call_rate_percent'] ?? null]);
    }

    /**
     * A bad entry fails the container build, not the first call to its third party, and names where it is.
     */
    public function testAMalformedPolicyFailsTheBuild(): void
    {
        try {
            $this->load(['circuits' => ['http.payments' => ['window_seconds' => 0] + self::POLICY]]);
            self::fail('A malformed policy was accepted.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertSame('phil_circuit_breaker.circuits.http.payments: The window of a circuit policy is at least 1; got 0.', $invalidConfigurationException->getMessage());
            self::assertSame(0, $invalidConfigurationException->getCode());
            self::assertInstanceOf(InvalidCircuit::class, $invalidConfigurationException->getPrevious());
        }
    }

    public function testANonStringServiceIdIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_circuit_breaker.state_store must be a service id.');

        $this->load(['state_store' => 5, 'circuits' => ['http.payments' => self::POLICY]]);
    }

    public function testATaggedOverrideForcesItsCircuitOpenInTheCompiledContainer(): void
    {
        $container = $this->load([
            'state_store' => 'app.circuits',
            'clock' => 'app.clock',
            'circuits' => ['http.payments' => self::POLICY, 'slack-webhook' => self::POLICY],
        ]);
        $container->register('app.circuits')->setSynthetic(true)->setPublic(true);
        $container->register('app.clock')->setSynthetic(true)->setPublic(true);
        $container->register('app.override', PaymentsForcedOpen::class)->setAutoconfigured(true);
        $container->setAlias('test.breakers', CircuitBreakers::class)->setPublic(true);
        $container->compile(true);
        $container->set('app.circuits', new InMemoryCircuitStore());
        $container->set('app.clock', new FrozenClock());

        $breakers = $container->get('test.breakers');
        self::assertInstanceOf(CircuitBreakers::class, $breakers);
        self::assertSame(CircuitState::Open, $breakers->get(CircuitKey::of('http.payments'))->state());
        self::assertSame(CircuitState::Closed, $breakers->get(CircuitKey::of('slack-webhook'))->state());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $extension = new PhilCircuitBreakerBundle()->getContainerExtension();
        self::assertNotNull($extension);

        $extension->load([$config], $container);

        return $container;
    }
}
