<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit;

use Phil\CircuitBreaker\CircuitBreakers;
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreakerBundle\PhilCircuitBreakerBundle;
use Phil\DddBundle\Domain\Exception\InvalidValue;
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
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationContext;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationReason;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Toggle\ToggleReason;
use Phil\FeatureFlagsBundle\Domain\Toggle\ToggleRequest;
use Phil\FeatureFlagsBundle\Infrastructure\Configuration\ConfiguredFlagProvider;
use Phil\FeatureFlagsBundle\Infrastructure\Console\ListFlagsCommand;
use Phil\FeatureFlagsBundle\Infrastructure\Doctrine\DoctrineFlagDefinitions;
use Phil\FeatureFlagsBundle\Infrastructure\Doctrine\DoctrineFlagStore;
use Phil\FeatureFlagsBundle\Infrastructure\Doctrine\FlagSchemaListener;
use Phil\FeatureFlagsBundle\Infrastructure\Http\FeatureGateListener;
use Phil\FeatureFlagsBundle\Infrastructure\Http\FlagAdminController;
use Phil\FeatureFlagsBundle\Infrastructure\Profiler\FeatureFlagsDataCollector;
use Phil\FeatureFlagsBundle\Infrastructure\Routing\FlagRoutingCondition;
use Phil\FeatureFlagsBundle\Infrastructure\Twig\FeatureFlagsExtension;
use Phil\FeatureFlagsBundle\PhilFeatureFlagsBundle;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\CountingResolver;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\FrozenClock;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\InMemoryCircuitStore;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\InMemoryFlagStore;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\ListAuthorizer;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\RecordingAuditor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The wiring the bundle declares: read on the `ContainerBuilder` for what it registers, and on a compiled
 * container, with the application's adapters as synthetic services, for what the services then do.
 */
#[CoversClass(PhilFeatureFlagsBundle::class)]
final class PhilFeatureFlagsBundleTest extends TestCase
{
    public function testTheExtensionAliasIsPhilFeatureFlags(): void
    {
        $extension = new PhilFeatureFlagsBundle()->getContainerExtension();

        self::assertNotNull($extension);
        self::assertSame('phil_feature_flags', $extension->getAlias());
    }

    public function testTheDefaultsWireTheEvaluatorOnTheConfigurationProviderOnly(): void
    {
        $container = $this->load([]);
        $arguments = $container->getDefinition(FlagEvaluator::class)->getArguments();

        self::assertEquals([[new Reference(PhilFeatureFlagsBundle::CONFIGURATION_PROVIDER)], new Reference(EvaluationContextResolver::class), null, null, new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $arguments);
        self::assertSame([['channel' => 'feature_flags']], $container->getDefinition(FlagEvaluator::class)->getTag('monolog.logger'));
        self::assertSame(ConfiguredFlagProvider::class, $container->getDefinition(PhilFeatureFlagsBundle::CONFIGURATION_PROVIDER)->getClass());
        self::assertSame(AnonymousContextResolver::class, $container->getDefinition(PhilFeatureFlagsBundle::ANONYMOUS_CONTEXT_RESOLVER)->getClass());
        self::assertFalse($container->hasDefinition(FlagToggler::class));
        self::assertEquals([new Reference(FlagEvaluator::class)], $container->getDefinition(FlagCircuitOverride::class)->getArguments());
        self::assertSame([[]], $container->getDefinition(FlagCircuitOverride::class)->getTag(PhilCircuitBreakerBundle::OVERRIDE_TAG));
        self::assertSame([], $container->getDefinition(PhilFeatureFlagsBundle::CONFIGURATION_PROVIDER)->getArgument(1), 'the circuits come from the compiler pass');
    }

    public function testTheEvaluatorIsResetBetweenRequests(): void
    {
        $definition = $this->load([])->getDefinition(FlagEvaluator::class);

        self::assertSame([['method' => 'reset']], $definition->getTag('kernel.reset'));
    }

    public function testTheProvidersKeepTheirConfiguredOrder(): void
    {
        $arguments = $this->load(['providers' => ['app.remote', PhilFeatureFlagsBundle::CONFIGURATION_PROVIDER]])
            ->getDefinition(FlagEvaluator::class)
            ->getArguments();

        self::assertEquals([new Reference('app.remote'), new Reference(PhilFeatureFlagsBundle::CONFIGURATION_PROVIDER)], $arguments[0]);
        self::assertIsArray($arguments[0]);
        self::assertContainsOnlyInstancesOf(Reference::class, $arguments[0]);
    }

    public function testConfiguredFlagsAreEvaluatedAgainstTheResolvedContext(): void
    {
        $container = $this->compile(
            [
                'context_resolver' => 'app.resolver',
                'flags' => [
                    'new-checkout' => ['active' => true, 'rules' => [['attribute' => 'tenant', 'members' => ['tenant-a']]]],
                    'beta.search' => true,
                    'dark_mode' => ['active' => true, 'rules' => [['attribute' => 'tenant', 'percentage' => 100]]],
                    'parked' => ['active' => false],
                ],
            ],
            ['app.resolver' => new CountingResolver(EvaluationContext::of(tenant: 'tenant-a'))],
        );
        $evaluator = $this->service($container, FlagEvaluator::class);

        self::assertSame(EvaluationReason::TargetingMatch, $evaluator->evaluate(FlagName::of('new-checkout'))->reason);
        self::assertSame(EvaluationReason::Static, $evaluator->evaluate(FlagName::of('beta.search'))->reason);
        self::assertSame(EvaluationReason::Split, $evaluator->evaluate(FlagName::of('dark_mode'))->reason);
        self::assertSame(EvaluationReason::Disabled, $evaluator->evaluate(FlagName::of('parked'))->reason);
        self::assertFalse($evaluator->isEnabled(FlagName::of('undeclared')));
    }

    public function testTheAnonymousResolverLeavesTargetedFlagsOff(): void
    {
        $container = $this->compile([
            'context_resolver' => PhilFeatureFlagsBundle::ANONYMOUS_CONTEXT_RESOLVER,
            'flags' => [
                'everyone' => true,
                'some-tenants' => ['active' => true, 'rules' => [['attribute' => 'tenant', 'percentage' => 100]]],
            ],
        ]);
        $evaluator = $this->service($container, FlagEvaluator::class);

        self::assertTrue($evaluator->isEnabled(FlagName::of('everyone')));
        self::assertFalse($evaluator->isEnabled(FlagName::of('some-tenants')));
    }

    public function testTheStateStoreOverridesTheConfiguredState(): void
    {
        $container = $this->compile(
            ['context_resolver' => PhilFeatureFlagsBundle::ANONYMOUS_CONTEXT_RESOLVER, 'state_store' => 'app.store', 'flags' => ['new-checkout' => true]],
            ['app.store' => new InMemoryFlagStore(['new-checkout' => false])],
        );
        $evaluator = $this->service($container, FlagEvaluator::class);

        self::assertSame(EvaluationReason::Disabled, $evaluator->evaluate(FlagName::of('new-checkout'))->reason);
    }

    public function testTheToggleWritesToTheStateStoreAndAudits(): void
    {
        $store = new InMemoryFlagStore(['new-checkout' => false]);
        $auditor = new RecordingAuditor();
        $container = $this->compile(
            [
                'context_resolver' => PhilFeatureFlagsBundle::ANONYMOUS_CONTEXT_RESOLVER,
                'state_store' => 'app.store',
                'toggle' => ['authorizer' => 'app.authorizer', 'auditor' => 'app.auditor'],
                'clock' => 'app.clock',
                'flags' => ['new-checkout' => true],
            ],
            ['app.store' => $store, 'app.authorizer' => new ListAuthorizer(['operator']), 'app.auditor' => $auditor, 'app.clock' => new FrozenClock()]
        );
        $toggler = $this->service($container, FlagToggler::class);

        self::assertTrue($toggler->toggle(new ToggleRequest(FlagName::of('new-checkout'), true, 'operator', ToggleReason::of('Opening to tenants.'))));
        self::assertSame(['new-checkout' => true], $store->flags);
        self::assertCount(1, $auditor->records);
    }

    public function testTheToggleNeedsAStateStore(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_feature_flags.toggle needs a state_store');

        $this->load(['toggle' => true]);
    }

    public function testTheToggleDefaultsToTheAdapterInterfaces(): void
    {
        $arguments = $this->load(['state_store' => 'app.store', 'toggle' => true])->getDefinition(FlagToggler::class)->getArguments();

        self::assertEquals(
            [
                new Reference(ToggleAuthorizer::class),
                new Reference('app.store'),
                new Reference(ToggleAuditor::class),
                new Reference('clock'),
                new Reference(FlagCatalog::class),
            ],
            $arguments,
        );
    }

    public function testADeclaredCircuitIsForcedOpenByItsFlagFromTheStateStore(): void
    {
        $container = $this->compileWithCircuits(
            [
                'context_resolver' => PhilFeatureFlagsBundle::ANONYMOUS_CONTEXT_RESOLVER,
                'state_store' => 'app.store',
            ],
            ['http.payments', 'slack-webhook'],
            ['app.store' => new InMemoryFlagStore(['circuit-open.http.payments' => true])],
        );
        $circuits = $this->service($container, CircuitBreakers::class);

        self::assertFalse($circuits->get(CircuitKey::of('http.payments'))->isAvailable());
        self::assertTrue($circuits->get(CircuitKey::of('slack-webhook'))->isAvailable());
    }

    public function testADeclaredCircuitDeclaresItsForceOpenFlagInactive(): void
    {
        $container = $this->compileWithCircuits(['context_resolver' => PhilFeatureFlagsBundle::ANONYMOUS_CONTEXT_RESOLVER], ['http.payments']);
        $evaluator = $this->service($container, FlagEvaluator::class);

        self::assertSame(EvaluationReason::Disabled, $evaluator->evaluate(FlagName::of('circuit-open.http.payments'))->reason);
    }

    public function testTheProfilerCollectsTheEvaluatedFlags(): void
    {
        $collector = $this->load([])->getDefinition(FeatureFlagsDataCollector::class);

        self::assertEquals([new Reference(FlagEvaluator::class)], $collector->getArguments());
        self::assertSame([['template' => '@PhilFeatureFlags/profiler/collector.html.twig', 'id' => 'phil_feature_flags']], $collector->getTag('data_collector'));
    }

    /**
     * A bad entry fails the container build, not the first evaluation, and names where it is.
     */
    public function testAMalformedRuleFailsTheBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_feature_flags.flags.new-checkout: A percentage is between 0 and 100; got 120.');

        $this->load(['flags' => ['new-checkout' => ['active' => true, 'rules' => [['attribute' => 'tenant', 'percentage' => 120]]]]]);
    }

    public function testANonStringServiceIdIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_feature_flags.state_store must be a service id.');

        $this->load(['state_store' => 5]);
    }

    public function testANonStringToggleServiceIdIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_feature_flags.toggle.auditor must be a service id.');

        $this->load(['state_store' => 'app.store', 'toggle' => ['auditor' => 5]]);
    }

    public function testANonStringProviderIdIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_feature_flags.providers must be a list of service ids.');

        $this->load(['providers' => [5]]);
    }

    public function testDoctrineWiresTheDatabaseStoreAfterTheConfiguration(): void
    {
        $container = $this->load(['doctrine' => ['connection' => 'flags'], 'toggle' => true]);
        $connection = new Reference('doctrine.dbal.flags_connection');

        $evaluator = $container->getDefinition(FlagEvaluator::class)->getArguments();
        self::assertEquals([new Reference(PhilFeatureFlagsBundle::CONFIGURATION_PROVIDER), new Reference(PhilFeatureFlagsBundle::DOCTRINE_PROVIDER)], $evaluator[0]);
        self::assertEquals(new Reference(PhilFeatureFlagsBundle::DOCTRINE_STATE_STORE), $evaluator[2]);

        foreach ([PhilFeatureFlagsBundle::DOCTRINE_PROVIDER => DoctrineFlagDefinitions::class, PhilFeatureFlagsBundle::DOCTRINE_STATE_STORE => DoctrineFlagStore::class] as $id => $class) {
            $definition = $container->getDefinition($id);
            self::assertSame($class, $definition->getClass());
            self::assertEquals([$connection, new Reference('clock')], $definition->getArguments());
            self::assertSame([['method' => 'reset']], $definition->getTag('kernel.reset'));
        }

        $listener = $container->getDefinition(FlagSchemaListener::class);
        self::assertEquals([$connection], $listener->getArguments());
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'flags']], $listener->getTag('doctrine.event_listener'));

        self::assertEquals(new Reference(PhilFeatureFlagsBundle::DOCTRINE_STATE_STORE), $container->getDefinition(FlagToggler::class)->getArgument(1));

        $editor = $container->getDefinition(FlagEditor::class)->getArguments();
        self::assertEquals(new Reference(PhilFeatureFlagsBundle::DOCTRINE_PROVIDER), $editor[1]);
        self::assertEquals(new Reference(PhilFeatureFlagsBundle::DOCTRINE_STATE_STORE), $editor[2]);
        self::assertEquals(new Reference(FlagCatalog::class), $editor[5]);
    }

    public function testWithoutDoctrineThereIsNoDatabaseStoreAndNoEditor(): void
    {
        $container = $this->load(['state_store' => 'app.store', 'toggle' => true]);

        self::assertFalse($container->hasDefinition(PhilFeatureFlagsBundle::DOCTRINE_PROVIDER));
        self::assertFalse($container->hasDefinition(PhilFeatureFlagsBundle::DOCTRINE_STATE_STORE));
        self::assertFalse($container->hasDefinition(FlagSchemaListener::class));
        self::assertFalse($container->hasDefinition(FlagEditor::class));
        self::assertTrue($container->hasDefinition(FlagToggler::class));
    }

    public function testExplicitStoresWinOverTheDatabaseDefaults(): void
    {
        $container = $this->load(['doctrine' => true, 'state_store' => 'app.store', 'definition_store' => 'app.definitions', 'toggle' => true]);

        self::assertEquals(new Reference('app.store'), $container->getDefinition(FlagEvaluator::class)->getArgument(2));
        self::assertEquals(new Reference('app.definitions'), $container->getDefinition(FlagEditor::class)->getArgument(1));
    }

    public function testTheCatalogReadsTheProvidersByServiceIdAndTheStateStore(): void
    {
        $container = $this->load(['providers' => [PhilFeatureFlagsBundle::CONFIGURATION_PROVIDER, 'app.remote'], 'state_store' => 'app.store', 'exposure_recorder' => 'app.exposures']);

        self::assertEquals(
            [[PhilFeatureFlagsBundle::CONFIGURATION_PROVIDER => new Reference(PhilFeatureFlagsBundle::CONFIGURATION_PROVIDER), 'app.remote' => new Reference('app.remote')], new Reference('app.store')],
            $container->getDefinition(FlagCatalog::class)->getArguments(),
        );
        $providers = $container->getDefinition(FlagCatalog::class)->getArgument(0);
        self::assertIsArray($providers);
        self::assertContainsOnlyInstancesOf(Reference::class, $providers);
        self::assertEquals(new Reference('app.exposures'), $container->getDefinition(FlagEvaluator::class)->getArgument(3));
    }

    public function testThePlacementServicesAreTagged(): void
    {
        $container = $this->load([]);
        $evaluator = [new Reference(FlagEvaluator::class)];

        $command = $container->getDefinition(ListFlagsCommand::class);
        self::assertTrue($command->hasTag('console.command'));
        self::assertEquals([new Reference(FlagCatalog::class), new Reference('clock')], $command->getArguments());

        $gate = $container->getDefinition(FeatureGateListener::class);
        self::assertSame([['event' => 'kernel.controller', 'method' => 'onKernelController']], $gate->getTag('kernel.event_listener'));
        self::assertEquals($evaluator, $gate->getArguments());

        $condition = $container->getDefinition(FlagRoutingCondition::class);
        self::assertSame([['alias' => 'feature_flags']], $condition->getTag('routing.condition_service'));
        self::assertEquals($evaluator, $condition->getArguments());

        $twig = $container->getDefinition(FeatureFlagsExtension::class);
        self::assertTrue($twig->hasTag('twig.attribute_extension'));
        self::assertTrue($twig->hasTag('twig.runtime'));
        self::assertEquals($evaluator, $twig->getArguments());
    }

    public function testTheAdminNeedsTheToggle(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_feature_flags.admin needs the toggle');

        $this->load(['state_store' => 'app.store', 'admin' => true]);
    }

    public function testTheAdminControllerIsWiredWithoutAnEditorWhenNoDefinitionStore(): void
    {
        $container = $this->load(['state_store' => 'app.store', 'toggle' => true, 'admin' => ['actor_provider' => 'app.actor', 'layout' => 'admin.html.twig']]);
        $controller = $container->getDefinition(FlagAdminController::class);

        self::assertTrue($controller->isPublic());
        self::assertTrue($controller->hasTag('controller.service_arguments'));
        self::assertEquals(
            [
                new Reference(FlagCatalog::class),
                new Reference(FlagToggler::class),
                new Reference('app.actor'),
                new Reference('twig'),
                new Reference('router'),
                new Reference('clock'),
                'admin.html.twig',
                null,
                null,
                null,
            ],
            $controller->getArguments(),
        );
    }

    public function testTheAdminControllerEditsTheDefinitionStore(): void
    {
        $arguments = $this->load(['doctrine' => true, 'toggle' => true, 'admin' => true])->getDefinition(FlagAdminController::class)->getArguments();

        self::assertEquals(new Reference(ActorProvider::class), $arguments[2]);
        self::assertSame(PhilFeatureFlagsBundle::DEFAULT_LAYOUT, $arguments[6]);
        self::assertEquals(new Reference(FlagEditor::class), $arguments[7]);
        self::assertEquals(new Reference(PhilFeatureFlagsBundle::DOCTRINE_PROVIDER), $arguments[8]);
        self::assertSame(PhilFeatureFlagsBundle::DOCTRINE_PROVIDER, $arguments[9]);
    }

    public function testWithoutTheAdminThereIsNoController(): void
    {
        self::assertFalse($this->load(['state_store' => 'app.store', 'toggle' => true])->hasDefinition(FlagAdminController::class));
    }

    public function testABuildErrorKeepsTheDomainRefusalAsItsCause(): void
    {
        try {
            $this->load(['flags' => ['new-checkout' => ['active' => true, 'rules' => [['attribute' => 'tenant', 'percentage' => 120]]]]]);
            self::fail('A malformed rule must fail the build.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertSame(0, $invalidConfigurationException->getCode());
            self::assertInstanceOf(InvalidValue::class, $invalidConfigurationException->getPrevious());
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $extension = new PhilFeatureFlagsBundle()->getContainerExtension();
        self::assertNotNull($extension);

        $extension->load([$config], $container);

        return $container;
    }

    /**
     * @param array<string, mixed>  $config
     * @param array<string, object> $adapters the application's adapters, by service id
     */
    private function compile(array $config, array $adapters = []): ContainerBuilder
    {
        return $this->compiled($this->load($config), $adapters);
    }

    /**
     * @param array<string, object> $adapters the application's adapters, by service id
     */
    private function compiled(ContainerBuilder $container, array $adapters): ContainerBuilder
    {
        foreach (array_keys($adapters) as $id) {
            $container->register($id)->setSynthetic(true)->setPublic(true);
        }

        foreach ([FlagEvaluator::class, FlagToggler::class, CircuitBreakers::class] as $id) {
            if ($container->hasDefinition($id)) {
                $container->setAlias('test.'.$id, $id)->setPublic(true);
            }
        }

        $container->compile(true);

        foreach ($adapters as $id => $adapter) {
            $container->set($id, $adapter);
        }

        return $container;
    }

    /**
     * Both bundles in one container, as an application registers them: the circuits of phil/circuit-breaker-bundle,
     * each with the same policy, their states in memory and the clock frozen.
     *
     * @param array<string, mixed>  $config   the configuration of this bundle
     * @param list<string>          $circuits the keys of the circuits
     * @param array<string, object> $adapters the application's adapters, by service id
     */
    private function compileWithCircuits(array $config, array $circuits, array $adapters = []): ContainerBuilder
    {
        $policy = ['failure_rate_percent' => 50, 'minimum_calls' => 10, 'window_seconds' => 60, 'open_seconds' => 30];
        $container = $this->load($config);
        new PhilFeatureFlagsBundle()->build($container);
        $extension = new PhilCircuitBreakerBundle()->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load([['state_store' => 'app.circuits', 'clock' => 'app.clock', 'circuits' => array_fill_keys($circuits, $policy)]], $container);

        return $this->compiled($container, [...$adapters, 'app.circuits' => new InMemoryCircuitStore(), 'app.clock' => new FrozenClock()]);
    }

    /**
     * A service of the bundle, through the public alias `compile()` gives it.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(ContainerBuilder $container, string $class): object
    {
        $service = $container->get('test.'.$class);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
