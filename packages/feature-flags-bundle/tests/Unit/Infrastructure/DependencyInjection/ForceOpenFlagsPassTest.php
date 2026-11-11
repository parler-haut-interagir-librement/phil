<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Infrastructure\DependencyInjection;

use Phil\CircuitBreakerBundle\PhilCircuitBreakerBundle;
use Phil\FeatureFlagsBundle\Infrastructure\Configuration\ConfiguredFlagProvider;
use Phil\FeatureFlagsBundle\Infrastructure\DependencyInjection\ForceOpenFlagsPass;
use Phil\FeatureFlagsBundle\PhilFeatureFlagsBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(ForceOpenFlagsPass::class)]
final class ForceOpenFlagsPassTest extends TestCase
{
    public function testTheKeysOfTheCircuitsReachTheConfigurationProvider(): void
    {
        $container = $this->container(['http.payments', 'slack-webhook']);

        new ForceOpenFlagsPass(PhilFeatureFlagsBundle::CONFIGURATION_PROVIDER)->process($container);

        self::assertSame(['http.payments', 'slack-webhook'], $container->getDefinition(PhilFeatureFlagsBundle::CONFIGURATION_PROVIDER)->getArgument(1));
    }

    public function testWithoutTheCircuitBreakerBundleNoFlagIsAdded(): void
    {
        $container = new ContainerBuilder();
        $container->register(PhilFeatureFlagsBundle::CONFIGURATION_PROVIDER, ConfiguredFlagProvider::class)->setArguments([[], []]);

        new ForceOpenFlagsPass(PhilFeatureFlagsBundle::CONFIGURATION_PROVIDER)->process($container);

        self::assertSame([], $container->getDefinition(PhilFeatureFlagsBundle::CONFIGURATION_PROVIDER)->getArgument(1));
    }

    public function testWithoutTheConfigurationProviderNothingIsChanged(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(PhilCircuitBreakerBundle::CIRCUIT_KEYS, ['http.payments']);

        new ForceOpenFlagsPass(PhilFeatureFlagsBundle::CONFIGURATION_PROVIDER)->process($container);

        self::assertFalse($container->hasDefinition(PhilFeatureFlagsBundle::CONFIGURATION_PROVIDER));
    }

    /**
     * The parameter is another bundle's: only its strings are keys, and anything but a list reads as no circuit.
     */
    public function testOnlyTheStringsOfAListAreKeys(): void
    {
        $container = $this->container(['http.payments', 42, 'slack-webhook']);
        new ForceOpenFlagsPass(PhilFeatureFlagsBundle::CONFIGURATION_PROVIDER)->process($container);
        self::assertSame(['http.payments', 'slack-webhook'], $container->getDefinition(PhilFeatureFlagsBundle::CONFIGURATION_PROVIDER)->getArgument(1));

        $container = $this->container('http.payments');
        new ForceOpenFlagsPass(PhilFeatureFlagsBundle::CONFIGURATION_PROVIDER)->process($container);
        self::assertSame([], $container->getDefinition(PhilFeatureFlagsBundle::CONFIGURATION_PROVIDER)->getArgument(1));
    }

    /**
     * @param list<int|string>|string $keys
     */
    private function container(array|string $keys): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(PhilCircuitBreakerBundle::CIRCUIT_KEYS, $keys);
        $container->register(PhilFeatureFlagsBundle::CONFIGURATION_PROVIDER, ConfiguredFlagProvider::class)->setArguments([[], []]);

        return $container;
    }
}
