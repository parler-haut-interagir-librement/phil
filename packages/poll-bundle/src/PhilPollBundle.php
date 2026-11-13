<?php

declare(strict_types=1);

namespace Phil\PollBundle;

use Override;
use Phil\PollBundle\Infrastructure\DependencyInjection\PollServices;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * The voting engine of phil/poll for an application: polls and their ballots kept with Doctrine, anonymous or not,
 * the application's policy asked before every action, its own counting methods beside the library's and the single
 * transferable vote, and the results counted on demand and kept until the next ballot. The tables are mapped for
 * Doctrine.
 */
final class PhilPollBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_poll';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('store')
                    ->info('Where polls and ballots are kept: "doctrine", or "memory" for tests.')
                    ->values(['doctrine', 'memory'])
                    ->defaultValue('doctrine')
                ->end()
                ->scalarNode('entity_manager')
                    ->info('The Doctrine entity manager of the tables.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
                ->scalarNode('policy')
                    ->info('The service, a PollPolicy, that says who may create polls, vote and manage them; by default everyone may vote and the creator manages.')
                    ->defaultNull()
                ->end()
                ->arrayNode('results_cache')
                    ->info('Where counts are kept until the next ballot; none by default, every count reading every ballot.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('pool')
                            ->info('The cache pool, a Symfony\\Contracts\\Cache\\CacheInterface such as "cache.app"; null keeps no count.')
                            ->defaultNull()
                        ->end()
                        ->integerNode('lifetime')
                            ->info('The seconds a count is kept at most.')
                            ->min(1)
                            ->defaultValue(3600)
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine')) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['PhilPollBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Phil\PollBundle\Domain\Model',
            ]]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{store: string, entity_manager: string, policy: ?string, results_cache: array{pool: ?string, lifetime: int}} $config */
        PollServices::register($builder, $config);
    }
}
