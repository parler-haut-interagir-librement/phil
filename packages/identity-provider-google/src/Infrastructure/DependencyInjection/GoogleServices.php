<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogle\Infrastructure\DependencyInjection;

use Phil\IdentityProviderBundle\Application\Sessions;
use Phil\IdentityProviderBundle\Domain\Contract\AccountRepository;
use Phil\IdentityProviderBundle\Domain\Contract\LinkedIdentityRepository;
use Phil\IdentityProviderGoogle\Application\Contract\SubjectHolds;
use Phil\IdentityProviderGoogle\Application\GoogleClaims;
use Phil\IdentityProviderGoogle\Application\GoogleSettings;
use Phil\IdentityProviderGoogle\Application\GoogleSignIn;
use Phil\IdentityProviderGoogle\Application\LinkedSessions;
use Phil\IdentityProviderGoogle\Application\ReplayGuard;
use Phil\IdentityProviderGoogle\Application\SecurityEventReceiver;
use Phil\IdentityProviderGoogle\Application\SecurityEventTokenReader;
use Phil\IdentityProviderGoogle\Application\SignInAttempts;
use Phil\IdentityProviderGoogle\Application\TransmitterSettings;
use Phil\IdentityProviderGoogle\Infrastructure\Cache\CacheSubjectHolds;
use Phil\IdentityProviderGoogle\Infrastructure\Federation\GoogleProvider;
use Phil\IdentityProviderGoogle\Infrastructure\Http\RiscController;
use Phil\IdentityProviderGoogle\Infrastructure\Http\SafeHttpTransport;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\SafeHttpClient;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcClient;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcIdToken;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcSignatureVerifier;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\ClientSecretBasic;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;

/**
 * The services of the bundle, from its processed configuration.
 *
 * @phpstan-type Risc array{enabled: bool, audiences: list<string>, max_age: int, hold_hijacked: bool, holds_pool: ?string}
 * @phpstan-type Config array{client_id: string, client_secret: string, hosted_domains: list<string>, profile: bool, prompt: string, clock_skew: int, attempt_lifetime: int, cache_pool: string, http_client: ?string, clock: string, risc: Risc}
 */
final readonly class GoogleServices
{
    public const string ALIAS = 'phil_identity_provider_google';

    /** The tag of the federated providers of `phil/identity-provider-bundle`. */
    public const string PROVIDER_TAG = 'phil_identity_provider.external_provider';

    /** The services of Symfony's OpenID Connect pieces, for Google's sign-in and its security events. */
    public const string TRANSPORT = self::ALIAS.'.transport';

    public const string DISCOVERY = self::ALIAS.'.discovery';

    public const string SIGNATURES = self::ALIAS.'.signatures';

    public const string RISC_DISCOVERY = self::ALIAS.'.risc_discovery';

    public const string RISC_SIGNATURES = self::ALIAS.'.risc_signatures';

    private function __construct()
    {
    }

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $clock = new Reference($config['clock']);
        $pool = new Reference($config['cache_pool']);
        $logger = new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE);

        $client = new Definition(SafeHttpClient::class, [
            null === $config['http_client'] ? null : new Reference($config['http_client']),
            new Definition(Limits::class)->setFactory([SafeHttpTransport::class, 'limits']),
        ]);
        $container->register(self::TRANSPORT, SafeHttpTransport::class)->setArguments([$client])->addTag('kernel.reset', ['method' => 'reset']);
        $transport = new Reference(self::TRANSPORT);

        $container->register(self::DISCOVERY, OidcDiscovery::class)
            ->setArguments([$transport, $pool, GoogleSettings::DISCOVERY_URL, GoogleSettings::ISSUER, 86_400, self::ALIAS.'.discovery', ['authorization_endpoint', 'token_endpoint', 'jwks_uri']])
            ->addTag('kernel.reset', ['method' => 'reset']);
        $container->register(self::SIGNATURES, OidcSignatureVerifier::class)
            ->setArguments([new Reference(self::DISCOVERY), $pool, $transport, ['RS256'], 3600, true, $clock]);

        $container->register(GoogleSettings::class, GoogleSettings::class)->setArguments([
            $config['client_id'],
            $config['hosted_domains'],
            $config['profile'],
            'default' === $config['prompt'] ? null : $config['prompt'],
            $config['clock_skew'],
            $config['attempt_lifetime'],
        ]);

        $holds = self::holds($container, $config);

        $container->register(GoogleSignIn::class, GoogleSignIn::class)->setArguments([
            new Reference(self::DISCOVERY),
            new Definition(OidcClient::class, [$transport, new Reference(self::DISCOVERY), $config['client_id'], new Definition(ClientSecretBasic::class, [$config['client_secret']])]),
            new Reference(self::SIGNATURES),
            new Definition(OidcIdToken::class, [$clock, $config['clock_skew']]),
            new Reference(GoogleSettings::class),
            new Definition(GoogleClaims::class, [new Reference(GoogleSettings::class)]),
            new Definition(SignInAttempts::class),
            $clock,
            $holds,
            $logger,
        ]);
        $container->register(GoogleProvider::class, GoogleProvider::class)
            ->setArguments([new Reference(GoogleSignIn::class)])
            ->addTag(self::PROVIDER_TAG);

        $receiver = $config['risc']['enabled'] ? self::receiver($container, $config, $holds) : null;

        $container->register(RiscController::class, RiscController::class)
            ->setArguments([$receiver])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
    }

    /**
     * @param Config $config
     */
    private static function holds(ContainerBuilder $container, array $config): ?Reference
    {
        if (!$config['risc']['enabled'] || !$config['risc']['hold_hijacked']) {
            return null;
        }

        $container->register(CacheSubjectHolds::class, CacheSubjectHolds::class)->setArguments([new Reference($config['risc']['holds_pool'] ?? $config['cache_pool'])]);
        $container->setAlias(SubjectHolds::class, CacheSubjectHolds::class);

        return new Reference(SubjectHolds::class);
    }

    /**
     * @param Config $config
     */
    private static function receiver(ContainerBuilder $container, array $config, ?Reference $holds): Reference
    {
        $clock = new Reference($config['clock']);
        $pool = new Reference($config['cache_pool']);
        $transmitter = new Definition(TransmitterSettings::class, [[] === $config['risc']['audiences'] ? [$config['client_id']] : $config['risc']['audiences'], $config['risc']['max_age']]);
        $transmitter->setFactory([TransmitterSettings::class, 'google']);

        $container->setDefinition(TransmitterSettings::class, $transmitter);

        $container->register(self::RISC_DISCOVERY, OidcDiscovery::class)
            ->setArguments([new Reference(self::TRANSPORT), $pool, TransmitterSettings::GOOGLE_CONFIGURATION_URL, TransmitterSettings::GOOGLE_ISSUER, 86_400, self::ALIAS.'.risc_discovery', ['jwks_uri']])
            ->addTag('kernel.reset', ['method' => 'reset']);
        $container->register(self::RISC_SIGNATURES, OidcSignatureVerifier::class)
            ->setArguments([new Reference(self::RISC_DISCOVERY), $pool, new Reference(self::TRANSPORT), ['RS256'], 3600, true, $clock]);

        $container->register(SecurityEventReceiver::class, SecurityEventReceiver::class)->setArguments([
            new Definition(SecurityEventTokenReader::class, [new Reference(self::RISC_SIGNATURES), new Reference(TransmitterSettings::class), $clock]),
            new Definition(ReplayGuard::class, [$pool, $config['risc']['max_age']]),
            new Definition(LinkedSessions::class, [
                new Reference(LinkedIdentityRepository::class),
                new Reference(AccountRepository::class),
                new Reference(Sessions::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ]),
            new Reference(TransmitterSettings::class),
            $holds,
            new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);

        return new Reference(SecurityEventReceiver::class);
    }
}
