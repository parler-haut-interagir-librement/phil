<?php

declare(strict_types=1);

namespace Phil\FormsAutosave;

use Override;
use Phil\FormsAutosave\Application\Drafts;
use Phil\FormsAutosave\Infrastructure\DependencyInjection\AutosaveServices;
use Phil\FormsAutosave\Infrastructure\DependencyInjection\ServerStorePass;
use Phil\FormsAutosave\Infrastructure\Http\DraftRequest;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * A form's input kept while it is filled: the option `autosave` of every form, the Stimulus controller that keeps the
 * draft in the browser, and, when the configuration turns it on, a store of drafts on the server for signed-in users,
 * sealed through Symfony's KeyManagement component, with its endpoints and purge command. The draft table is mapped for Doctrine when
 * the server store is on, the controller's CSRF token is added to the stateless ones of FrameworkBundle, and with
 * AssetMapper the controller and its stylesheet are served without a build step.
 */
final class PhilFormsAutosaveBundle extends AbstractBundle
{
    public const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    public const string STYLES_DIR = __DIR__.'/../assets/styles';

    /** How long the controller waits after the last change before it saves, by default, in milliseconds. */
    public const int DEBOUNCE = 800;

    /** How large a draft the controller keeps in the browser, by default: 256 KiB. */
    public const int MAX_BYTES = 262144;

    #[Override]
    protected string $extensionAlias = 'phil_forms_autosave';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('secret')
                    ->info('The secret the keys of drafts and the hashes of their owners are derived with.')
                    ->cannotBeEmpty()
                    ->defaultValue('%kernel.secret%')
                ->end()
                ->integerNode('ttl')
                    ->info('How long a draft is kept after its last change, in seconds, in the browser and on the server.')
                    ->min(60)
                    ->max(31536000)
                    ->defaultValue(Drafts::SECONDS)
                ->end()
                ->integerNode('debounce')
                    ->info('How long the controller waits after the last change before it saves, in milliseconds.')
                    ->min(100)
                    ->max(10000)
                    ->defaultValue(self::DEBOUNCE)
                ->end()
                ->integerNode('max_bytes')
                    ->info('How large a draft the controller keeps in the browser, in bytes.')
                    ->min(1024)
                    ->max(4194304)
                    ->defaultValue(self::MAX_BYTES)
                ->end()
                ->arrayNode('server')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Whether drafts are also kept on the server, for signed-in users.')
                            ->defaultFalse()
                        ->end()
                        ->enumNode('store')
                            ->info('Where the server keeps drafts: "doctrine", or "memory" for tests.')
                            ->values(['doctrine', 'memory'])
                            ->defaultValue('doctrine')
                        ->end()
                        ->scalarNode('connection')
                            ->info('The Doctrine connection of the draft table.')
                            ->cannotBeEmpty()
                            ->defaultValue('default')
                        ->end()
                        ->booleanNode('encrypt')
                            ->info("Whether drafts are sealed through Symfony's KeyManagement component, under the master key \"key\" names; false stores them in clear.")
                            ->defaultTrue()
                        ->end()
                        ->scalarNode('key')
                            ->info('The master key of the KMS that seals drafts, required while encryption is on.')
                            ->defaultNull()
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('kms')
                            ->info('The service of a client of the KeyManagement component, such as "key_management.<client>"; null for its default client.')
                            ->defaultNull()
                            ->cannotBeEmpty()
                        ->end()
                        ->integerNode('max_bytes')
                            ->info('How large a draft the server keeps, in bytes.')
                            ->min(1024)
                            ->max(4194304)
                            ->defaultValue(Drafts::MAX_BYTES)
                        ->end()
                        ->scalarNode('owner')
                            ->info('The service, a DraftOwner, that names the signed-in user; by default, the user of Symfony Security.')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine') && $this->serverEnabled($builder->getExtensionConfig('phil_forms_autosave'))) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['PhilFormsAutosave' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Phil\FormsAutosave\Domain\Model',
            ]]]]);
        }

        if (!$builder->hasExtension('framework')) {
            return;
        }

        $container->extension('framework', ['csrf_protection' => ['stateless_token_ids' => [DraftRequest::TOKEN_ID]]]);

        if (interface_exists(AssetMapperInterface::class)) {
            $container->extension('framework', ['asset_mapper' => ['paths' => [
                self::CONTROLLERS_DIR => '@phil/forms-autosave',
                self::STYLES_DIR => 'phil-forms-autosave',
            ]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{secret: string, ttl: int, debounce: int, max_bytes: int, server: array{enabled: bool, store: string, connection: string, encrypt: bool, key: ?string, kms: ?string, max_bytes: int, owner: ?string}} $config */
        AutosaveServices::register($builder, $config);
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ServerStorePass());
    }

    /**
     * Whether the configurations turn the server store on, the last one that says so deciding.
     *
     * @param list<mixed> $configs
     */
    private function serverEnabled(array $configs): bool
    {
        $enabled = false;

        foreach ($configs as $config) {
            $server = \is_array($config) ? $config['server'] ?? null : null;

            if (\is_array($server) && \array_key_exists('enabled', $server)) {
                $enabled = true === $server['enabled'] || 'true' === $server['enabled'];
            }
        }

        return $enabled;
    }
}
