<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogleBundle\Tests\Unit\Infrastructure;

use Phil\IdentityProviderBundle\Domain\Contract\AccountRepository;
use Phil\IdentityProviderBundle\Domain\Contract\LinkedIdentityRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryAccountRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryLinkedIdentityRepository;
use Phil\IdentityProviderGoogleBundle\Application\Contract\SubjectHolds;
use Phil\IdentityProviderGoogleBundle\Application\GoogleSettings;
use Phil\IdentityProviderGoogleBundle\Application\GoogleSignIn;
use Phil\IdentityProviderGoogleBundle\Application\SecurityEventReceiver;
use Phil\IdentityProviderGoogleBundle\Application\TransmitterSettings;
use Phil\IdentityProviderGoogleBundle\Infrastructure\Cache\CacheSubjectHolds;
use Phil\IdentityProviderGoogleBundle\Infrastructure\DependencyInjection\GoogleServices;
use Phil\IdentityProviderGoogleBundle\Infrastructure\Federation\GoogleProvider;
use Phil\IdentityProviderGoogleBundle\Infrastructure\Http\RiscController;
use Phil\IdentityProviderGoogleBundle\PhilIdentityProviderGoogleBundle;
use Phil\IdentityProviderGoogleBundle\Tests\Fixture\FakeGoogle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Routing\Loader\PhpFileLoader;

#[CoversClass(PhilIdentityProviderGoogleBundle::class)]
#[CoversClass(GoogleServices::class)]
final class PhilIdentityProviderGoogleBundleTest extends TestCase
{
    public function testTheProviderIsTaggedForTheIdentityBundle(): void
    {
        $container = $this->container([]);

        self::assertSame([[]], $container->getDefinition(GoogleProvider::class)->getTag('phil_identity_provider.external_provider'));
        self::assertSame([['method' => 'reset']], $container->getDefinition(GoogleServices::TRANSPORT)->getTag('kernel.reset'));
        self::assertSame([['method' => 'reset']], $container->getDefinition(GoogleServices::DISCOVERY)->getTag('kernel.reset'));
        self::assertTrue($container->getDefinition(RiscController::class)->hasTag('controller.service_arguments'));
        self::assertFalse($container->hasDefinition(SecurityEventReceiver::class));
        self::assertFalse($container->has(SubjectHolds::class));
        self::assertNull($container->getDefinition(RiscController::class)->getArgument(0));

        $container->compile(true);
        $settings = $container->get(GoogleSettings::class);
        self::assertInstanceOf(GoogleSettings::class, $settings);
        self::assertSame(FakeGoogle::CLIENT_ID, $settings->clientId);
        self::assertSame([], $settings->hostedDomains);
        self::assertFalse($settings->requestProfile);
        self::assertSame('select_account', $settings->prompt);
        self::assertSame(60, $settings->clockSkew);
        self::assertSame(600, $settings->attemptLifetime);
        self::assertInstanceOf(GoogleSignIn::class, $container->get(GoogleSignIn::class));
        self::assertInstanceOf(GoogleProvider::class, $container->get(GoogleProvider::class));
    }

    public function testTheSettingsFollowTheConfiguration(): void
    {
        $container = $this->container(['hosted_domains' => ['example.com'], 'profile' => true, 'prompt' => 'default', 'clock_skew' => 0, 'attempt_lifetime' => 60]);
        $container->compile(true);

        $settings = $container->get(GoogleSettings::class);

        self::assertInstanceOf(GoogleSettings::class, $settings);
        self::assertSame(['example.com'], $settings->hostedDomains);
        self::assertTrue($settings->requestProfile);
        self::assertNull($settings->prompt);
        self::assertSame(0, $settings->clockSkew);
        self::assertSame(60, $settings->attemptLifetime);
    }

    public function testTheReceiverOfSecurityEvents(): void
    {
        $container = $this->container(['risc' => ['enabled' => true, 'max_age' => 3600, 'holds_pool' => 'holds_pool']]);

        self::assertEquals(new Reference(SecurityEventReceiver::class), $container->getDefinition(RiscController::class)->getArgument(0));
        self::assertEquals(new Reference('holds_pool'), $container->getDefinition(CacheSubjectHolds::class)->getArgument(0));
        self::assertSame([['method' => 'reset']], $container->getDefinition(GoogleServices::RISC_DISCOVERY)->getTag('kernel.reset'));
        self::assertEquals(new Reference(SubjectHolds::class), $container->getDefinition(GoogleSignIn::class)->getArgument(8));

        $container->compile(true);
        $transmitter = $container->get(TransmitterSettings::class);
        self::assertInstanceOf(TransmitterSettings::class, $transmitter);
        self::assertSame([FakeGoogle::CLIENT_ID], $transmitter->audiences);
        self::assertSame(3600, $transmitter->maxAge);
        self::assertInstanceOf(SecurityEventReceiver::class, $container->get(SecurityEventReceiver::class));
        self::assertInstanceOf(RiscController::class, $container->get(RiscController::class));
    }

    public function testTheAudiencesAndTheCachePoolOfTheHolds(): void
    {
        $container = $this->container(['risc' => ['enabled' => true, 'audiences' => ['a', 'b']]]);

        self::assertEquals(new Reference('cache.app'), $container->getDefinition(CacheSubjectHolds::class)->getArgument(0));
        $container->compile(true);
        $transmitter = $container->get(TransmitterSettings::class);
        self::assertInstanceOf(TransmitterSettings::class, $transmitter);
        self::assertSame(['a', 'b'], $transmitter->audiences);
        self::assertSame(604_800, $transmitter->maxAge);
    }

    public function testHijackedAccountsMayBeLetIn(): void
    {
        $container = $this->container(['risc' => ['enabled' => true, 'hold_hijacked' => false]]);

        self::assertFalse($container->hasDefinition(CacheSubjectHolds::class));
        self::assertNull($container->getDefinition(GoogleSignIn::class)->getArgument(8));
    }

    public function testAnHttpClientOfTheApplicationIsWrapped(): void
    {
        $container = $this->container(['http_client' => 'my_client']);
        $client = $container->getDefinition(GoogleServices::TRANSPORT)->getArgument(0);

        self::assertInstanceOf(Definition::class, $client);
        self::assertEquals(new Reference('my_client'), $client->getArgument(0));
        $default = $this->container([])->getDefinition(GoogleServices::TRANSPORT)->getArgument(0);
        self::assertInstanceOf(Definition::class, $default);
        self::assertNull($default->getArgument(0));
    }

    public function testTheEndpointIsPublicToTheRouter(): void
    {
        self::assertTrue($this->loaded([])->getDefinition(RiscController::class)->isPublic());
    }

    public function testEveryPromptOfGoogleIsAccepted(): void
    {
        foreach ([...GoogleSettings::PROMPTS, 'default'] as $prompt) {
            $container = $this->container(['prompt' => $prompt]);
            $container->compile(true);
            $settings = $container->get(GoogleSettings::class);

            self::assertInstanceOf(GoogleSettings::class, $settings);
            self::assertSame('default' === $prompt ? null : $prompt, $settings->prompt);
        }
    }

    public function testTheBundleCanBeTurnedOff(): void
    {
        $container = $this->container(['enabled' => false]);

        self::assertFalse($container->hasDefinition(GoogleProvider::class));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalid(): iterable
    {
        yield 'another prompt' => [['prompt' => 'login']];
        yield 'a skew too large' => [['clock_skew' => 301]];
        yield 'a lifetime too short' => [['attempt_lifetime' => 59]];
        yield 'an age too short' => [['risc' => ['max_age' => 59]]];
        yield 'an empty client ID' => [['client_id' => '']];
        yield 'a negative skew' => [['clock_skew' => -1]];
        yield 'a lifetime too long' => [['attempt_lifetime' => 3601]];
        yield 'an age too long' => [['risc' => ['max_age' => 2_592_001]]];
        yield 'an empty audience' => [['risc' => ['audiences' => ['']]]];
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function bounds(): iterable
    {
        yield 'the largest skew' => [['clock_skew' => 300]];
        yield 'the longest lifetime' => [['attempt_lifetime' => 3600]];
        yield 'the shortest age' => [['risc' => ['max_age' => 60]]];
        yield 'the longest age' => [['risc' => ['max_age' => 2_592_000]]];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('bounds')]
    public function testTheBoundsOfTheConfigurationAreAccepted(array $config): void
    {
        self::assertTrue($this->container($config)->hasDefinition(GoogleProvider::class));
    }

    public function testTheServicesOfSymfonysOpenIdConnectPieces(): void
    {
        $container = $this->container(['clock_skew' => 30, 'risc' => ['enabled' => true]]);
        $transport = new Reference(GoogleServices::TRANSPORT);

        self::assertEquals([$transport, new Reference('cache.app'), 'https://accounts.google.com/.well-known/openid-configuration', 'https://accounts.google.com', 86_400, 'phil_identity_provider_google.discovery', ['authorization_endpoint', 'token_endpoint', 'jwks_uri']], $container->getDefinition(GoogleServices::DISCOVERY)->getArguments());
        self::assertEquals([new Reference(GoogleServices::DISCOVERY), new Reference('cache.app'), $transport, ['RS256'], 3600, true, new Reference('clock')], $container->getDefinition(GoogleServices::SIGNATURES)->getArguments());
        self::assertEquals([$transport, new Reference('cache.app'), 'https://accounts.google.com/.well-known/risc-configuration', 'https://accounts.google.com/', 86_400, 'phil_identity_provider_google.risc_discovery', ['jwks_uri']], $container->getDefinition(GoogleServices::RISC_DISCOVERY)->getArguments());
        self::assertEquals([new Reference(GoogleServices::RISC_DISCOVERY), new Reference('cache.app'), $transport, ['RS256'], 3600, true, new Reference('clock')], $container->getDefinition(GoogleServices::RISC_SIGNATURES)->getArguments());
        self::assertTrue($container->getDefinition(GoogleServices::RISC_DISCOVERY)->hasTag('kernel.reset'));

        $signIn = $container->getDefinition(GoogleSignIn::class)->getArguments();
        $client = $signIn[1];
        $idTokens = $signIn[3];
        self::assertInstanceOf(Definition::class, $client);
        self::assertInstanceOf(Definition::class, $idTokens);
        self::assertSame(FakeGoogle::CLIENT_ID, $client->getArgument(2));
        $secret = $client->getArgument(3);
        self::assertInstanceOf(Definition::class, $secret);
        self::assertSame([FakeGoogle::CLIENT_SECRET], $secret->getArguments());
        self::assertEquals([new Reference('clock'), 30], $idTokens->getArguments());
        self::assertEquals(new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE), $signIn[9]);

        $receiver = $container->getDefinition(SecurityEventReceiver::class)->getArguments();
        $replays = $receiver[1];
        self::assertInstanceOf(Definition::class, $replays);
        self::assertEquals([new Reference('cache.app'), 604_800], $replays->getArguments());
        self::assertEquals(new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE), $receiver[5]);
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalid')]
    public function testAnInvalidConfigurationIsRefused(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->container($config);
    }

    public function testTheRouteOfTheReceiver(): void
    {
        $routes = new PhpFileLoader(new FileLocator(\dirname(__DIR__, 3).'/config'))->load('routes.php');
        $route = $routes->get(RiscController::ROUTE);

        self::assertNotNull($route);
        self::assertSame('/risc', $route->getPath());
        self::assertSame(['POST'], $route->getMethods());
        self::assertSame(RiscController::class, $route->getDefault('_controller'));
        self::assertTrue($route->getDefault('_stateless'));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function container(array $config): ContainerBuilder
    {
        $container = $this->loaded($config);

        foreach ($container->getDefinitions() as $definition) {
            $definition->setPublic(true);
        }

        foreach ($container->getAliases() as $alias) {
            $alias->setPublic(true);
        }

        return $container;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function loaded(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $container->setParameter('kernel.debug', false);
        $container->register('clock', MockClock::class);
        $container->register('cache.app', ArrayAdapter::class);
        $container->register('holds_pool', ArrayAdapter::class);
        $container->register(LinkedIdentityRepository::class, InMemoryLinkedIdentityRepository::class);
        $container->register(AccountRepository::class, InMemoryAccountRepository::class);

        $bundle = new PhilIdentityProviderGoogleBundle();
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);
        $container->registerExtension($extension);
        $extension->load([array_replace_recursive(['client_id' => FakeGoogle::CLIENT_ID, 'client_secret' => FakeGoogle::CLIENT_SECRET], $config)], $container);

        return $container;
    }
}
