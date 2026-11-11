<?php

declare(strict_types=1);

namespace Phil\EditLockBundle;

use Override;
use Phil\EditLockBundle\Application\EditLocks;
use Phil\EditLockBundle\Infrastructure\DependencyInjection\CsrfProtectionPass;
use Phil\EditLockBundle\Infrastructure\DependencyInjection\EditLockServices;
use Phil\EditLockBundle\Infrastructure\Http\EditLockRequest;
use Phil\EditLockBundle\Infrastructure\Mercure\MercureBroadcaster;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Mercure\HubInterface;

/**
 * Edit leases for an application: the configuration sets their duration, where they are kept, and the services of the
 * application behind the endpoints — who writes, what they may edit, where saved blocks go. With MercureBundle,
 * presence and block saves are pushed to the other writers. The lease tables are mapped for Doctrine, the controller's
 * CSRF token is added to the stateless ones of FrameworkBundle, and with AssetMapper the controller and its stylesheet
 * are served without a build step.
 */
final class PhilEditLockBundle extends AbstractBundle
{
    public const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    public const string STYLES_DIR = __DIR__.'/../assets/styles';

    #[Override]
    protected string $extensionAlias = 'phil_edit_lock';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->integerNode('seconds')
                    ->info('How long a lease lasts without a heartbeat, in seconds; the controller beats three times as often.')
                    ->min(5)
                    ->max(3600)
                    ->defaultValue(EditLocks::SECONDS)
                ->end()
                ->enumNode('store')
                    ->info('Where leases are kept: "doctrine", or "memory" for tests.')
                    ->values(['doctrine', 'memory'])
                    ->defaultValue('doctrine')
                ->end()
                ->scalarNode('connection')
                    ->info('The Doctrine connection of the lease table.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
                ->scalarNode('holder_provider')
                    ->info('The service, a HolderProvider, that names who writes; the endpoints need it.')
                    ->defaultNull()
                ->end()
                ->scalarNode('policy')
                    ->info('The service, an EditPolicy, that says who may edit and take over; the endpoints need it.')
                    ->defaultNull()
                ->end()
                ->scalarNode('block_store')
                    ->info('The service, a BlockStore, that keeps saved blocks; none only tells them to the other writers.')
                    ->defaultNull()
                ->end()
                ->arrayNode('mercure')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Whether to push presence and block saves; by default, when MercureBundle is registered.')
                            ->defaultFalse()
                        ->end()
                        ->scalarNode('hub')
                            ->info('The service of the hub to publish to.')
                            ->cannotBeEmpty()
                            ->defaultValue(HubInterface::class)
                        ->end()
                        ->scalarNode('topic_prefix')
                            ->info('What comes before the name of a resource in its topic.')
                            ->defaultValue(MercureBroadcaster::PREFIX)
                        ->end()
                        ->booleanNode('private')
                            ->info('Whether updates are private, sent only to subscribers authorized for the topic.')
                            ->defaultTrue()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('mercure')) {
            $container->extension('phil_edit_lock', ['mercure' => ['enabled' => true]]);
        }

        if ($builder->hasExtension('doctrine')) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['PhilEditLockBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Phil\EditLockBundle\Domain\Model',
            ]]]]);
        }

        if ($builder->hasExtension('framework')) {
            $container->extension('framework', ['csrf_protection' => ['stateless_token_ids' => [EditLockRequest::TOKEN_ID]]]);
        }

        if (interface_exists(AssetMapperInterface::class) && $builder->hasExtension('framework')) {
            $container->extension('framework', ['asset_mapper' => ['paths' => [
                self::CONTROLLERS_DIR => '@phil/edit-lock-bundle',
                self::STYLES_DIR => 'phil-edit-lock',
            ]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{seconds: int, store: string, connection: string, holder_provider: ?string, policy: ?string, block_store: ?string, mercure: array{enabled: bool, hub: string, topic_prefix: string, private: bool}} $config */
        EditLockServices::register($builder, $config);
    }

    /**
     * Stops the container with a helpful message when the application turns the CSRF protection off.
     */
    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new CsrfProtectionPass(EditLockRequest::class, EditLockRequest::TOKEN_ID));
    }
}
