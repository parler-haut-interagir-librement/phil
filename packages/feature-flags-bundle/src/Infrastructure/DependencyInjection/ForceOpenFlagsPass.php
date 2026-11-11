<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\CircuitBreakerBundle\PhilCircuitBreakerBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Declares the force-open flag of each circuit of `phil/circuit-breaker-bundle` in the configuration provider, once
 * every bundle has loaded its configuration: the keys are that bundle's parameter, already validated by it. Without
 * that bundle, no circuit is declared and no flag is added.
 */
final readonly class ForceOpenFlagsPass implements CompilerPassInterface
{
    /**
     * @param string $provider the service id of the configuration provider
     */
    public function __construct(
        private string $provider,
    ) {
    }

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(PhilCircuitBreakerBundle::CIRCUIT_KEYS) || !$container->hasDefinition($this->provider)) {
            return;
        }

        $keys = $container->getParameter(PhilCircuitBreakerBundle::CIRCUIT_KEYS);

        $container->getDefinition($this->provider)
            ->setArgument(1, \is_array($keys) ? array_values(array_filter($keys, is_string(...))) : []);
    }
}
