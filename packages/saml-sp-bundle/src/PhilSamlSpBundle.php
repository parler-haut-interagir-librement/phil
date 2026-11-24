<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle;

use Override;
use Phil\SamlSp\Attribute\UserField;
use Phil\SamlSp\Store\DbalReplayGuard;
use Phil\SamlSpBundle\Infrastructure\DependencyInjection\ReplayGuardPass;
use Phil\SamlSpBundle\Infrastructure\DependencyInjection\SamlServices;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * SAML 2.0 single sign-on in a Symfony application: people sign in through the identity provider of their
 * organisation — Microsoft Entra ID, Okta or any other, one per organisation —, by the routes of the bundle and its
 * Symfony Security authenticator; their account is found or created at their first sign-in, and single logout follows
 * Symfony's logout. The accepted assertions are remembered in a table of the Doctrine DBAL connection when there is one,
 * in the cache pool otherwise, which the prod environment refuses when it stays on each server.
 *
 * ```yaml
 * phil_saml_sp:
 *     signing: { certificate: '%env(file:SAML_SP_CERTIFICATE)%', private_key: '%env(file:SAML_SP_PRIVATE_KEY)%' }
 *     identity_providers:
 *         acme: { metadata_url: 'https://login.microsoftonline.com/…/federationmetadata.xml?appid=…', preset: entra_id }
 * ```
 *
 * @phpstan-import-type Config from SamlServices
 */
final class PhilSamlSpBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_saml_sp';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->validate()
                ->ifTrue(static fn (mixed $config): bool => \is_array($config) && true === $config['sign_requests'] && null === ($config['signing'] ?? null) && true === $config['enabled'])
                ->thenInvalid('A service provider that signs its requests has its signing key pair: set "signing", or "sign_requests: false".')
            ->end()
            ->children()
                ->booleanNode('enabled')->info("Registers the bundle's services.")->defaultTrue()->end()
                ->scalarNode('entity_id')->info('The entity ID of the service provider; the address of the metadata of each identity provider by default.')->defaultNull()->end()
                ->append($this->keyPair('signing', 'The key pair the requests and the logout messages are signed with, in PEM.'))
                ->arrayNode('encryption')
                    ->info('The key pairs the encrypted assertions are decrypted with, in PEM: the current one first, the previous one during a rollover.')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('certificate')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('private_key')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('passphrase')->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('name_id_format')->info('The name identifier format the requests ask for, such as urn:oasis:names:tc:SAML:2.0:nameid-format:persistent; none by default.')->defaultNull()->end()
                ->booleanNode('sign_requests')->info('Whether the requests are signed.')->defaultTrue()->end()
                ->integerNode('clock_skew')->info("The seconds the clocks of the identity providers may differ from the application's.")->min(0)->max(600)->defaultValue(120)->end()
                ->integerNode('max_age')->info('The seconds a response may be old, past its issue instant.')->min(30)->max(86_400)->defaultValue(600)->end()
                ->booleanNode('allow_sha1')->info('Whether signatures with SHA-1 are accepted; refused by default.')->defaultFalse()->end()
                ->integerNode('min_rsa_bits')->info('The smallest RSA key of a signature.')->min(1024)->max(16384)->defaultValue(2048)->end()
                ->booleanNode('allow_localhost')->info('Whether addresses on localhost are accepted, in development.')->defaultFalse()->end()
                ->booleanNode('bind_to_browser')->info('Whether a sign-in is bound by a cookie to the browser that started it.')->defaultTrue()->end()
                ->booleanNode('secure_cookie')->info('Whether the cookie of a sign-in is Secure and SameSite=None, as a response posted by the identity provider needs over HTTPS.')->defaultTrue()->end()
                ->scalarNode('default_target_path')->info('Where a person goes once signed in, when the sign-in names no path.')->cannotBeEmpty()->defaultValue('/')->end()
                ->scalarNode('logout_target_path')->info('Where a person goes once signed out of the identity provider.')->cannotBeEmpty()->defaultValue('/')->end()
                ->scalarNode('cache_pool')->info('The cache pool of the pending requests, the metadata and, without a database, the accepted assertions; shared by every server, not cleared on deploy.')->cannotBeEmpty()->defaultValue('cache.app')->end()
                ->arrayNode('replay_guard')
                    ->info('Where the accepted assertions are remembered, so that one posted again is refused: a table of a Doctrine DBAL connection when it exists, atomic on any number of servers; the cache pool otherwise.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('store')->info('"auto" takes the connection when it exists and the cache pool otherwise; "doctrine" requires the connection; "cache" takes the cache pool.')->values(['auto', 'doctrine', 'cache'])->defaultValue('auto')->end()
                        ->scalarNode('connection')->info('The name of the Doctrine DBAL connection.')->cannotBeEmpty()->defaultValue('default')->end()
                        ->scalarNode('table')
                            ->info('The table of the accepted assertions, which a migration of the application creates.')
                            ->defaultValue(DbalReplayGuard::TABLE)
                            ->validate()
                                ->ifTrue(static fn (mixed $table): bool => !\is_string($table) || 1 !== preg_match('/^[a-z_][a-z0-9_]{0,62}$/D', $table))
                                ->thenInvalid('The table of the accepted assertions is up to 63 lowercase letters, digits or "_", not starting with a digit.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->booleanNode('single_server')->info('Whether the application runs on a single server, where a cache pool local to the server may remember the accepted assertions in the prod environment.')->defaultFalse()->end()
                ->integerNode('metadata_lifetime')->info('The seconds fetched metadata is kept.')->min(60)->max(2_592_000)->defaultValue(86_400)->end()
                ->integerNode('request_lifetime')->info('The seconds a request waits for its response.')->min(60)->max(3600)->defaultValue(600)->end()
                ->scalarNode('clock')->info('The PSR-20 clock of the time checks.')->cannotBeEmpty()->defaultValue('clock')->end()
                ->scalarNode('http_client')->info('The HTTP client metadata is fetched with, wrapped by phil/safe-http; its own by default.')->defaultNull()->end()
                ->scalarNode('accounts')->info('A SamlAccounts service that finds and creates the accounts of the application; a SamlUser in the session by default.')->defaultNull()->end()
                ->append($this->identityProviders())
            ->end();
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new ReplayGuardPass());
    }

    /**
     * @param array<array-key, mixed> $config the configuration, processed by the tree above
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (true !== $config['enabled']) {
            return;
        }

        /** @var Config $config */
        SamlServices::register($builder, $config);
    }

    private function keyPair(string $name, string $info): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition($name);
        $node
            ->info($info)
            ->canBeUnset()
            ->children()
                ->scalarNode('certificate')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('private_key')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('passphrase')->defaultNull()->end()
            ->end();

        return $node;
    }

    private function identityProviders(): ArrayNodeDefinition
    {
        $fields = array_map(static fn (UserField $field): string => $field->value, UserField::cases());
        $node = new ArrayNodeDefinition('identity_providers');
        $node
            ->info('The identity providers, one per organisation, by a key of lowercase letters, digits, "_" and "-" that their routes carry.')
            ->normalizeKeys(false)
            ->useAttributeAsKey('key')
            ->validate()
                ->ifTrue(static fn (mixed $providers): bool => \is_array($providers) && [] !== array_filter(array_keys($providers), static fn (int|string $key): bool => 1 !== preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', (string) $key)))
                ->thenInvalid('The key of an identity provider is 1 to 64 lowercase letters, digits, "_" or "-", starting with a letter or a digit.')
            ->end()
            ->arrayPrototype()
                ->validate()
                    ->ifTrue(static fn (mixed $provider): bool => \is_array($provider) && 1 !== \count(array_filter([$provider['metadata_url'] ?? null, $provider['metadata_file'] ?? null, $provider['metadata_xml'] ?? null], static fn (mixed $source): bool => null !== $source)))
                    ->thenInvalid('An identity provider has its metadata from one of "metadata_url", "metadata_file" or "metadata_xml".')
                ->end()
                ->validate()
                    ->ifTrue(static fn (array $provider): bool => \is_array($provider['attributes'] ?? null) && [] !== array_diff(array_keys($provider['attributes']), $fields))
                    ->thenInvalid('The attributes of an identity provider are mapped to the fields '.implode(', ', $fields).'.')
                ->end()
                ->children()
                    ->scalarNode('metadata_url')->info('The HTTPS address of the metadata, fetched and cached.')->defaultNull()->end()
                    ->scalarNode('metadata_file')->info('A file holding the metadata.')->defaultNull()->end()
                    ->scalarNode('metadata_xml')->info('The metadata itself.')->defaultNull()->end()
                    ->arrayNode('metadata_certificates')->info('The PEM certificates the metadata must be signed with; read unsigned when empty.')->scalarPrototype()->end()->end()
                    ->scalarNode('entity_id')->info('The entity ID of the identity provider, required in metadata describing several.')->defaultNull()->end()
                    ->enumNode('preset')->info('The claim names of a known identity provider.')->values(SamlServices::PRESETS)->defaultValue('none')->end()
                    ->arrayNode('attributes')
                        ->info('For each field — '.implode(', ', $fields).' — the attribute names to read, in order, over the preset.')
                        ->useAttributeAsKey('field')
                        ->arrayPrototype()->scalarPrototype()->end()->end()
                    ->end()
                    ->arrayNode('group_roles')->info('A role of the application for each group or role the identity provider sends.')->normalizeKeys(false)->useAttributeAsKey('group')->scalarPrototype()->end()->end()
                    ->arrayNode('default_roles')->info('The roles of every person of this identity provider.')->scalarPrototype()->end()->defaultValue(['ROLE_USER'])->end()
                    ->booleanNode('want_assertions_signed')->info('Whether the assertion itself must be signed; false accepts a signed response around an unsigned assertion.')->defaultTrue()->end()
                    ->booleanNode('want_assertions_encrypted')->info('Whether the assertions must be encrypted.')->defaultFalse()->end()
                    ->booleanNode('allow_unsolicited')->info('Whether a response the identity provider sends unasked, from its portal, is accepted.')->defaultFalse()->end()
                    ->enumNode('binding')->info('The binding the requests are sent on.')->values(['redirect', 'post'])->defaultValue('redirect')->end()
                    ->booleanNode('single_logout')->info('Whether single logout is carried to and from this identity provider.')->defaultTrue()->end()
                    ->booleanNode('create_accounts')->info('Whether an account is created at the first sign-in of a person.')->defaultTrue()->end()
                    ->booleanNode('force_authn')->info('Whether the person authenticates again at each sign-in.')->defaultFalse()->end()
                ->end()
            ->end();

        return $node;
    }
}
