<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Infrastructure\DependencyInjection;

use Phil\ApiContractBundle\Infrastructure\Http\ProblemDetailsResponse;
use Phil\ApiKeysBundle\Application\ApiTokens;
use Phil\ApiKeysBundle\Application\Contract\ClientAddressAnonymizer;
use Phil\ApiKeysBundle\Application\OwnerTypes;
use Phil\ApiKeysBundle\Application\ScopeCatalogue;
use Phil\ApiKeysBundle\Application\TokenHasher;
use Phil\ApiKeysBundle\Domain\Contract\ApiTokenRepository;
use Phil\ApiKeysBundle\Domain\Token\TokenFormat;
use Phil\ApiKeysBundle\Infrastructure\Console\CreateTokenCommand;
use Phil\ApiKeysBundle\Infrastructure\Console\ListTokensCommand;
use Phil\ApiKeysBundle\Infrastructure\Console\PurgeExpiredTokensCommand;
use Phil\ApiKeysBundle\Infrastructure\Console\RevokeTokenCommand;
use Phil\ApiKeysBundle\Infrastructure\Doctrine\DoctrineApiTokenRepository;
use Phil\ApiKeysBundle\Infrastructure\Form\CreateTokenType;
use Phil\ApiKeysBundle\Infrastructure\Http\TokenPageController;
use Phil\ApiKeysBundle\Infrastructure\IpAnonymizer\AnonymizedClientAddress;
use Phil\ApiKeysBundle\Infrastructure\Memory\InMemoryApiTokenRepository;
use Phil\ApiKeysBundle\Infrastructure\RateLimit\TokenKey;
use Phil\ApiKeysBundle\Infrastructure\SecretScanning\GitHub\GitHubPublicKeys;
use Phil\ApiKeysBundle\Infrastructure\SecretScanning\GitHub\GitHubSecretScanningController;
use Phil\ApiKeysBundle\Infrastructure\Security\ApiTokenHandler;
use Phil\ApiKeysBundle\Infrastructure\Security\ProblemErrorResponder;
use Phil\ApiKeysBundle\Infrastructure\Security\RequiresScopeListener;
use Phil\ApiKeysBundle\Infrastructure\Security\ScopeVoter;
use Phil\ApiKeysBundle\Infrastructure\Security\TokenAuthenticationListener;
use Phil\IpAnonymizer\Truncation;
use Phil\RateLimitBundle\Application\Contract\KeyResolver;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\AccessToken\HeaderAccessTokenExtractor;
use Symfony\Component\Security\Http\Event\AuthenticationTokenCreatedEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Environment;

/**
 * The services of the configuration: the token format, the hasher, the scopes, the store, `ApiTokens`, the token
 * handler and the token extractor of Symfony's `access_token` authenticator, the listener that gives the roles of the
 * scopes, the problem details of a `401` when `phil/api-contract-bundle` is installed, the voter and the listener of
 * `#[RequiresScope]`, the address anonymizer, the commands when the console is installed, the key of the rate limiters
 * when `phil/rate-limit-bundle` is, and the members' page and the endpoint of GitHub's secret scanning when they are
 * enabled.
 *
 * @phpstan-type Config array{
 *     prefix: string,
 *     hash_key: non-empty-list<string>,
 *     scopes: array<array-key, array{description: string, label: string|null}>,
 *     owner_types: array<string, array{provider: string, scopes: list<string>}>,
 *     scope_translation_domain: string,
 *     default_lifetime: positive-int,
 *     max_lifetime: positive-int,
 *     lifetime_choices: list<positive-int>,
 *     last_used_throttle: non-negative-int,
 *     last_used_address: string|null,
 *     store: string,
 *     entity_manager: string,
 *     clock: string,
 *     header: string,
 *     realm: string,
 *     grant_user_roles: bool,
 *     other_authentications: bool,
 *     purge_retention: non-negative-int,
 *     secret_scanning: array{github: array{enabled: bool, keys_url: string, http_client: string, cache: string, keys_lifetime: positive-int, refresh_interval: positive-int}},
 *     member_page: array{enabled: bool, template: string},
 * }
 */
final class ApiKeysServices
{
    /** The `store` that keeps the tokens in a table, through the Doctrine ORM. */
    public const string STORE_DOCTRINE = 'doctrine';

    /** The `store` that keeps the tokens in memory, for tests and single runs. */
    public const string STORE_MEMORY = 'memory';

    /** The `last_used_address` that keeps the address truncated by `phil/ip-anonymizer`. */
    public const string ADDRESS_TRUNCATION = 'truncation';

    /** The header a client sends its token in by default, with the Bearer scheme. */
    public const string AUTHORIZATION = 'Authorization';

    /** The token handler, as the firewall's `access_token.token_handler` names it. */
    public const string TOKEN_HANDLER = 'phil_api_keys.token_handler';

    /** The token extractor of the configured header, as the firewall's `access_token.token_extractors` names it. */
    public const string TOKEN_EXTRACTOR = 'phil_api_keys.token_extractor';

    /**
     * The `401` as problem details, as the firewall's `entry_point` and `access_token.failure_handler` name it, with
     * `phil/api-contract-bundle`.
     */
    public const string PROBLEM_RESPONDER = 'phil_api_keys.problem_responder';

    /** The user providers of the owner types other than `user`, by type. */
    public const string OWNER_PROVIDERS = 'phil_api_keys.owner_providers';

    /** The key strategy of `phil/rate-limit-bundle` by token, as a limiter's or a rule's `key` names it. */
    public const string RATE_LIMIT_KEY = 'phil_api_keys.rate_limit_key';

    private function __construct()
    {
    }

    /**
     * @param Config    $config
     * @param bool|null $problems       whether `phil/api-contract-bundle` is installed; null to look
     * @param bool|null $rateLimit      whether `phil/rate-limit-bundle` is installed; null to look
     * @param bool|null $console        whether `symfony/console` is installed; null to look
     * @param bool|null $memberPage     whether the form component, Twig and the CSRF component are installed; null to look
     * @param bool|null $ipAnonymizer   whether `phil/ip-anonymizer` is installed; null to look
     * @param bool|null $secretScanning whether `symfony/http-client`, `symfony/cache` and `phil/url` are installed; null
     *                                  to look
     *
     * @throws InvalidConfigurationException when a part the configuration asks for is not installed
     */
    public static function register(ContainerBuilder $container, array $config, ?bool $problems = null, ?bool $rateLimit = null, ?bool $console = null, ?bool $memberPage = null, ?bool $ipAnonymizer = null, ?bool $secretScanning = null): void
    {
        if ($config['default_lifetime'] > $config['max_lifetime']) {
            throw new InvalidConfigurationException(\sprintf('phil_api_keys.default_lifetime, %d days, is longer than max_lifetime, %d days.', $config['default_lifetime'], $config['max_lifetime']));
        }

        $container->register(TokenFormat::class, TokenFormat::class)->setArguments([$config['prefix']]);
        $container->register(TokenHasher::class, TokenHasher::class)->setArguments($config['hash_key']);
        $container->register(ScopeCatalogue::class, ScopeCatalogue::class)->setArguments([$config['scopes']])->setPublic(true);
        self::ownerTypes($container, $config);
        $container->setAlias(ApiTokenRepository::class, self::store($container, $config))->setPublic(true);

        $container->register(ApiTokens::class, ApiTokens::class)
            ->setArguments([
                new Reference(ApiTokenRepository::class),
                new Reference(TokenFormat::class),
                new Reference(TokenHasher::class),
                new Reference(ScopeCatalogue::class),
                new Reference($config['clock']),
                $config['default_lifetime'],
                $config['max_lifetime'],
                $config['last_used_throttle'],
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                self::addresses($container, $config, $ipAnonymizer ?? class_exists(Truncation::class)),
                new Reference(OwnerTypes::class),
            ])
            ->setPublic(true);

        $container->register(self::OWNER_PROVIDERS, ServiceLocator::class)
            ->setArguments([array_map(static fn (array $type): Reference => new Reference($type['provider']), $config['owner_types'])])
            ->addTag('container.service_locator');
        $container->register(ApiTokenHandler::class, ApiTokenHandler::class)
            ->setArguments([
                new Reference(ApiTokens::class),
                new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference(self::OWNER_PROVIDERS),
            ]);
        $container->setAlias(self::TOKEN_HANDLER, ApiTokenHandler::class);

        $container->register(self::TOKEN_EXTRACTOR, HeaderAccessTokenExtractor::class)
            ->setArguments([$config['header'], 0 === strcasecmp($config['header'], self::AUTHORIZATION) ? 'Bearer' : '']);

        $container->register(TokenAuthenticationListener::class, TokenAuthenticationListener::class)
            ->setArguments([$config['grant_user_roles']])
            ->addTag('kernel.event_listener', ['event' => AuthenticationTokenCreatedEvent::class, 'method' => 'onAuthenticationTokenCreated'])
            ->addTag('kernel.event_listener', ['event' => LoginSuccessEvent::class, 'method' => 'onLoginSuccess']);

        if ($problems ?? class_exists(ProblemDetailsResponse::class)) {
            $container->register(ProblemErrorResponder::class, ProblemErrorResponder::class)->setArguments([$config['realm']]);
            $container->setAlias(self::PROBLEM_RESPONDER, ProblemErrorResponder::class);
        }

        $container->register(ScopeVoter::class, ScopeVoter::class)
            ->setArguments([$config['other_authentications']])
            ->addTag('security.voter');

        $container->register(RequiresScopeListener::class, RequiresScopeListener::class)
            ->setArguments([new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE), $config['realm']])
            ->addTag('kernel.event_listener', ['event' => 'kernel.controller_arguments', 'method' => 'onKernelControllerArguments']);

        if ($rateLimit ?? interface_exists(KeyResolver::class)) {
            $container->register(self::RATE_LIMIT_KEY, TokenKey::class);
        }

        if ($console ?? class_exists(Command::class)) {
            self::commands($container, $config);
        }

        if ($config['secret_scanning']['github']['enabled']) {
            self::githubSecretScanning($container, $config, $secretScanning ?? (interface_exists(HttpClientInterface::class) && interface_exists(CacheInterface::class) && class_exists(Url::class)));
        }

        if ($config['member_page']['enabled']) {
            self::memberPage($container, $config, $memberPage ?? (class_exists(AbstractType::class) && class_exists(Environment::class) && interface_exists(CsrfTokenManagerInterface::class)));
        }
    }

    /**
     * The owner types and the scopes each may hold, every one of them declared.
     *
     * @param Config $config
     *
     * @throws InvalidConfigurationException when a type lists a scope that is not declared
     */
    private static function ownerTypes(ContainerBuilder $container, array $config): void
    {
        $types = [];

        foreach ($config['owner_types'] as $type => $owner) {
            foreach ($owner['scopes'] as $scope) {
                if (!\array_key_exists($scope, $config['scopes'])) {
                    throw new InvalidConfigurationException(\sprintf('phil_api_keys.owner_types.%s.scopes names "%s", which phil_api_keys.scopes does not declare.', $type, $scope));
                }
            }

            $types[$type] = [] === $owner['scopes'] ? null : $owner['scopes'];
        }

        $container->register(OwnerTypes::class, OwnerTypes::class)->setArguments([$types])->setPublic(true);
    }

    /**
     * The store's service id: the Doctrine store, the in-memory store, or the application's.
     *
     * @param Config $config
     */
    private static function store(ContainerBuilder $container, array $config): string
    {
        if (self::STORE_MEMORY === $config['store']) {
            $container->register(InMemoryApiTokenRepository::class, InMemoryApiTokenRepository::class)->addTag('kernel.reset', ['method' => 'reset']);

            return InMemoryApiTokenRepository::class;
        }

        if (self::STORE_DOCTRINE !== $config['store']) {
            return $config['store'];
        }

        $container->register(DoctrineApiTokenRepository::class, DoctrineApiTokenRepository::class)
            ->setArguments([new Reference(\sprintf('doctrine.orm.%s_entity_manager', $config['entity_manager']))]);

        return DoctrineApiTokenRepository::class;
    }

    /**
     * What the last use keeps of the client's address: nothing, its truncation, or what the application's anonymizer
     * gives.
     *
     * @param Config $config
     */
    private static function addresses(ContainerBuilder $container, array $config, bool $ipAnonymizer): ?Reference
    {
        if (null === $config['last_used_address']) {
            return null;
        }

        if (self::ADDRESS_TRUNCATION !== $config['last_used_address']) {
            return new Reference($config['last_used_address']);
        }

        if (!$ipAnonymizer) {
            throw new InvalidConfigurationException('phil_api_keys.last_used_address "truncation" needs phil/ip-anonymizer: install it, or name a service implementing ClientAddressAnonymizer.');
        }

        $container->register(Truncation::class, Truncation::class);
        $container->register(ClientAddressAnonymizer::class, AnonymizedClientAddress::class)->setArguments([new Reference(Truncation::class)]);

        return new Reference(ClientAddressAnonymizer::class);
    }

    /**
     * @param Config $config
     */
    private static function commands(ContainerBuilder $container, array $config): void
    {
        $container->register(CreateTokenCommand::class, CreateTokenCommand::class)
            ->setArguments([new Reference(ApiTokens::class), new Reference($config['clock'])])
            ->addTag('console.command');
        $container->register(ListTokensCommand::class, ListTokensCommand::class)
            ->setArguments([new Reference(ApiTokens::class), new Reference($config['clock'])])
            ->addTag('console.command');
        $container->register(RevokeTokenCommand::class, RevokeTokenCommand::class)
            ->setArguments([new Reference(ApiTokens::class)])
            ->addTag('console.command');
        $container->register(PurgeExpiredTokensCommand::class, PurgeExpiredTokensCommand::class)
            ->setArguments([new Reference(ApiTokens::class), $config['purge_retention']])
            ->addTag('console.command');
    }

    /**
     * The endpoint of GitHub's secret scanning, with the keys it verifies signatures under.
     *
     * @param Config $config
     */
    private static function githubSecretScanning(ContainerBuilder $container, array $config, bool $installed): void
    {
        if (!$installed) {
            throw new InvalidConfigurationException('phil_api_keys.secret_scanning.github needs symfony/http-client, symfony/cache and phil/url: install them, or leave it disabled.');
        }

        $github = $config['secret_scanning']['github'];

        try {
            $secure = str_starts_with(Url::fromString($github['keys_url'])->toString(), 'https://');
        } catch (InvalidUrl) {
            $secure = false;
        }

        if (!$secure) {
            throw new InvalidConfigurationException(\sprintf('phil_api_keys.secret_scanning.github.keys_url is an https address: "%s".', $github['keys_url']));
        }

        $container->register(GitHubPublicKeys::class, GitHubPublicKeys::class)
            ->setArguments([
                new Reference($github['http_client']),
                new Reference($github['cache']),
                new Reference($config['clock']),
                $github['keys_url'],
                $github['keys_lifetime'],
                $github['refresh_interval'],
            ]);
        $container->register(GitHubSecretScanningController::class, GitHubSecretScanningController::class)
            ->setArguments([new Reference(ApiTokens::class), new Reference(GitHubPublicKeys::class)])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
    }

    /**
     * @param Config $config
     */
    private static function memberPage(ContainerBuilder $container, array $config, bool $installed): void
    {
        if (!$installed) {
            throw new InvalidConfigurationException('phil_api_keys.member_page needs symfony/form, symfony/security-csrf and twig/twig: install them, or leave the page disabled.');
        }

        $lifetimes = array_values(array_filter($config['lifetime_choices'], static fn (int $days): bool => $days <= $config['max_lifetime']));

        $container->register(CreateTokenType::class, CreateTokenType::class)
            ->setArguments([new Reference(ScopeCatalogue::class), $lifetimes, $config['default_lifetime'], $config['scope_translation_domain']])
            ->addTag('form.type');
        $container->register(TokenPageController::class, TokenPageController::class)
            ->setArguments([
                new Reference(ApiTokens::class),
                new Reference(ScopeCatalogue::class),
                new Reference('form.factory'),
                new Reference('twig'),
                new Reference('security.token_storage'),
                new Reference('security.csrf.token_manager'),
                new Reference('router'),
                new Reference($config['clock']),
                $config['member_page']['template'],
                $config['scope_translation_domain'],
                new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference(OwnerTypes::class),
            ])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
    }
}
