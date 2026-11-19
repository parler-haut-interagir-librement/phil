<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle;

use Override;
use Phil\ApiKeysBundle\Application\ScopeCatalogue;
use Phil\ApiKeysBundle\Domain\Token\TokenFormat;
use Phil\ApiKeysBundle\Domain\Token\TokenOwner;
use Phil\ApiKeysBundle\Infrastructure\DependencyInjection\ApiKeysServices;
use Phil\ApiKeysBundle\Infrastructure\SecretScanning\GitHub\GitHubPublicKeys;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Access tokens for a public API, for members, organisations or applications, from the configuration:
 *
 * ```yaml
 * phil_api_keys:
 *     prefix: acme
 *     hash_key: '%env(API_KEYS_HASH_KEY)%'
 *     scopes:
 *         projects:read: { description: 'Read the projects', label: 'scope.projects_read' }
 *         projects:write: { description: 'Create and change projects' }
 * ```
 *
 * The bundle wires the token format, the keyed hash, the declared scopes, the store, `ApiTokens`, the token handler
 * and the token extractor that the `access_token` authenticator of a stateless firewall names, the roles of the
 * scopes, the voter and the listener of `#[RequiresScope]`, the commands, and, when enabled, the page where members
 * manage their tokens and the endpoint of GitHub's secret scanning. The table is mapped for Doctrine.
 */
final class PhilApiKeysBundle extends AbstractBundle
{
    public const string TEMPLATE = '@PhilApiKeys/tokens.html.twig';

    #[Override]
    protected string $extensionAlias = 'phil_api_keys';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('prefix')
                    ->info('What every token starts with, before an underscore: 2 to 16 lowercase letters and digits starting with a letter, the name of the application for secret scanners.')
                    ->defaultValue('pat')
                    ->validate()
                        ->ifTrue(static fn (mixed $prefix): bool => !\is_string($prefix) || 1 !== preg_match(TokenFormat::PREFIX_PATTERN, $prefix))
                        ->thenInvalid('The prefix is 2 to 16 lowercase letters and digits, starting with a letter: %s.')
                    ->end()
                ->end()
                ->arrayNode('hash_key')
                    ->info('The keys of the HMAC-SHA-256 each token is kept as, at least 32 bytes each, read from the secrets vault: "%env(API_KEYS_HASH_KEY)%", or a list whose first key hashes new tokens and whose others, the keys it replaced, still verify the tokens issued under them. Dropping a key refuses every token issued under it.')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->beforeNormalization()
                        ->ifString()
                        ->then(static fn (string $key): array => [$key])
                    ->end()
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->arrayNode('scopes')
                    ->info('The scopes a token may hold, by name: what each allows, for developers, and the translation key of its label, for members.')
                    ->useAttributeAsKey('name')
                    ->normalizeKeys(false)
                    ->validate()
                        ->ifTrue(static fn (array $scopes): bool => [] !== array_filter(array_keys($scopes), static fn (int|string $name): bool => 1 !== preg_match(ScopeCatalogue::NAME_PATTERN, (string) $name)))
                        ->thenInvalid('A scope name is a lowercase letter followed by up to 63 lowercase letters, digits, "_", ".", ":" or "-": %s')
                    ->end()
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('description')->info('What the scope allows.')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('label')->info('The translation key of its label; "scope.<name>" without it.')->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('owner_types')
                    ->info('The owner types a token may act for besides "user", the members, by name — "organization", "application"…: the user provider that loads an owner of that type when its token authenticates, and the scopes its tokens may hold, every declared scope when none are listed.')
                    ->useAttributeAsKey('name')
                    ->normalizeKeys(false)
                    ->validate()
                        ->ifTrue(static fn (array $types): bool => [] !== array_filter(array_keys($types), static fn (int|string $name): bool => TokenOwner::USER === $name || 1 !== preg_match(TokenOwner::TYPE_PATTERN, (string) $name)))
                        ->thenInvalid('An owner type is a lowercase letter followed by up to 31 lowercase letters, digits or "_", other than "user", which the firewall\'s provider loads: %s')
                    ->end()
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('provider')->info('Service id of the user provider that loads an owner of this type by its identifier.')->isRequired()->cannotBeEmpty()->end()
                            ->arrayNode('scopes')->info('The scopes its tokens may hold; every declared scope when omitted or empty.')->scalarPrototype()->cannotBeEmpty()->end()->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('scope_translation_domain')
                    ->info('The translation domain of the scope labels.')
                    ->defaultValue('phil_api_keys')
                    ->cannotBeEmpty()
                ->end()
                ->integerNode('default_lifetime')->info('The days a token lasts when its creator gives no expiry.')->defaultValue(90)->min(1)->end()
                ->integerNode('max_lifetime')->info('The most days a token may last, at least default_lifetime; every token expires.')->defaultValue(365)->end()
                ->arrayNode('lifetime_choices')
                    ->info("The lifetimes, in days, the members' page offers; those past max_lifetime are left out.")
                    ->integerPrototype()->min(1)->end()
                    ->defaultValue([7, 30, 90, 365])
                ->end()
                ->integerNode('last_used_throttle')->info('The seconds within which a second use of a token is not written to its row.')->defaultValue(300)->min(0)->end()
                ->scalarNode('last_used_address')
                    ->info('What the row keeps of the address a token was last used from: null for nothing, "truncation" for the address truncated by phil/ip-anonymizer, or the id of a service implementing ClientAddressAnonymizer. Never the address itself.')
                    ->defaultNull()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('store')
                    ->info('Where tokens are kept: "doctrine", "memory" for tests, or the id of a service implementing ApiTokenRepository.')
                    ->defaultValue(ApiKeysServices::STORE_DOCTRINE)
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('entity_manager')->info('The Doctrine entity manager of the table.')->defaultValue('default')->cannotBeEmpty()->end()
                ->scalarNode('clock')->info('Service id of the PSR-20 clock.')->defaultValue('clock')->cannotBeEmpty()->end()
                ->scalarNode('header')
                    ->info('The header a client sends its token in: "Authorization", with the Bearer scheme, or another header holding the token alone, such as "X-Api-Key".')
                    ->defaultValue(ApiKeysServices::AUTHORIZATION)
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('realm')->info("The realm of the WWW-Authenticate challenge, the same as the firewall's access_token.realm.")->defaultValue('api')->cannotBeEmpty()->end()
                ->booleanNode('grant_user_roles')->info('Whether a request authenticated by a token also has the roles of its owner, beside the roles of its scopes.')->defaultTrue()->end()
                ->booleanNode('other_authentications')
                    ->info('Whether a request authenticated otherwise than by a token — a session — is granted every scope, for the controllers members reach both from the site and through the API.')
                    ->defaultFalse()
                ->end()
                ->integerNode('purge_retention')->info('The days an expired or revoked token is kept before phil:api-keys:purge-expired removes it.')->defaultValue(30)->min(0)->end()
                ->arrayNode('secret_scanning')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('github')
                            ->info("The endpoint GitHub's secret scanning partner program posts leaked tokens to, whose route config/github_secret_scanning.php declares: each token of this application it reports is revoked.")
                            ->canBeEnabled()
                            ->children()
                                ->scalarNode('keys_url')->info('Where GitHub publishes the keys it signs with, an https address.')->defaultValue(GitHubPublicKeys::DEFAULT_URL)->cannotBeEmpty()->end()
                                ->scalarNode('http_client')->info('Service id of the HTTP client that fetches the keys.')->defaultValue('http_client')->cannotBeEmpty()->end()
                                ->scalarNode('cache')->info('Service id of the cache pool that keeps the keys, shared by every worker.')->defaultValue('cache.app')->cannotBeEmpty()->end()
                                ->integerNode('keys_lifetime')->info('The seconds the keys are kept.')->defaultValue(86_400)->min(60)->end()
                                ->integerNode('refresh_interval')->info('The fewest seconds between two fetches of the keys, and the seconds a failed fetch is kept.')->defaultValue(60)->min(1)->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('member_page')
                    ->info('The page where members create and revoke their tokens, whose routes config/routes.php declares.')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('template')->defaultValue(self::TEMPLATE)->cannotBeEmpty()->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine')) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['PhilApiKeysBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Phil\ApiKeysBundle\Domain\Model',
            ]]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @phpstan-var array{prefix: string, hash_key: non-empty-list<string>, scopes: array<array-key, array{description: string, label: string|null}>, owner_types: array<string, array{provider: string, scopes: list<string>}>, scope_translation_domain: string, default_lifetime: positive-int, max_lifetime: positive-int, lifetime_choices: list<positive-int>, last_used_throttle: non-negative-int, last_used_address: string|null, store: string, entity_manager: string, clock: string, header: string, realm: string, grant_user_roles: bool, other_authentications: bool, purge_retention: non-negative-int, secret_scanning: array{github: array{enabled: bool, keys_url: string, http_client: string, cache: string, keys_lifetime: positive-int, refresh_interval: positive-int}}, member_page: array{enabled: bool, template: string}} $config */
        ApiKeysServices::register($builder, $config);
    }
}
