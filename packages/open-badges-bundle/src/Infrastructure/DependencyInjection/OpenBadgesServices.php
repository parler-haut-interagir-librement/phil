<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle\Infrastructure\DependencyInjection;

use Phil\OpenBadges\Clr\ClrIssuer;
use Phil\OpenBadges\Contract\ContextLoader;
use Phil\OpenBadges\Contract\Signer;
use Phil\OpenBadges\Contract\VerificationMethods;
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadges\DataIntegrity\EddsaRdfc2022;
use Phil\OpenBadges\EndorsementIssuer;
use Phil\OpenBadges\Issuer;
use Phil\OpenBadges\Jwt\JwtSigner;
use Phil\OpenBadges\Status\StatusListIssuer;
use Phil\OpenBadges\Verification\CredentialVerifier;
use Phil\OpenBadgesBundle\Application\Securing;
use Phil\OpenBadgesBundle\Infrastructure\Http\CredentialCheck;
use Phil\OpenBadgesBundle\Infrastructure\Http\IssuerController;
use Phil\OpenBadgesBundle\Infrastructure\Http\StatusListController;
use Phil\OpenBadgesBundle\Infrastructure\Http\VerificationController;
use Phil\OpenBadgesBundle\Infrastructure\Http\VerificationRateLimit;
use Phil\OpenBadgesBundle\Infrastructure\Routing\StatusListUrls;
use Phil\OpenBadgesBundle\Infrastructure\Verification\RemoteVerificationMethods;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\SafeHttpClient;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The bundle's services: the issuer and its key, the builders of credentials, endorsements, learner records and status
 * lists, the securing the configuration chooses with the JSON-LD contexts the application provides, the verifier with
 * the keys it knows — and those of other issuers' profiles, read through `phil/safe-http`, when the configuration asks
 * for them —, and the controllers of the profile, the keys, the status lists and the verification page, with the limit
 * on its checks when one is configured. None keeps anything from one request to the next.
 */
final class OpenBadgesServices
{
    public const string PROFILE = 'phil_open_badges.profile';

    public const string KEYS = 'phil_open_badges.keys';

    public const string KNOWN_METHODS = 'phil_open_badges.known_methods';

    public const string HTTP_CLIENT = 'phil_open_badges.safe_http_client';

    private function __construct()
    {
    }

    /**
     * @param array{issuer: array{id: string, name: string, url: ?string, email: ?string, image: ?string}, key: array{algorithm: string, secret: string, passphrase: ?string}, securing: string, allow_localhost: bool, clock: string, max_age: int, contexts: array<string, string>, trusted_keys: array<string, string>, status_lists: array{service: ?string, size: int, max_age: int}, verification: array{enabled: bool, layout: string, max_upload: int, rate_limit: ?string}, remote_issuers: array{enabled: bool, trusted: list<string>, cache: string, ttl: int, failure_ttl: int, timeout: float, max_duration: float, max_bytes: int, user_agent: string}} $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $method = $config['issuer']['id'].'#key-1';
        $clock = new Reference($config['clock']);
        $issuer = new Reference(Issuer::class);
        $signer = new Reference(Signer::class);
        $lists = null === $config['status_lists']['service'] ? null : new Reference($config['status_lists']['service'], ContainerInterface::NULL_ON_INVALID_REFERENCE);

        $container->register(Issuer::class, Issuer::class)
            ->setArguments([$config['issuer']['id'], $config['issuer']['name'], $config['issuer']['url'], $config['issuer']['email'], $config['issuer']['image']]);
        $container->register(Signer::class, Signer::class)
            ->setFactory([Keys::class, 'signer'])
            ->setArguments([$config['key']['algorithm'], $config['key']['secret'], $config['key']['passphrase']]);

        $container->register(CredentialIssuer::class, CredentialIssuer::class)->setArguments([$issuer, $signer, $method]);
        $container->register(EndorsementIssuer::class, EndorsementIssuer::class)->setArguments([$issuer]);
        $container->register(ClrIssuer::class, ClrIssuer::class)->setArguments([$issuer]);
        $container->register(StatusListIssuer::class, StatusListIssuer::class)->setArguments([$issuer]);
        $container->register(JwtSigner::class, JwtSigner::class)->setArguments([$signer, $method]);
        $container->register(EddsaRdfc2022::class, EddsaRdfc2022::class)
            ->setArguments([new Definition(ContextLoader::class)->setFactory([Contexts::class, 'loader'])->setArguments([$config['contexts']])]);
        $container->register(Securing::class, Securing::class)
            ->setArguments([$signer, $method, $config['securing'], $clock, new Reference(EddsaRdfc2022::class)]);

        $container->register(self::KNOWN_METHODS, VerificationMethods::class)
            ->setFactory([Keys::class, 'methods'])
            ->setArguments([$signer, $method, $config['trusted_keys']]);
        self::methods($container, $config['remote_issuers'], $config['allow_localhost']);
        $container->register(CredentialVerifier::class, CredentialVerifier::class)
            ->setArguments([new Reference(VerificationMethods::class), $method, new Reference(EddsaRdfc2022::class)]);

        $container->register(StatusListUrls::class, StatusListUrls::class)->setArguments([new Reference('router'), $config['allow_localhost']]);

        $container->register(self::PROFILE, 'array')->setFactory([Keys::class, 'profile'])->setArguments([$issuer, $signer, $method]);
        $container->register(self::KEYS, 'array')->setFactory([Keys::class, 'jwks'])->setArguments([$signer, $method]);
        $container->register(IssuerController::class, IssuerController::class)
            ->setArguments([new Reference(self::PROFILE), new Reference(self::KEYS), $config['max_age']])
            ->addTag('controller.service_arguments');

        $container->register(StatusListController::class, StatusListController::class)
            ->setArguments([new Reference(StatusListIssuer::class), new Reference(Securing::class), new Reference(StatusListUrls::class), $clock, $config['status_lists']['size'], $config['status_lists']['max_age'], $lists])
            ->addTag('controller.service_arguments');

        if (!$config['verification']['enabled']) {
            return;
        }

        $container->register(CredentialCheck::class, CredentialCheck::class)
            ->setArguments([new Reference(CredentialVerifier::class), $clock, new Reference(StatusListUrls::class), $lists]);
        $container->register(VerificationController::class, VerificationController::class)
            ->setArguments([new Reference(CredentialCheck::class), new Reference('twig'), $config['verification']['layout'], $config['verification']['max_upload']])
            ->addTag('controller.service_arguments');

        if (null !== $config['verification']['rate_limit']) {
            $container->register(VerificationRateLimit::class, VerificationRateLimit::class)
                ->setArguments([$config['verification']['rate_limit']])
                ->addTag('kernel.event_listener', ['event' => 'kernel.request', 'method' => 'onKernelRequest']);
        }
    }

    /**
     * The verification methods the verifier asks: the keys the application knows, and, when the configuration asks for
     * them, those of the profiles of other issuers.
     *
     * @param array{enabled: bool, trusted: list<string>, cache: string, ttl: int, failure_ttl: int, timeout: float, max_duration: float, max_bytes: int, user_agent: string} $remote
     */
    private static function methods(ContainerBuilder $container, array $remote, bool $allowLocalhost): void
    {
        if (!$remote['enabled']) {
            $container->setAlias(VerificationMethods::class, self::KNOWN_METHODS);

            return;
        }

        $container->register(self::HTTP_CLIENT, SafeHttpClient::class)
            ->setArguments([
                new Reference('http_client', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Definition(Limits::class, [$remote['timeout'], $remote['max_duration'], $remote['max_bytes'], 3, $remote['user_agent']]),
            ])
            ->addTag('kernel.reset', ['method' => 'reset']);
        $container->register(VerificationMethods::class, RemoteVerificationMethods::class)
            ->setArguments([new Reference(self::KNOWN_METHODS), new Reference(self::HTTP_CLIENT), new Reference($remote['cache']), $remote['trusted'], $remote['ttl'], $remote['failure_ttl'], $allowLocalhost]);
    }
}
