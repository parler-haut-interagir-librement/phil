<?php

declare(strict_types=1);

namespace Phil\LtiBundle;

use Override;
use Phil\Lti\Service\Scope;
use Phil\LtiBundle\Infrastructure\Dbal\LtiTables;
use Phil\LtiBundle\Infrastructure\DependencyInjection\DefaultsPass;
use Phil\LtiBundle\Infrastructure\DependencyInjection\LtiServices;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;

/**
 * `phil/lti` in Symfony: an application that is an LTI 1.3 tool, a platform, or both.
 *
 * ```yaml
 * phil_lti:
 *     keys: { source: dbal, master_key: lti }
 *     tool:
 *         platforms:
 *             - { issuer: 'https://lms.example.org', client_id: '…', authorization_url: '…', token_url: '…', key_set_url: '…', deployments: ['1'] }
 *     platform:
 *         issuer: 'https://courses.example.com'
 *         tools:
 *             - { client_id: '…', login_url: '…', redirect_urls: ['…'], key_set_url: '…', deployments: ['1'] }
 * ```
 *
 * @phpstan-import-type LtiConfig from LtiServices
 */
final class PhilLtiBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_lti';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $root = $definition->rootNode();
        $root
            ->children()
                ->enumNode('store')
                    ->info('Where the nonces, states, tokens, keys, line items and scores live: "dbal", tables of a DBAL connection; "memory", one process, for tests.')
                    ->values([LtiServices::STORE_DBAL, LtiServices::STORE_MEMORY])
                    ->defaultValue(LtiServices::STORE_DBAL)
                ->end()
                ->scalarNode('connection')
                    ->info('The name of the DBAL connection of DoctrineBundle the tables are in.')
                    ->defaultValue('default')
                    ->validate()
                        ->ifTrue(static fn (mixed $name): bool => !\is_string($name) || 1 !== preg_match('/^\w{1,64}$/D', $name))
                        ->thenInvalid('The connection is named by 1 to 64 letters, digits or "_", not %s.')
                    ->end()
                ->end()
                ->scalarNode('table_prefix')
                    ->info('The prefix of the eight table names.')
                    ->defaultValue(LtiTables::DEFAULT_PREFIX)
                    ->validate()
                        ->ifTrue(static fn (mixed $prefix): bool => !\is_string($prefix) || 1 !== preg_match(LtiTables::PREFIX_PATTERN, $prefix))
                        ->thenInvalid('The table prefix is 1 to 40 lowercase letters, digits or "_", not starting with a digit, not %s.')
                    ->end()
                ->end()
                ->scalarNode('clock')->info('The service id of the PSR-20 clock.')->defaultValue('clock')->cannotBeEmpty()->end()
                ->scalarNode('cache')->info('The service id of the cache of the key sets and of the access tokens.')->defaultValue('cache.app')->cannotBeEmpty()->end()
                ->scalarNode('http_client')->info('The service id of the Symfony HTTP client of the services, wrapped against private networks; a client of its own when null.')->defaultNull()->end()
                ->scalarNode('message_bus')->info('The service id of the Messenger bus that sends the scores of the tool; at once, without a bus, when null.')->defaultNull()->end()
                ->booleanNode('allow_localhost')->info('Whether "localhost" and the names under ".localhost" are addresses, for development; never in production.')->defaultFalse()->end()
                ->scalarNode('kit_subdirectory')->info('Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null.')->defaultNull()->end()
                ->arrayNode('keys')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('source')
                            ->info('"dbal": keys made, rotated and sealed by the KMS in a table; "config": one private key in PEM, from the environment.')
                            ->values([LtiServices::KEYS_DBAL, LtiServices::KEYS_CONFIG])
                            ->defaultValue(LtiServices::KEYS_DBAL)
                        ->end()
                        ->scalarNode('kms')->info('The service id of the KMS of Symfony KeyManagement that seals the keys of the table.')->defaultValue(DataKeyGeneratorInterface::class)->end()
                        ->scalarNode('master_key')->info('The master key of the KMS the keys of the table are sealed under.')->defaultNull()->end()
                        ->integerNode('lifetime')->info('The seconds a signing key of the table signs before phil:lti:rotate-keys --if-due replaces it.')->defaultValue(7_776_000)->min(86_400)->end()
                        ->integerNode('overlap')->info('The seconds a retired key stays published.')->defaultValue(604_800)->min(3_600)->end()
                        ->integerNode('bits')->info('The bits of a new key.')->defaultValue(2048)->min(2048)->max(4096)->end()
                        ->scalarNode('private_key')->info('The private key in PEM, from the environment, when the source is "config".')->defaultNull()->end()
                        ->scalarNode('passphrase')->info('The passphrase of the private key, when it has one.')->defaultNull()->end()
                        ->scalarNode('key_id')->info('The identifier of the private key in the key set; its RFC 7638 thumbprint when null.')->defaultNull()->end()
                        ->arrayNode('retired_public_keys')->info('The public keys in PEM of earlier rotations, still published.')->scalarPrototype()->end()->end()
                    ->end()
                ->end()
                ->arrayNode('tool')
                    ->canBeEnabled()
                    ->children()
                        ->integerNode('state_lifetime')->info('The seconds between the login initiation and the launch.')->defaultValue(600)->min(60)->max(3600)->end()
                        ->integerNode('refused_score_retention')->info('The seconds a score the platform refused for good is kept, as a trace, before phil:lti:purge removes it.')->defaultValue(2_592_000)->min(86_400)->end()
                        ->append($this->platforms())
                    ->end()
                ->end()
                ->arrayNode('platform')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('issuer')->info('The issuer of the platform, the address the tools register it by.')->defaultNull()->end()
                        ->scalarNode('token_url')->info('The address of the token endpoint, as the tools register it: the audience of their client assertions.')->defaultNull()->end()
                        ->integerNode('token_lifetime')->info('The seconds an access token lives.')->defaultValue(3600)->min(60)->max(86_400)->end()
                        ->integerNode('deep_linking_retention')->info('The seconds a verified deep linking response is kept, to be handled again when its handling failed.')->defaultValue(86_400)->min(600)->max(2_592_000)->end()
                        ->append($this->tools())
                    ->end()
                ->end()
            ->end()
            ->validate()
                ->ifTrue(static fn (array $config): bool => true === self::option($config, 'platform', 'enabled') && !\is_string(self::option($config, 'platform', 'issuer')))
                ->thenInvalid('The platform side needs its "issuer".')
            ->end()
            ->validate()
                ->ifTrue(static fn (array $config): bool => true === self::option($config, 'platform', 'enabled') && !\is_string(self::option($config, 'platform', 'token_url')))
                ->thenInvalid('The platform side needs the "token_url" its tools register.')
            ->end()
            ->validate()
                ->ifTrue(static fn (array $config): bool => LtiServices::KEYS_CONFIG === self::option($config, 'keys', 'source') && !\is_string(self::option($config, 'keys', 'private_key')))
                ->thenInvalid('Keys of the configuration need their "private_key".')
            ->end()
            ->validate()
                ->ifTrue(static fn (array $config): bool => LtiServices::KEYS_DBAL === self::option($config, 'keys', 'source') && LtiServices::STORE_DBAL === ($config['store'] ?? null) && !\is_string(self::option($config, 'keys', 'master_key')))
                ->thenInvalid('Keys kept in the table are sealed under a "master_key" of the KMS.')
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('framework') && interface_exists(AssetMapperInterface::class)) {
            $container->extension('framework', ['asset_mapper' => ['paths' => [\dirname(__DIR__).'/assets/src' => '@phil/lti-bundle']]]);
        }
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new DefaultsPass());
    }

    /**
     * @param array<array-key, mixed> $config the configuration, processed by the tree above
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var LtiConfig $config */
        LtiServices::register($builder, $config);
    }

    /**
     * A value of a section of the configuration, before it is normalised.
     *
     * @param array<array-key, mixed> $config
     */
    private static function option(array $config, string $section, string $key): mixed
    {
        $values = $config[$section] ?? null;

        return \is_array($values) ? $values[$key] ?? null : null;
    }

    private function platforms(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('platforms');
        $node
            ->info('The platforms the tool is registered with.')
            ->arrayPrototype()
                ->children()
                    ->scalarNode('issuer')->isRequired()->cannotBeEmpty()->end()
                    ->scalarNode('client_id')->isRequired()->cannotBeEmpty()->end()
                    ->scalarNode('authorization_url')->isRequired()->cannotBeEmpty()->end()
                    ->scalarNode('token_url')->isRequired()->cannotBeEmpty()->end()
                    ->scalarNode('key_set_url')->isRequired()->cannotBeEmpty()->end()
                    ->scalarNode('token_audience')->defaultNull()->end()
                    ->arrayNode('deployments')->scalarPrototype()->end()->end()
                ->end()
            ->end();

        return $node;
    }

    private function tools(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('tools');
        $node
            ->info('The tools registered with the platform.')
            ->arrayPrototype()
                ->children()
                    ->scalarNode('client_id')->isRequired()->cannotBeEmpty()->end()
                    ->scalarNode('name')->defaultNull()->end()
                    ->scalarNode('login_url')->isRequired()->cannotBeEmpty()->end()
                    ->arrayNode('redirect_urls')->isRequired()->requiresAtLeastOneElement()->scalarPrototype()->end()->end()
                    ->scalarNode('key_set_url')->isRequired()->cannotBeEmpty()->end()
                    ->scalarNode('deep_linking_url')->defaultNull()->end()
                    ->arrayNode('scopes')
                        ->scalarPrototype()
                            ->validate()
                                ->ifNotInArray(Scope::ALL)
                                ->thenInvalid('%s is not a scope of LTI Advantage.')
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('deployments')->scalarPrototype()->end()->end()
                ->end()
            ->end();

        return $node;
    }
}
