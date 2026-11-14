<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle\Tests\Unit;

use Phil\OpenBadges\Clr\ClrIssuer;
use Phil\OpenBadges\Contract\ContextLoader;
use Phil\OpenBadges\Contract\Signer;
use Phil\OpenBadges\Contract\VerificationMethods;
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadges\DataIntegrity\DidKeyMethods;
use Phil\OpenBadges\DataIntegrity\EddsaRdfc2022;
use Phil\OpenBadges\EndorsementIssuer;
use Phil\OpenBadges\Issuer;
use Phil\OpenBadges\Jwt\JwtSigner;
use Phil\OpenBadges\Key\Ed25519Key;
use Phil\OpenBadges\Key\Ed25519Signer;
use Phil\OpenBadges\Key\RsaSigner;
use Phil\OpenBadges\Status\StatusListIssuer;
use Phil\OpenBadges\Verification\CredentialVerifier;
use Phil\OpenBadgesBundle\Application\Contract\StatusLists;
use Phil\OpenBadgesBundle\Application\Securing;
use Phil\OpenBadgesBundle\Infrastructure\DependencyInjection\Contexts;
use Phil\OpenBadgesBundle\Infrastructure\DependencyInjection\Keys;
use Phil\OpenBadgesBundle\Infrastructure\DependencyInjection\OpenBadgesServices;
use Phil\OpenBadgesBundle\Infrastructure\Http\CredentialCheck;
use Phil\OpenBadgesBundle\Infrastructure\Http\IssuerController;
use Phil\OpenBadgesBundle\Infrastructure\Http\StatusListController;
use Phil\OpenBadgesBundle\Infrastructure\Http\VerificationController;
use Phil\OpenBadgesBundle\Infrastructure\Http\VerificationRateLimit;
use Phil\OpenBadgesBundle\Infrastructure\Routing\StatusListUrls;
use Phil\OpenBadgesBundle\Infrastructure\Verification\RemoteVerificationMethods;
use Phil\OpenBadgesBundle\PhilOpenBadgesBundle;
use Phil\OpenBadgesBundle\Tests\Unit\Fixture\Kit;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\SafeHttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The wiring the bundle declares, read on the `ContainerBuilder`, and the factories of its keys.
 */
#[CoversClass(PhilOpenBadgesBundle::class)]
#[CoversClass(OpenBadgesServices::class)]
#[CoversClass(Keys::class)]
#[CoversClass(StatusListUrls::class)]
final class WiringTest extends TestCase
{
    private const array CONFIG = ['issuer' => ['id' => Kit::ISSUER, 'name' => 'Example'], 'key' => ['secret' => 'seed']];

    public function testTheExtensionAlias(): void
    {
        self::assertSame('phil_open_badges', $this->extension()->getAlias());
    }

    public function testTheServices(): void
    {
        $container = $this->load(self::CONFIG);
        $issuer = new Reference(Issuer::class);
        $signer = new Reference(Signer::class);
        $method = Kit::ISSUER.'#key-1';

        self::assertSame([Kit::ISSUER, 'Example', null, null, null], $container->getDefinition(Issuer::class)->getArguments());
        self::assertSame([Keys::class, 'signer'], $container->getDefinition(Signer::class)->getFactory());
        self::assertSame(['EdDSA', 'seed', null], $container->getDefinition(Signer::class)->getArguments());
        self::assertEquals([$issuer, $signer, $method], $container->getDefinition(CredentialIssuer::class)->getArguments());
        self::assertEquals([$issuer], $container->getDefinition(EndorsementIssuer::class)->getArguments());
        self::assertEquals([$issuer], $container->getDefinition(ClrIssuer::class)->getArguments());
        self::assertEquals([$issuer], $container->getDefinition(StatusListIssuer::class)->getArguments());
        self::assertEquals([$signer, $method], $container->getDefinition(JwtSigner::class)->getArguments());
        self::assertEquals([new Definition(ContextLoader::class)->setFactory([Contexts::class, 'loader'])->setArguments([[]])], $container->getDefinition(EddsaRdfc2022::class)->getArguments());
        self::assertEquals([$signer, $method, 'jwt', new Reference('clock'), new Reference(EddsaRdfc2022::class)], $container->getDefinition(Securing::class)->getArguments());
        self::assertSame([Keys::class, 'methods'], $container->getDefinition(OpenBadgesServices::KNOWN_METHODS)->getFactory());
        self::assertEquals([$signer, $method, []], $container->getDefinition(OpenBadgesServices::KNOWN_METHODS)->getArguments());
        self::assertSame(OpenBadgesServices::KNOWN_METHODS, (string) $container->getAlias(VerificationMethods::class));
        self::assertFalse($container->hasDefinition(OpenBadgesServices::HTTP_CLIENT));
        self::assertEquals([new Reference(VerificationMethods::class), $method, new Reference(EddsaRdfc2022::class)], $container->getDefinition(CredentialVerifier::class)->getArguments());
        self::assertEquals([new Reference('router'), false], $container->getDefinition(StatusListUrls::class)->getArguments());
        self::assertSame([Keys::class, 'profile'], $container->getDefinition(OpenBadgesServices::PROFILE)->getFactory());
        self::assertEquals([$issuer, $signer, $method], $container->getDefinition(OpenBadgesServices::PROFILE)->getArguments());
        self::assertSame([Keys::class, 'jwks'], $container->getDefinition(OpenBadgesServices::KEYS)->getFactory());
        self::assertEquals([$signer, $method], $container->getDefinition(OpenBadgesServices::KEYS)->getArguments());
        self::assertEquals([new Reference(OpenBadgesServices::PROFILE), new Reference(OpenBadgesServices::KEYS), 3600], $container->getDefinition(IssuerController::class)->getArguments());
        self::assertTrue($container->getDefinition(IssuerController::class)->hasTag('controller.service_arguments'));

        $lists = new Reference(StatusLists::class, ContainerInterface::NULL_ON_INVALID_REFERENCE);
        self::assertEquals([new Reference(StatusListIssuer::class), new Reference(Securing::class), new Reference(StatusListUrls::class), new Reference('clock'), 131_072, 300, $lists], $container->getDefinition(StatusListController::class)->getArguments());
        self::assertTrue($container->getDefinition(StatusListController::class)->hasTag('controller.service_arguments'));

        self::assertEquals([new Reference(CredentialVerifier::class), new Reference('clock'), new Reference(StatusListUrls::class), $lists], $container->getDefinition(CredentialCheck::class)->getArguments());
        self::assertEquals([new Reference(CredentialCheck::class), new Reference('twig'), '@PhilOpenBadges/layout.html.twig', 2_097_152], $container->getDefinition(VerificationController::class)->getArguments());
        self::assertTrue($container->getDefinition(VerificationController::class)->hasTag('controller.service_arguments'));
        self::assertFalse($container->hasDefinition(VerificationRateLimit::class));
    }

    public function testTheChecksOfThePageCountAgainstTheLimiterNamed(): void
    {
        $container = $this->load(['verification' => ['rate_limit' => 'badge.checks-1']] + self::CONFIG, rateLimit: true);

        self::assertSame(['badge.checks-1'], $container->getDefinition(VerificationRateLimit::class)->getArguments());
        self::assertSame([['event' => 'kernel.request', 'method' => 'onKernelRequest']], $container->getDefinition(VerificationRateLimit::class)->getTag('kernel.event_listener'));
        self::assertFalse($this->load(['verification' => ['rate_limit' => 'checks']] + self::CONFIG, twig: false, rateLimit: true)->hasDefinition(VerificationRateLimit::class));
        self::assertTrue($this->load(['verification' => ['rate_limit' => str_repeat('a', 64)]] + self::CONFIG, rateLimit: true)->hasDefinition(VerificationRateLimit::class));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidLimiters(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'a space' => ['badge checks'];
        yield 'a slash' => ['badge/checks'];
        yield 'a trailing line' => ["checks\n"];
        yield 'a number' => [12];
    }

    #[DataProvider('invalidLimiters')]
    public function testALimiterOfAnotherShapeIsRefused(mixed $limiter): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The name of a limiter is 1 to 64 letters, digits, "_", "." and "-";');

        $this->load(['verification' => ['rate_limit' => $limiter]] + self::CONFIG, rateLimit: true);
    }

    public function testALimiterNeedsTheRateLimitBundle(): void
    {
        foreach ([false, null] as $registered) {
            try {
                $this->load(['verification' => ['rate_limit' => 'checks']] + self::CONFIG, rateLimit: $registered);
                self::fail('The limit needs PhilRateLimitBundle.');
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertSame('phil_open_badges.verification.rate_limit: a limit on the verification page needs PhilRateLimitBundle, from phil/rate-limit-bundle: register it too.', $invalidConfigurationException->getMessage());
            }
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function missingIssuers(): iterable
    {
        yield 'nothing' => [[], 'issuer.id, issuer.name, key.secret are missing.'];
        yield 'no key' => [['issuer' => ['id' => Kit::ISSUER, 'name' => 'Example']], 'key.secret is missing.'];
        yield 'no name' => [['issuer' => ['id' => Kit::ISSUER], 'key' => ['secret' => 'seed']], 'issuer.name is missing.'];
        yield 'no identifier' => [['issuer' => ['name' => 'Example'], 'key' => ['secret' => 'seed']], 'issuer.id is missing.'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('missingIssuers')]
    public function testWithoutItsIssuerTheBuildSaysWhatToSet(array $config, string $missing): void
    {
        $this->expectExceptionObject(new InvalidConfigurationException('PhilOpenBadgesBundle signs as an issuer the application names: set phil_open_badges.issuer.id, phil_open_badges.issuer.name and phil_open_badges.key.secret in config/packages/phil_open_badges.yaml; '.$missing));

        $this->load($config);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidAddresses(): iterable
    {
        yield 'an identifier on localhost' => [['id' => 'https://localhost/issuer'], 'phil_open_badges.issuer.id: '];
        yield 'an identifier with credentials' => [['id' => 'https://user@example.com/issuer'], 'phil_open_badges.issuer.id: '];
        yield 'an identifier on an IP address' => [['id' => 'https://192.0.2.1/issuer'], 'phil_open_badges.issuer.id: '];
        yield 'a web site on localhost' => [['url' => 'http://app.localhost'], 'phil_open_badges.issuer.url: '];
        yield 'an image of no address' => [['image' => 'logo.png'], 'phil_open_badges.issuer.image: '];
    }

    /**
     * @param array<string, string> $issuer
     */
    #[DataProvider('invalidAddresses')]
    public function testTheAddressesOfTheIssuerAreReadAsABrowserReadsThem(array $issuer, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load(['issuer' => $issuer + self::CONFIG['issuer']] + self::CONFIG);
    }

    public function testLocalhostIsAnIssuerHostWhenAllowed(): void
    {
        $container = $this->load(['allow_localhost' => true, 'issuer' => ['id' => 'https://localhost/issuer', 'name' => 'Example', 'url' => 'http://app.localhost', 'image' => 'https://app.localhost/logo.png']] + self::CONFIG);

        self::assertSame(['https://localhost/issuer', 'Example', 'http://app.localhost', null, 'https://app.localhost/logo.png'], $container->getDefinition(Issuer::class)->getArguments());
    }

    public function testTheConfigurationIsPassedOn(): void
    {
        $container = $this->load([
            'issuer' => ['id' => Kit::ISSUER, 'name' => 'Example', 'url' => 'https://example.com', 'email' => 'badges@example.com', 'image' => 'https://example.com/logo.png'],
            'key' => ['algorithm' => 'RS256', 'secret' => 'pem', 'passphrase' => 'pass'],
            'clock' => 'app.clock',
            'max_age' => 60,
            'trusted_keys' => ['did:web:partner.example#key-1' => 'z6Mk'],
            'contexts' => ['https://purl.imsglobal.org/spec/ob/v3p0/context-3.0.3.json' => '/app/config/jsonld/ob-v3p0-context-3.0.3.json'],
            'status_lists' => ['service' => 'app.lists', 'size' => 262_144, 'max_age' => 30],
            'verification' => ['layout' => 'base.html.twig', 'max_upload' => 4096],
        ]);

        self::assertSame([Kit::ISSUER, 'Example', 'https://example.com', 'badges@example.com', 'https://example.com/logo.png'], $container->getDefinition(Issuer::class)->getArguments());
        self::assertSame(['RS256', 'pem', 'pass'], $container->getDefinition(Signer::class)->getArguments());
        self::assertSame(['did:web:partner.example#key-1' => 'z6Mk'], $container->getDefinition(OpenBadgesServices::KNOWN_METHODS)->getArgument(2));
        self::assertSame(60, $container->getDefinition(IssuerController::class)->getArgument(2));
        $loader = $container->getDefinition(EddsaRdfc2022::class)->getArgument(0);
        self::assertInstanceOf(Definition::class, $loader);
        self::assertSame([['https://purl.imsglobal.org/spec/ob/v3p0/context-3.0.3.json' => '/app/config/jsonld/ob-v3p0-context-3.0.3.json']], $loader->getArguments());
        self::assertEquals(new Reference('app.clock'), $container->getDefinition(Securing::class)->getArgument(3));
        self::assertSame([262_144, 30], \array_slice($container->getDefinition(StatusListController::class)->getArguments(), 4, 2));
        self::assertEquals(new Reference('app.lists', ContainerInterface::NULL_ON_INVALID_REFERENCE), $container->getDefinition(StatusListController::class)->getArgument(6));
        self::assertSame(['base.html.twig', 4096], \array_slice($container->getDefinition(VerificationController::class)->getArguments(), 2));
        self::assertNull($this->load(['status_lists' => ['service' => null]] + self::CONFIG)->getDefinition(StatusListController::class)->getArgument(6));
    }

    public function testTheKeysOfOtherIssuersAreReadWhenAsked(): void
    {
        $container = $this->load(['remote_issuers' => true] + self::CONFIG);

        self::assertSame(RemoteVerificationMethods::class, $container->getDefinition(VerificationMethods::class)->getClass());
        self::assertEquals([new Reference(OpenBadgesServices::KNOWN_METHODS), new Reference(OpenBadgesServices::HTTP_CLIENT), new Reference('cache.app'), [], 3600, 60, false], $container->getDefinition(VerificationMethods::class)->getArguments());
        self::assertSame(SafeHttpClient::class, $container->getDefinition(OpenBadgesServices::HTTP_CLIENT)->getClass());
        self::assertEquals([new Reference('http_client', ContainerInterface::NULL_ON_INVALID_REFERENCE), new Definition(Limits::class, [3.0, 5.0, 65_536, 3, 'PhilOpenBadges/0.1'])], $container->getDefinition(OpenBadgesServices::HTTP_CLIENT)->getArguments());
        self::assertSame([['method' => 'reset']], $container->getDefinition(OpenBadgesServices::HTTP_CLIENT)->getTag('kernel.reset'));
        self::assertEquals([new Reference(VerificationMethods::class), Kit::METHOD, new Reference(EddsaRdfc2022::class)], $container->getDefinition(CredentialVerifier::class)->getArguments());

        $container = $this->load(['allow_localhost' => true, 'remote_issuers' => [
            'trusted' => ['https://partner.example/issuer', 'https://badges.localhost/issuer', 'did:web:partner.example', 'did:web:partner.example%3A8443:badges'],
            'cache' => 'cache.badges',
            'ttl' => 600,
            'failure_ttl' => 5,
            'timeout' => 1.5,
            'max_duration' => 2.5,
            'max_bytes' => 4096,
            'user_agent' => 'ExampleBadges/1.0 (+https://example.com/bot)',
        ]] + self::CONFIG);

        self::assertEquals([new Reference(OpenBadgesServices::KNOWN_METHODS), new Reference(OpenBadgesServices::HTTP_CLIENT), new Reference('cache.badges'), ['https://partner.example/issuer', 'https://badges.localhost/issuer', 'did:web:partner.example', 'did:web:partner.example%3A8443:badges'], 600, 5, true], $container->getDefinition(VerificationMethods::class)->getArguments());
        self::assertEquals(new Definition(Limits::class, [1.5, 2.5, 4096, 3, 'ExampleBadges/1.0 (+https://example.com/bot)']), $container->getDefinition(OpenBadgesServices::HTTP_CLIENT)->getArgument(1));
    }

    /**
     * @return iterable<string, array{string, non-empty-string}>
     */
    public static function untrustableIssuers(): iterable
    {
        yield 'not an address' => ['partner', 'phil_open_badges.remote_issuers.trusted.0: '];
        yield 'plain HTTP' => ['http://partner.example/issuer', 'phil_open_badges.remote_issuers.trusted.0: an issuer whose profile is read is an HTTPS address without a fragment, or a did:web; "http://partner.example/issuer" is not.'];
        yield 'a fragment' => ['https://partner.example/issuer#key-1', 'phil_open_badges.remote_issuers.trusted.0: an issuer whose profile is read is an HTTPS address without a fragment, or a did:web; "https://partner.example/issuer#key-1" is not.'];
        yield 'localhost' => ['https://badges.localhost/issuer', 'phil_open_badges.remote_issuers.trusted.0: '];
        yield 'a did:web with a fragment' => ['did:web:partner.example#key-1', 'phil_open_badges.remote_issuers.trusted.0: "did:web:partner.example#key-1" is not a did:web whose document can be read: "did:web:partner.example#key-1" is not a did:web identifier.'];
        yield 'a did:web on localhost' => ['did:web:badges.localhost', 'phil_open_badges.remote_issuers.trusted.0: "did:web:badges.localhost" is not a did:web whose document can be read: '];
    }

    public function testTheIssuersAfterAValidDidWebAreChecked(): void
    {
        try {
            $this->load(['remote_issuers' => ['trusted' => ['did:web:partner.example', 'http://partner.example/issuer']]] + self::CONFIG);
            self::fail('Refused.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertStringStartsWith('phil_open_badges.remote_issuers.trusted.1: an issuer whose profile is read is an HTTPS address', $invalidConfigurationException->getMessage());
        }
    }

    /**
     * @param non-empty-string $message
     */
    #[DataProvider('untrustableIssuers')]
    public function testATrustedIssuerIsAnHttpsAddress(string $issuer, string $message): void
    {
        try {
            $this->load(['remote_issuers' => ['trusted' => [$issuer]]] + self::CONFIG);
            self::fail('Refused.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertStringStartsWith($message, $invalidConfigurationException->getMessage());
            self::assertSame(0, $invalidConfigurationException->getCode());
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, bool}>
     */
    public static function bounds(): iterable
    {
        yield 'keys kept a minute' => [['remote_issuers' => ['ttl' => 60]], true];
        yield 'keys kept less' => [['remote_issuers' => ['ttl' => 59]], false];
        yield 'keys kept a day' => [['remote_issuers' => ['ttl' => 86_400]], true];
        yield 'keys kept longer' => [['remote_issuers' => ['ttl' => 86_401]], false];
        yield 'a refusal kept a second' => [['remote_issuers' => ['failure_ttl' => 1]], true];
        yield 'a refusal not kept' => [['remote_issuers' => ['failure_ttl' => 0]], false];
        yield 'a refusal kept an hour' => [['remote_issuers' => ['failure_ttl' => 3600]], true];
        yield 'a refusal kept longer' => [['remote_issuers' => ['failure_ttl' => 3601]], false];
        yield 'the shortest silence' => [['remote_issuers' => ['timeout' => 0.1]], true];
        yield 'no silence' => [['remote_issuers' => ['timeout' => 0.0]], false];
        yield 'the longest silence' => [['remote_issuers' => ['timeout' => 60.0]], true];
        yield 'a longer silence' => [['remote_issuers' => ['timeout' => 60.5]], false];
        yield 'the shortest read' => [['remote_issuers' => ['max_duration' => 0.1]], true];
        yield 'no read' => [['remote_issuers' => ['max_duration' => 0.0]], false];
        yield 'the longest read' => [['remote_issuers' => ['max_duration' => 120.0]], true];
        yield 'a longer read' => [['remote_issuers' => ['max_duration' => 120.5]], false];
        yield 'the smallest profile' => [['remote_issuers' => ['max_bytes' => 1024]], true];
        yield 'a smaller profile' => [['remote_issuers' => ['max_bytes' => 1023]], false];
        yield 'the largest profile' => [['remote_issuers' => ['max_bytes' => 1_048_576]], true];
        yield 'a larger profile' => [['remote_issuers' => ['max_bytes' => 1_048_577]], false];
        yield 'a user agent on two lines' => [['remote_issuers' => ['user_agent' => "a\nb"]], false];
        yield 'a user agent not a string' => [['remote_issuers' => ['user_agent' => 12]], false];
        yield 'an empty cache' => [['remote_issuers' => ['cache' => '']], false];
        yield 'an EdDSA key' => [['key' => ['algorithm' => 'EdDSA', 'secret' => 's']], true];
        yield 'an RS256 key' => [['key' => ['algorithm' => 'RS256', 'secret' => 's']], true];
        yield 'another algorithm' => [['key' => ['algorithm' => 'ES256', 'secret' => 's']], false];
        yield 'tokens' => [['securing' => 'jwt'], true];
        yield 'proofs' => [['securing' => 'data_integrity'], true];
        yield 'another securing' => [['securing' => 'ldp'], false];
        yield 'profiles never cached' => [['max_age' => 0], true];
        yield 'a negative cache' => [['max_age' => -1], false];
        yield 'the smallest list' => [['status_lists' => ['size' => 131_072]], true];
        yield 'a list too small' => [['status_lists' => ['size' => 131_071]], false];
        yield 'a list valid one second' => [['status_lists' => ['max_age' => 1]], true];
        yield 'a list valid no time' => [['status_lists' => ['max_age' => 0]], false];
        yield 'the smallest upload' => [['verification' => ['max_upload' => 1024]], true];
        yield 'an upload too small' => [['verification' => ['max_upload' => 1023]], false];
    }

    /**
     * @param array<string, mixed> $change
     */
    #[DataProvider('bounds')]
    public function testTheBoundsOfTheConfiguration(array $change, bool $accepted): void
    {
        try {
            $this->load($change + self::CONFIG);
            self::assertTrue($accepted, 'The configuration was expected to be refused.');
        } catch (InvalidConfigurationException) {
            self::assertFalse($accepted, 'The configuration was expected to be accepted.');
        }
    }

    public function testThePageNeedsTwig(): void
    {
        self::assertFalse($this->load(self::CONFIG, twig: false)->hasDefinition(VerificationController::class));
        self::assertFalse($this->load(self::CONFIG, twig: null)->hasDefinition(CredentialCheck::class));
        self::assertFalse($this->load(['verification' => false] + self::CONFIG)->hasDefinition(VerificationController::class));
    }

    public function testThePrependSaysWhetherTwigIsThere(): void
    {
        $bundle = new PhilOpenBadgesBundle();
        $container = new ContainerBuilder();
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);
        $container->registerExtension($extension);

        $extension instanceof PrependExtensionInterface ? $extension->prepend($container) : self::fail('The extension prepends.');
        self::assertFalse($container->getParameter(PhilOpenBadgesBundle::TWIG_PARAMETER));
        self::assertFalse($container->getParameter(PhilOpenBadgesBundle::RATE_LIMIT_PARAMETER));

        $rateLimit = self::createStub(ExtensionInterface::class);
        $rateLimit->method('getAlias')->willReturn('phil_rate_limit');
        $container->registerExtension($rateLimit);
        $extension->prepend($container);
        self::assertTrue($container->getParameter(PhilOpenBadgesBundle::RATE_LIMIT_PARAMETER));
        self::assertFalse($container->getParameter(PhilOpenBadgesBundle::TWIG_PARAMETER));

        $twig = self::createStub(ExtensionInterface::class);
        $twig->method('getAlias')->willReturn('twig');
        $container->registerExtension($twig);
        $extension->prepend($container);
        self::assertTrue($container->getParameter(PhilOpenBadgesBundle::TWIG_PARAMETER));
    }

    public function testAnInvalidIssuerFailsTheBuild(): void
    {
        $this->expectExceptionObject(new InvalidConfigurationException('phil_open_badges.issuer.id: "issuer" is not an http or https address.'));
        $this->load(['issuer' => ['id' => 'issuer', 'name' => 'Example']] + self::CONFIG);
    }

    public function testDataIntegrityNeedsAnEdDsaKey(): void
    {
        self::assertSame('data_integrity', $this->load(['securing' => 'data_integrity'] + self::CONFIG)->getDefinition(Securing::class)->getArgument(2));

        $this->expectExceptionObject(new InvalidConfigurationException('phil_open_badges.securing: Data Integrity proofs are signed with an EdDSA key.'));
        $this->load(['securing' => 'data_integrity', 'key' => ['algorithm' => 'RS256', 'secret' => 'pem']] + self::CONFIG);
    }

    public function testTheKeys(): void
    {
        self::assertInstanceOf(Ed25519Signer::class, Keys::signer('EdDSA', str_repeat("\1", 32)));
        $pem = $this->rsaPem();
        self::assertInstanceOf(RsaSigner::class, Keys::signer('RS256', $pem));

        $methods = Keys::methods(Kit::signer(), Kit::METHOD, ['did:web:partner.example#key-1' => 'z6MkrJVnaZkeFzdQyMZu1cgjg7k1pZZ6pvBQ7XJPt4swbTQ2', Kit::METHOD => 'z6MkrJVnaZkeFzdQyMZu1cgjg7k1pZZ6pvBQ7XJPt4swbTQ2']);
        self::assertInstanceOf(DidKeyMethods::class, $methods);
        $own = $methods->key(Kit::METHOD);
        self::assertInstanceOf(Ed25519Key::class, $own);
        self::assertSame(Kit::signer()->verificationKey()->publicKey, $own->publicKey);
        $partner = $methods->key('did:web:partner.example#key-1');
        self::assertInstanceOf(Ed25519Key::class, $partner);
        self::assertSame('z6MkrJVnaZkeFzdQyMZu1cgjg7k1pZZ6pvBQ7XJPt4swbTQ2', $partner->toMultibase());

        self::assertSame(Kit::issuer()->profileDocument([Kit::METHOD => Kit::signer()->verificationKey()]), Keys::profile(Kit::issuer(), Kit::signer(), Kit::METHOD));
        self::assertSame(Kit::issuer()->profileDocument(), Keys::profile(Kit::issuer(), RsaSigner::fromPem($pem), Kit::METHOD));
        self::assertSame(['keys' => [[...Kit::signer()->publicJwk(), 'kid' => Kit::METHOD, 'alg' => 'EdDSA', 'use' => 'sig']]], Keys::jwks(Kit::signer(), Kit::METHOD));
    }

    public function testTheUrlsOfTheStatusLists(): void
    {
        $urls = Kit::urls();

        self::assertSame('https://example.com/open-badges/status/main', $urls->url('main'));
        self::assertSame(['statusListCredential' => 'https://example.com/open-badges/status/main', 'index' => 4, 'purpose' => 'suspension'], (array) $urls->entry('main', 4, 'suspension'));
        self::assertSame('revocation', $urls->entry('main', 4)->purpose);
        self::assertSame('main', $urls->listOf('https://example.com/open-badges/status/main'));
        self::assertSame(str_repeat('a', 64), $urls->listOf('https://example.com/open-badges/status/'.str_repeat('a', 64)));

        foreach (['https://example.com/open-badges/status/', 'https://example.com/open-badges/status/'.str_repeat('a', 65), 'https://example.com/open-badges/status/-a', 'https://example.com/open-badges/status/a/b', 'https://partner.example/open-badges/status/main', 'http://example.com/open-badges/status/main', 'https://example.com/other/status/main', 'https://example.com/open-badges/status/main#x', 'https://example.com', 'https://example.com/open-badges/status/main?x=1', "https://example.com/open-badges/status/main\n", 'https://example.com\\open-badges\\status\\main', 'https://example.com/open-badges/x/../status/main', 'https://user@example.com/open-badges/status/main', 'https://example.com@partner.example/open-badges/status/main', 'https://EXAMPLE.com/open-badges/status/main', 'open-badges/status/main', '/open-badges/status/main', ''] as $url) {
            self::assertNull($urls->listOf($url), $url);
        }
    }

    public function testTheListOfALocalhostUrlIsReadOnlyWhenLocalhostIsAllowed(): void
    {
        self::assertNull(Kit::urls('app.localhost')->listOf('https://app.localhost/open-badges/status/main'));
        self::assertSame('main', Kit::urls('app.localhost', true)->listOf('https://app.localhost/open-badges/status/main'));
        self::assertSame('https://app.localhost/open-badges/status/main', Kit::urls('app.localhost')->url('main'));
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilOpenBadgesBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config, ?bool $twig = true, ?bool $rateLimit = null): ContainerBuilder
    {
        $container = new ContainerBuilder();

        if (null !== $twig) {
            $container->setParameter(PhilOpenBadgesBundle::TWIG_PARAMETER, $twig);
        }

        if (null !== $rateLimit) {
            $container->setParameter(PhilOpenBadgesBundle::RATE_LIMIT_PARAMETER, $rateLimit);
        }

        $this->extension()->load([$config], $container);

        return $container;
    }

    private function rsaPem(): string
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        openssl_pkey_export($key, $pem);
        self::assertIsString($pem);

        return $pem;
    }
}
