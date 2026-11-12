<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Infrastructure\DependencyInjection;

use Phil\IdentityClientBundle\Infrastructure\Security\DeclaredOriginsReturnToPolicy;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;

/**
 * The `phil_identity_client` tree.
 *
 * @phpstan-type Level array{acr: ?string, max_age: ?int}
 * @phpstan-type Config array{
 *     firewall: string,
 *     issuer: string,
 *     discovery_uri: ?string,
 *     http_client: ?string,
 *     client_id: string,
 *     client_secret: ?string,
 *     client_secret_transport: 'basic'|'post',
 *     client_key: ?string,
 *     client_key_algorithm: string,
 *     public_client: bool,
 *     scopes: list<string>,
 *     claims: list<string>,
 *     claims_source: 'id_token'|'userinfo',
 *     signature_algorithm: string,
 *     allowed_time_drift: int,
 *     authorization_params: array<string, string>,
 *     lock_factory: string,
 *     paths: array{check: string, start: string, landing: string, failure: string},
 *     return_to: array{parameter: string, origins: list<string>},
 *     session: array{max_lifetime: int, start_claim: string},
 *     back_channel_logout: array{enabled: bool, path: string, replay_ttl: int, cache: string},
 *     end_session: array{enabled: bool, post_logout_path: string},
 *     elevation: array{max_age: ?int, retry_after: int, levels: array<string, Level>},
 *     refresh: array{enabled: bool, leeway: int, wait_timeout: float, lock_ttl: float, outcome_ttl: int, cache: string},
 *     tokens: array{keep_access_token: bool, keep_id_token: bool},
 * }
 */
final class ClientConfiguration
{
    public const array SIGNATURE_ALGORITHMS = ['RS256', 'RS384', 'RS512', 'ES256', 'ES384', 'ES512', 'PS256', 'PS384', 'PS512'];

    private const int DAY = 86400;

    private const int SESSION_CAP = 43200;

    public static function define(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->validate()
                ->ifTrue(static fn (array $config): bool => 1 !== \count(array_filter([null !== $config['client_secret'], null !== $config['client_key'], true === $config['public_client']])))
                ->thenInvalid('Set exactly one of phil_identity_client.client_secret, client_key or public_client: %s')
            ->end()
            ->children()
                ->scalarNode('firewall')->info('The firewall of security.firewalls to sign in on; it must be declared there.')->defaultValue('main')->cannotBeEmpty()->end()
                ->scalarNode('issuer')->info('The issuer URL of the provider.')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('discovery_uri')->info('The discovery document, when it is not under the issuer.')->defaultNull()->end()
                ->scalarNode('http_client')->info('The HTTP client service for the calls to the provider.')->defaultNull()->end()
                ->scalarNode('client_id')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('client_secret')->info('The client secret, from a Symfony secret.')->defaultNull()->end()
                ->enumNode('client_secret_transport')->values(['basic', 'post'])->defaultValue('basic')->end()
                ->scalarNode('client_key')->info('A JSON-encoded private JWK, from a Symfony secret, to authenticate with a signed JWT.')->defaultNull()->end()
                ->enumNode('client_key_algorithm')->values(self::SIGNATURE_ALGORITHMS)->defaultValue('RS256')->end()
                ->booleanNode('public_client')->info('A client that holds no secret, bound by PKCE alone.')->defaultFalse()->end()
                ->arrayNode('scopes')->scalarPrototype()->end()->defaultValue(['openid'])->end()
                ->arrayNode('claims')->info('The claims handed to the UserProvisioner; every other is dropped.')->scalarPrototype()->end()->defaultValue([])->end()
                ->enumNode('claims_source')->values(['id_token', 'userinfo'])->defaultValue('id_token')->end()
                ->enumNode('signature_algorithm')->info('The one algorithm tokens of the provider are accepted with.')->values(self::SIGNATURE_ALGORITHMS)->defaultValue('RS256')->end()
                ->integerNode('allowed_time_drift')->min(0)->defaultValue(5)->end()
                ->arrayNode('authorization_params')->info('Extra parameters of each authorization request.')->normalizeKeys(false)->useAttributeAsKey('name')->scalarPrototype()->end()->defaultValue([])->end()
                ->scalarNode('lock_factory')->info('The LockFactory service; its store must be shared by every server.')->defaultValue('lock.factory')->cannotBeEmpty()->end()
                ->arrayNode('paths')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('check')->defaultValue('/oidc/callback')->cannotBeEmpty()->end()
                        ->scalarNode('start')->defaultValue('/oidc/start')->cannotBeEmpty()->end()
                        ->scalarNode('landing')->info('Where a sign-in lands without a valid target.')->defaultValue('/')->cannotBeEmpty()->end()
                        ->scalarNode('failure')->info('Where a failed sign-in lands.')->defaultValue('/')->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->arrayNode('return_to')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('parameter')->defaultValue('return_to')->cannotBeEmpty()->end()
                        ->arrayNode('origins')
                            ->info('The origins a return_to may point at, besides the paths of the application.')
                            ->scalarPrototype()
                                ->validate()
                                    ->ifTrue(static fn (mixed $origin): bool => \is_string($origin) && !str_contains($origin, '%') && !DeclaredOriginsReturnToPolicy::isDeclarable($origin))
                                    ->thenInvalid('An origin is a bare https://host[:port], or http on a loopback host: %s')
                                ->end()
                            ->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('session')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('max_lifetime')->info('The session cap in seconds, measured from the start of the session at the provider; 0 or null for none.')->min(0)->treatNullLike(0)->defaultValue(self::SESSION_CAP)->end()
                        ->scalarNode('start_claim')->info('The ID token claim that dates the session at the provider; auth_time when absent.')->defaultValue('session_start')->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->arrayNode('back_channel_logout')
                    ->canBeDisabled()
                    ->children()
                        ->scalarNode('path')->defaultValue('/oidc/back-channel-logout')->cannotBeEmpty()->end()
                        ->integerNode('replay_ttl')->info('How long an accepted jti is remembered, in seconds.')->min(1)->defaultValue(self::DAY)->end()
                        ->scalarNode('cache')->info('The cache pool, shared by every server.')->defaultValue('cache.security')->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->arrayNode('end_session')
                    ->info('Signs the user out at the provider too, on local logout.')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('post_logout_path')->defaultValue('/')->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->arrayNode('elevation')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('max_age')->info('The max_age of every authorization request.')->min(0)->defaultNull()->end()
                        ->integerNode('retry_after')->info('How long, in seconds, a level the provider declined is not asked for again.')->min(0)->defaultValue(60)->end()
                        ->arrayNode('levels')
                            ->info('The assurance scale, weakest first; each is granted as OIDC_ASSURANCE:<name>.')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->validate()
                                    ->ifTrue(static fn (array $level): bool => null === $level['acr'] && null === $level['max_age'])
                                    ->thenInvalid('An assurance level needs an acr, a max_age, or both: %s')
                                ->end()
                                ->children()
                                    ->scalarNode('acr')->defaultNull()->end()
                                    ->integerNode('max_age')->min(0)->defaultNull()->end()
                                ->end()
                            ->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('refresh')
                    ->info('Renews the access token through the refresh token grant, one request at a time.')
                    ->canBeEnabled()
                    ->children()
                        ->integerNode('leeway')->min(0)->defaultValue(30)->end()
                        ->floatNode('wait_timeout')->info('How long a request waits, in seconds, for a refresh under way.')->min(0)->defaultValue(10.0)->end()
                        ->floatNode('lock_ttl')->info('The lifetime of the lock, in seconds; above the timeout of the HTTP client.')->min(1)->defaultValue(30.0)->end()
                        ->integerNode('outcome_ttl')->info('How long, in seconds, the result of a rotation stays readable by the requests that hold the previous token.')->min(1)->defaultValue(120)->end()
                        ->scalarNode('cache')->info('The cache pool, shared by every server.')->defaultValue('cache.security')->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->arrayNode('tokens')
                    ->info('The tokens to keep in the session beyond what the features turned on here need.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('keep_access_token')->defaultFalse()->end()
                        ->booleanNode('keep_id_token')->defaultFalse()->end()
                    ->end()
                ->end()
            ->end();
    }
}
