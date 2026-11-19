<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Unit;

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
use Phil\ApiKeysBundle\Infrastructure\DependencyInjection\ApiKeysServices;
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
use Phil\ApiKeysBundle\PhilApiKeysBundle;
use Phil\IpAnonymizer\Truncation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Http\AccessToken\HeaderAccessTokenExtractor;
use Symfony\Component\Security\Http\Event\AuthenticationTokenCreatedEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[CoversClass(PhilApiKeysBundle::class)]
#[CoversClass(ApiKeysServices::class)]
final class PhilApiKeysBundleTest extends TestCase
{
    private const string KEY = 'a-test-key-of-thirty-two-bytes!!';

    public function testTheServices(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_api_keys', $this->extension()->getAlias());
        self::assertSame(['service_container', TokenFormat::class, TokenHasher::class, ScopeCatalogue::class, OwnerTypes::class, DoctrineApiTokenRepository::class, ApiTokens::class, ApiKeysServices::OWNER_PROVIDERS, ApiTokenHandler::class, ApiKeysServices::TOKEN_EXTRACTOR, TokenAuthenticationListener::class, ProblemErrorResponder::class, ScopeVoter::class, RequiresScopeListener::class, ApiKeysServices::RATE_LIMIT_KEY, CreateTokenCommand::class, ListTokensCommand::class, RevokeTokenCommand::class, PurgeExpiredTokensCommand::class], array_keys($container->getDefinitions()));
        self::assertSame(['pat'], $container->getDefinition(TokenFormat::class)->getArguments());
        self::assertSame([self::KEY], $container->getDefinition(TokenHasher::class)->getArguments());
        self::assertSame([[]], $container->getDefinition(ScopeCatalogue::class)->getArguments());
        self::assertTrue($container->getDefinition(ScopeCatalogue::class)->isPublic());
        self::assertEquals([new Reference('doctrine.orm.default_entity_manager')], $container->getDefinition(DoctrineApiTokenRepository::class)->getArguments());
        self::assertSame([DoctrineApiTokenRepository::class, true], [(string) $container->getAlias(ApiTokenRepository::class), $container->getAlias(ApiTokenRepository::class)->isPublic()]);
        self::assertEquals([
            new Reference(ApiTokenRepository::class),
            new Reference(TokenFormat::class),
            new Reference(TokenHasher::class),
            new Reference(ScopeCatalogue::class),
            new Reference('clock'),
            90,
            365,
            300,
            new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            null,
            new Reference(OwnerTypes::class),
        ], $container->getDefinition(ApiTokens::class)->getArguments());
        self::assertSame([[]], $container->getDefinition(OwnerTypes::class)->getArguments());
        self::assertTrue($container->getDefinition(ApiTokens::class)->isPublic());
        self::assertSame(['api'], $container->getDefinition(ProblemErrorResponder::class)->getArguments());
        self::assertSame(ProblemErrorResponder::class, (string) $container->getAlias(ApiKeysServices::PROBLEM_RESPONDER));
        self::assertEquals([new Reference(ApiTokens::class), new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE), new Reference(ApiKeysServices::OWNER_PROVIDERS)], $container->getDefinition(ApiTokenHandler::class)->getArguments());
        self::assertSame([[]], $container->getDefinition(ApiKeysServices::OWNER_PROVIDERS)->getArguments());
        self::assertSame(ApiTokenHandler::class, (string) $container->getAlias(ApiKeysServices::TOKEN_HANDLER));
        self::assertFalse($container->getDefinition(ApiTokenHandler::class)->isPublic());
        self::assertSame(HeaderAccessTokenExtractor::class, $container->getDefinition(ApiKeysServices::TOKEN_EXTRACTOR)->getClass());
        self::assertSame(['Authorization', 'Bearer'], $container->getDefinition(ApiKeysServices::TOKEN_EXTRACTOR)->getArguments());
        self::assertSame([true], $container->getDefinition(TokenAuthenticationListener::class)->getArguments());
        self::assertSame([
            ['event' => AuthenticationTokenCreatedEvent::class, 'method' => 'onAuthenticationTokenCreated'],
            ['event' => LoginSuccessEvent::class, 'method' => 'onLoginSuccess'],
        ], $container->getDefinition(TokenAuthenticationListener::class)->getTag('kernel.event_listener'));
        self::assertFalse($container->hasDefinition(GitHubSecretScanningController::class));
        self::assertSame([false], $container->getDefinition(ScopeVoter::class)->getArguments());
        self::assertTrue($container->getDefinition(ScopeVoter::class)->hasTag('security.voter'));
        self::assertSame(TokenKey::class, $container->getDefinition(ApiKeysServices::RATE_LIMIT_KEY)->getClass());
        self::assertEquals([new Reference(ApiTokens::class), new Reference('clock')], $container->getDefinition(CreateTokenCommand::class)->getArguments());
        self::assertEquals([new Reference(ApiTokens::class), new Reference('clock')], $container->getDefinition(ListTokensCommand::class)->getArguments());
        self::assertEquals([new Reference(ApiTokens::class)], $container->getDefinition(RevokeTokenCommand::class)->getArguments());
        self::assertEquals([new Reference(ApiTokens::class), 30], $container->getDefinition(PurgeExpiredTokensCommand::class)->getArguments());

        foreach ([CreateTokenCommand::class, ListTokensCommand::class, RevokeTokenCommand::class, PurgeExpiredTokensCommand::class] as $command) {
            self::assertTrue($container->getDefinition($command)->hasTag('console.command'));
        }
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load([
            'prefix' => 'acme',
            'scopes' => ['projects:read' => ['description' => 'Read', 'label' => 'scope.read'], 'billing' => ['description' => 'Invoices'], 'a-b' => ['description' => 'Dashed']],
            'default_lifetime' => 10,
            'max_lifetime' => 20,
            'last_used_throttle' => 0,
            'last_used_address' => 'app.addresses',
            'store' => 'memory',
            'clock' => 'app.clock',
            'header' => 'X-Api-Key',
            'realm' => 'acme',
            'grant_user_roles' => false,
            'other_authentications' => true,
            'purge_retention' => 0,
        ]);

        self::assertSame(['acme'], $container->getDefinition(TokenFormat::class)->getArguments());
        self::assertSame([['projects:read' => ['description' => 'Read', 'label' => 'scope.read'], 'billing' => ['description' => 'Invoices', 'label' => null], 'a-b' => ['description' => 'Dashed', 'label' => null]]], $container->getDefinition(ScopeCatalogue::class)->getArguments());
        self::assertTrue($container->hasDefinition(InMemoryApiTokenRepository::class));
        self::assertFalse($container->hasDefinition(DoctrineApiTokenRepository::class));
        self::assertSame(InMemoryApiTokenRepository::class, (string) $container->getAlias(ApiTokenRepository::class));
        self::assertSame([['method' => 'reset']], $container->getDefinition(InMemoryApiTokenRepository::class)->getTag('kernel.reset'));
        self::assertEquals([new Reference('app.clock'), 10, 20, 0], \array_slice($container->getDefinition(ApiTokens::class)->getArguments(), 4, 4));
        self::assertEquals(new Reference('app.addresses'), $container->getDefinition(ApiTokens::class)->getArgument(9));
        self::assertSame(['acme'], $container->getDefinition(ProblemErrorResponder::class)->getArguments());
        self::assertSame(['X-Api-Key', ''], $container->getDefinition(ApiKeysServices::TOKEN_EXTRACTOR)->getArguments());
        self::assertSame([false], $container->getDefinition(TokenAuthenticationListener::class)->getArguments());
        self::assertSame(['authorization', 'Bearer'], $this->load(['header' => 'authorization'])->getDefinition(ApiKeysServices::TOKEN_EXTRACTOR)->getArguments());
        self::assertSame([true], $container->getDefinition(ScopeVoter::class)->getArguments());
        self::assertEquals([new Reference(ApiTokens::class), new Reference('app.clock')], $container->getDefinition(CreateTokenCommand::class)->getArguments());
        self::assertEquals([new Reference(ApiTokens::class), new Reference('app.clock')], $container->getDefinition(ListTokensCommand::class)->getArguments());
        self::assertEquals([new Reference(ApiTokens::class), 0], $container->getDefinition(PurgeExpiredTokensCommand::class)->getArguments());

        self::assertEquals([new Reference('doctrine.orm.tokens_entity_manager')], $this->load(['entity_manager' => 'tokens'])->getDefinition(DoctrineApiTokenRepository::class)->getArguments());
        $own = $this->load(['store' => 'app.tokens']);
        self::assertSame('app.tokens', (string) $own->getAlias(ApiTokenRepository::class));
        self::assertFalse($own->hasDefinition(DoctrineApiTokenRepository::class));
        self::assertFalse($own->hasDefinition(InMemoryApiTokenRepository::class));
        self::assertSame([5, 5], \array_slice($this->load(['default_lifetime' => 5, 'max_lifetime' => 5])->getDefinition(ApiTokens::class)->getArguments(), 5, 2));
    }

    public function testTheOwnerTypes(): void
    {
        $container = $this->load([
            'scopes' => ['projects:read' => ['description' => 'Read'], 'billing' => ['description' => 'Invoices']],
            'owner_types' => [
                'organization' => ['provider' => 'app.organizations', 'scopes' => ['billing']],
                'application' => ['provider' => 'app.applications'],
            ],
        ]);

        self::assertSame([['organization' => ['billing'], 'application' => null]], $container->getDefinition(OwnerTypes::class)->getArguments());
        self::assertTrue($container->getDefinition(OwnerTypes::class)->isPublic());
        self::assertEquals(new Reference(OwnerTypes::class), $container->getDefinition(ApiTokens::class)->getArgument(10));
        $locator = $container->getDefinition(ApiKeysServices::OWNER_PROVIDERS);
        self::assertEquals([['organization' => new Reference('app.organizations'), 'application' => new Reference('app.applications')]], $locator->getArguments());
        self::assertTrue($locator->hasTag('container.service_locator'));
        self::assertEquals(new Reference(ApiKeysServices::OWNER_PROVIDERS), $container->getDefinition(ApiTokenHandler::class)->getArgument(2));
        self::assertEquals([new Reference(ApiTokens::class), new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE), new Reference(ApiKeysServices::OWNER_PROVIDERS)], $container->getDefinition(ApiTokenHandler::class)->getArguments());

        try {
            $this->load(['scopes' => ['billing' => ['description' => 'Invoices']], 'owner_types' => ['organization' => ['provider' => 'p', 'scopes' => ['projects:read']]]]);
            self::fail('An owner type may only hold declared scopes.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertSame('phil_api_keys.owner_types.organization.scopes names "projects:read", which phil_api_keys.scopes does not declare.', $invalidConfigurationException->getMessage());
        }
    }

    public function testTheTruncatedAddress(): void
    {
        $container = $this->load(['last_used_address' => 'truncation']);

        self::assertSame(Truncation::class, $container->getDefinition(Truncation::class)->getClass());
        self::assertSame(AnonymizedClientAddress::class, $container->getDefinition(ClientAddressAnonymizer::class)->getClass());
        self::assertEquals([new Reference(Truncation::class)], $container->getDefinition(ClientAddressAnonymizer::class)->getArguments());
        self::assertEquals(new Reference(ClientAddressAnonymizer::class), $container->getDefinition(ApiTokens::class)->getArgument(9));

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_api_keys.last_used_address "truncation" needs phil/ip-anonymizer: install it, or name a service implementing ClientAddressAnonymizer.');

        ApiKeysServices::register(new ContainerBuilder(), [...$this->defaults(), 'last_used_address' => 'truncation'], ipAnonymizer: false);
    }

    public function testWhatIsInstalledDecides(): void
    {
        $container = new ContainerBuilder();
        ApiKeysServices::register($container, $this->defaults(), false, false, false);

        self::assertSame(['service_container', TokenFormat::class, TokenHasher::class, ScopeCatalogue::class, OwnerTypes::class, DoctrineApiTokenRepository::class, ApiTokens::class, ApiKeysServices::OWNER_PROVIDERS, ApiTokenHandler::class, ApiKeysServices::TOKEN_EXTRACTOR, TokenAuthenticationListener::class, ScopeVoter::class, RequiresScopeListener::class], array_keys($container->getDefinitions()));
        self::assertFalse($container->hasAlias(ApiKeysServices::PROBLEM_RESPONDER));
        self::assertEquals([new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE), 'api'], $container->getDefinition(RequiresScopeListener::class)->getArguments());
        self::assertSame([['event' => 'kernel.controller_arguments', 'method' => 'onKernelControllerArguments']], $container->getDefinition(RequiresScopeListener::class)->getTag('kernel.event_listener'));
    }

    public function testTheMembersPage(): void
    {
        $container = $this->load(['member_page' => true, 'scope_translation_domain' => 'scopes', 'max_lifetime' => 90, 'lifetime_choices' => [7, 91, 90, 365]]);

        self::assertEquals([new Reference(ScopeCatalogue::class), [7, 90], 90, 'scopes'], $container->getDefinition(CreateTokenType::class)->getArguments());
        self::assertTrue($container->getDefinition(CreateTokenType::class)->hasTag('form.type'));
        self::assertEquals([
            new Reference(ApiTokens::class),
            new Reference(ScopeCatalogue::class),
            new Reference('form.factory'),
            new Reference('twig'),
            new Reference('security.token_storage'),
            new Reference('security.csrf.token_manager'),
            new Reference('router'),
            new Reference('clock'),
            PhilApiKeysBundle::TEMPLATE,
            'scopes',
            new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference(OwnerTypes::class),
        ], $container->getDefinition(TokenPageController::class)->getArguments());
        self::assertTrue($container->getDefinition(TokenPageController::class)->hasTag('controller.service_arguments'));
        self::assertTrue($container->getDefinition(TokenPageController::class)->isPublic());
        self::assertSame([[7, 30, 90, 365], '@App/tokens.html.twig'], [$this->load(['member_page' => ['template' => '@App/tokens.html.twig']])->getDefinition(CreateTokenType::class)->getArgument(1), $this->load(['member_page' => ['template' => '@App/tokens.html.twig']])->getDefinition(TokenPageController::class)->getArgument(8)]);
        self::assertFalse($this->load([])->hasDefinition(TokenPageController::class));

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_api_keys.member_page needs symfony/form, symfony/security-csrf and twig/twig: install them, or leave the page disabled.');

        ApiKeysServices::register(new ContainerBuilder(), [...$this->defaults(), 'member_page' => ['enabled' => true, 'template' => PhilApiKeysBundle::TEMPLATE]], memberPage: false);
    }

    public function testTheHashKeysRotate(): void
    {
        $previous = str_repeat('o', 32);

        self::assertSame([self::KEY, $previous], $this->load(['hash_key' => [self::KEY, $previous]])->getDefinition(TokenHasher::class)->getArguments());
    }

    public function testTheEndpointOfGitHubsSecretScanning(): void
    {
        $container = $this->load(['clock' => 'app.clock', 'secret_scanning' => ['github' => true]]);

        self::assertEquals([
            new Reference('http_client'),
            new Reference('cache.app'),
            new Reference('app.clock'),
            GitHubPublicKeys::DEFAULT_URL,
            86_400,
            60,
        ], $container->getDefinition(GitHubPublicKeys::class)->getArguments());
        self::assertEquals([new Reference(ApiTokens::class), new Reference(GitHubPublicKeys::class)], $container->getDefinition(GitHubSecretScanningController::class)->getArguments());
        self::assertTrue($container->getDefinition(GitHubSecretScanningController::class)->hasTag('controller.service_arguments'));
        self::assertTrue($container->getDefinition(GitHubSecretScanningController::class)->isPublic());

        $configured = $this->load(['secret_scanning' => ['github' => [
            'keys_url' => 'https://keys.example.com/github',
            'http_client' => 'app.http',
            'cache' => 'cache.keys',
            'keys_lifetime' => 60,
            'refresh_interval' => 1,
        ]]]);
        self::assertEquals([new Reference('app.http'), new Reference('cache.keys'), new Reference('clock'), 'https://keys.example.com/github', 60, 1], $configured->getDefinition(GitHubPublicKeys::class)->getArguments());

        foreach (['http://keys.example.com/', 'nope', 'https://user:pass@keys.example.com/'] as $address) {
            try {
                $this->load(['secret_scanning' => ['github' => ['keys_url' => $address]]]);
                self::fail(\sprintf('The address %s was accepted.', $address));
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertSame(\sprintf('phil_api_keys.secret_scanning.github.keys_url is an https address: "%s".', $address), $invalidConfigurationException->getMessage());
            }
        }

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_api_keys.secret_scanning.github needs symfony/http-client, symfony/cache and phil/url: install them, or leave it disabled.');

        ApiKeysServices::register(new ContainerBuilder(), [...$this->defaults(), 'secret_scanning' => ['github' => ['enabled' => true, 'keys_url' => GitHubPublicKeys::DEFAULT_URL, 'http_client' => 'http_client', 'cache' => 'cache.app', 'keys_lifetime' => 86_400, 'refresh_interval' => 60]]], secretScanning: false);
    }

    public function testTheDoctrineMappingIsPrepended(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'doctrine';
            }
        });
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([['orm' => ['mappings' => ['PhilApiKeysBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname(__DIR__, 2).'/src/Domain/Model',
            'prefix' => 'Phil\ApiKeysBundle\Domain\Model',
        ]]]]], $container->getExtensionConfig('doctrine'));

        $alone = new ContainerBuilder();
        $extension->prepend($alone);
        self::assertSame([], $alone->getExtensionConfig('doctrine'));
    }

    public function testTheDefaultLifetimeIsWithinTheLongest(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_api_keys.default_lifetime, 91 days, is longer than max_lifetime, 90 days.');

        $this->load(['default_lifetime' => 91, 'max_lifetime' => 90]);
    }

    public function testABadConfigurationIsRefused(): void
    {
        $refused = 0;

        foreach ([
            ['hash_key' => null],
            ['hash_key' => ''],
            ['hash_key' => []],
            ['hash_key' => [self::KEY, '']],
            ['secret_scanning' => ['github' => ['keys_url' => '']]],
            ['secret_scanning' => ['github' => ['http_client' => '']]],
            ['secret_scanning' => ['github' => ['cache' => '']]],
            ['secret_scanning' => ['github' => ['keys_lifetime' => 59]]],
            ['secret_scanning' => ['github' => ['refresh_interval' => 0]]],
            ['prefix' => 'A'],
            ['prefix' => 'a'],
            ['prefix' => 1],
            ['scopes' => ['Bad' => ['description' => 'd']]],
            ['scopes' => ['ok' => []]],
            ['scopes' => ['ok' => ['description' => '']]],
            ['scope_translation_domain' => ''],
            ['default_lifetime' => 0],
            ['max_lifetime' => 0],
            ['lifetime_choices' => [0]],
            ['last_used_throttle' => -1],
            ['last_used_address' => ''],
            ['store' => ''],
            ['entity_manager' => ''],
            ['clock' => ''],
            ['header' => ''],
            ['realm' => ''],
            ['purge_retention' => -1],
            ['member_page' => ['template' => '']],
            ['owner_types' => ['user' => ['provider' => 'p']]],
            ['owner_types' => ['Org' => ['provider' => 'p']]],
            ['owner_types' => ['my-org' => ['provider' => 'p']]],
            ['owner_types' => ['org' => []]],
            ['owner_types' => ['org' => ['provider' => '']]],
            ['owner_types' => ['org' => ['provider' => 'p', 'scopes' => ['']]]],
        ] as $config) {
            try {
                $this->load($config);
                self::fail(\sprintf('The configuration %s was accepted.', json_encode($config, \JSON_THROW_ON_ERROR)));
            } catch (InvalidConfigurationException) {
                ++$refused;
            }
        }

        self::assertSame(34, $refused);

        try {
            $this->load(['owner_types' => ['user' => ['provider' => 'p']]]);
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertStringContainsString('An owner type is a lowercase letter followed by up to 31 lowercase letters, digits or "_", other than "user"', $invalidConfigurationException->getMessage());
        }

        try {
            $this->load(['prefix' => 'A']);
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertStringContainsString('The prefix is 2 to 16 lowercase letters and digits, starting with a letter: "A".', $invalidConfigurationException->getMessage());
        }

        try {
            $this->load(['scopes' => ['Bad' => ['description' => 'd']]]);
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertStringContainsString('A scope name is a lowercase letter followed by up to 63 lowercase letters, digits, "_", ".", ":" or "-"', $invalidConfigurationException->getMessage());
        }

        $minimal = $this->load(['default_lifetime' => 1, 'max_lifetime' => 1, 'lifetime_choices' => [1], 'last_used_throttle' => 0, 'purge_retention' => 0, 'secret_scanning' => ['github' => ['keys_lifetime' => 60, 'refresh_interval' => 1]]]);
        self::assertTrue($minimal->hasDefinition(ApiTokens::class));
        self::assertTrue($this->load(['scopes' => ['a1' => ['description' => 'd']], 'prefix' => 'ab'])->hasDefinition(ScopeCatalogue::class));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load([['hash_key' => self::KEY, ...$config]], $container);

        return $container;
    }

    /**
     * The configuration by default, as the bundle hands it to the services.
     *
     * @return array{prefix: string, hash_key: non-empty-list<string>, scopes: array<array-key, array{description: string, label: string|null}>, owner_types: array<string, array{provider: string, scopes: list<string>}>, scope_translation_domain: string, default_lifetime: positive-int, max_lifetime: positive-int, lifetime_choices: list<positive-int>, last_used_throttle: non-negative-int, last_used_address: string|null, store: string, entity_manager: string, clock: string, header: string, realm: string, grant_user_roles: bool, other_authentications: bool, purge_retention: non-negative-int, secret_scanning: array{github: array{enabled: bool, keys_url: string, http_client: string, cache: string, keys_lifetime: positive-int, refresh_interval: positive-int}}, member_page: array{enabled: bool, template: string}}
     */
    private function defaults(): array
    {
        return [
            'prefix' => 'pat',
            'hash_key' => [self::KEY],
            'scopes' => [],
            'owner_types' => [],
            'scope_translation_domain' => 'phil_api_keys',
            'default_lifetime' => 90,
            'max_lifetime' => 365,
            'lifetime_choices' => [7, 30, 90, 365],
            'last_used_throttle' => 300,
            'last_used_address' => null,
            'store' => 'doctrine',
            'entity_manager' => 'default',
            'clock' => 'clock',
            'header' => 'Authorization',
            'realm' => 'api',
            'grant_user_roles' => true,
            'other_authentications' => false,
            'purge_retention' => 30,
            'secret_scanning' => ['github' => ['enabled' => false, 'keys_url' => GitHubPublicKeys::DEFAULT_URL, 'http_client' => 'http_client', 'cache' => 'cache.app', 'keys_lifetime' => 86_400, 'refresh_interval' => 60]],
            'member_page' => ['enabled' => false, 'template' => PhilApiKeysBundle::TEMPLATE],
        ];
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilApiKeysBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
