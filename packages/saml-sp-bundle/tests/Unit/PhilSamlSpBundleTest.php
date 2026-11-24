<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Tests\Unit;

use Phil\SafeHttp\Limits;
use Phil\SafeHttp\SafeHttpClient;
use Phil\SamlSp\Binding\Binding;
use Phil\SamlSp\Contract\ReplayGuard;
use Phil\SamlSp\Http\SafeHttpMetadataFetcher;
use Phil\SamlSp\Message\LogoutProcessor;
use Phil\SamlSp\Message\MessageFactory;
use Phil\SamlSp\Message\ResponseProcessor;
use Phil\SamlSp\Metadata\MetadataLoader;
use Phil\SamlSp\Metadata\MetadataReader;
use Phil\SamlSp\Provider\SecurityPolicy;
use Phil\SamlSp\Signature\SignaturePolicy;
use Phil\SamlSp\Store\CachePendingRequests;
use Phil\SamlSp\Xml\XmlBudget;
use Phil\SamlSpBundle\Application\IdentityProviders;
use Phil\SamlSpBundle\Application\SamlLogin;
use Phil\SamlSpBundle\Application\SamlLogout;
use Phil\SamlSpBundle\Application\SamlUsers;
use Phil\SamlSpBundle\Application\ServiceProviderSettings;
use Phil\SamlSpBundle\Infrastructure\Console\CheckCommand;
use Phil\SamlSpBundle\Infrastructure\Controller\SamlController;
use Phil\SamlSpBundle\Infrastructure\DependencyInjection\ReplayGuardPass;
use Phil\SamlSpBundle\Infrastructure\DependencyInjection\SamlServices;
use Phil\SamlSpBundle\Infrastructure\Routing\RoutedEndpoints;
use Phil\SamlSpBundle\Infrastructure\Security\SamlAuthenticator;
use Phil\SamlSpBundle\Infrastructure\Security\SamlUserProvider;
use Phil\SamlSpBundle\Infrastructure\Security\SingleLogoutListener;
use Phil\SamlSpBundle\PhilSamlSpBundle;
use Phil\SamlSpBundle\Tests\Fixtures\Saml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[CoversClass(PhilSamlSpBundle::class)]
#[CoversClass(SamlServices::class)]
final class PhilSamlSpBundleTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $config = $this->process(['signing' => Saml::pemPair('sp')]);

        self::assertTrue($config['enabled']);
        self::assertNull($config['entity_id']);
        self::assertSame([], $config['encryption']);
        self::assertNull($config['name_id_format']);
        self::assertTrue($config['sign_requests']);
        self::assertSame(120, $config['clock_skew']);
        self::assertSame(600, $config['max_age']);
        self::assertFalse($config['allow_sha1']);
        self::assertSame(2048, $config['min_rsa_bits']);
        self::assertFalse($config['allow_localhost']);
        self::assertTrue($config['bind_to_browser']);
        self::assertTrue($config['secure_cookie']);
        self::assertSame('/', $config['default_target_path']);
        self::assertSame('/', $config['logout_target_path']);
        self::assertSame('cache.app', $config['cache_pool']);
        self::assertSame(86_400, $config['metadata_lifetime']);
        self::assertSame(600, $config['request_lifetime']);
        self::assertSame('clock', $config['clock']);
        self::assertNull($config['http_client']);
        self::assertNull($config['accounts']);
        self::assertSame(['store' => 'auto', 'connection' => 'default', 'table' => 'phil_saml_used_assertion'], $config['replay_guard']);
        self::assertFalse($config['single_server']);
        self::assertSame([], $config['identity_providers']);
        self::assertIsArray($config['signing']);
        self::assertArrayHasKey('passphrase', $config['signing']);
        self::assertNull($config['signing']['passphrase']);
    }

    public function testTheReplayGuardIsChosenAndItsTableNamed(): void
    {
        foreach (['auto', 'doctrine', 'cache'] as $store) {
            $config = $this->process(['sign_requests' => false, 'replay_guard' => ['store' => $store, 'connection' => 'audit', 'table' => 'acme_used_assertion']]);

            self::assertSame(['store' => $store, 'connection' => 'audit', 'table' => 'acme_used_assertion'], $config['replay_guard']);
        }
    }

    public function testTheDefaultsOfAnIdentityProvider(): void
    {
        $providers = $this->process(['signing' => Saml::pemPair('sp'), 'identity_providers' => ['acme-corp' => ['metadata_url' => 'https://idp.example.org/metadata']]])['identity_providers'];
        self::assertIsArray($providers);
        $provider = $providers['acme-corp'];

        self::assertSame([
            'metadata_url' => 'https://idp.example.org/metadata',
            'metadata_file' => null,
            'metadata_xml' => null,
            'metadata_certificates' => [],
            'entity_id' => null,
            'preset' => 'none',
            'attributes' => [],
            'group_roles' => [],
            'default_roles' => ['ROLE_USER'],
            'want_assertions_signed' => true,
            'want_assertions_encrypted' => false,
            'allow_unsolicited' => false,
            'binding' => 'redirect',
            'single_logout' => true,
            'create_accounts' => true,
            'force_authn' => false,
        ], $provider);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalid(): iterable
    {
        $idp = static fn (array $provider, string $key = 'acme'): array => ['signing' => Saml::pemPair('sp'), 'identity_providers' => [$key => $provider]];

        yield 'no signing key' => [[], 'A service provider that signs its requests has its signing key pair'];
        yield 'bad key' => [$idp(['metadata_url' => 'https://a.example.org'], 'Acme'), 'The key of an identity provider is 1 to 64 lowercase letters'];
        yield 'long key' => [$idp(['metadata_url' => 'https://a.example.org'], str_repeat('a', 65)), 'The key of an identity provider is 1 to 64'];
        yield 'no metadata' => [$idp([]), 'An identity provider has its metadata from one of'];
        yield 'two metadata' => [$idp(['metadata_url' => 'https://a.example.org', 'metadata_xml' => '<x/>']), 'An identity provider has its metadata from one of'];
        yield 'unknown field' => [$idp(['metadata_xml' => '<x/>', 'attributes' => ['phone' => ['tel']]]), 'The attributes of an identity provider are mapped to the fields identifier, email'];
        yield 'unknown preset' => [$idp(['metadata_xml' => '<x/>', 'preset' => 'google']), 'The value "google" is not allowed'];
        yield 'skew' => [['signing' => Saml::pemPair('sp'), 'clock_skew' => 601], 'clock_skew'];
        yield 'replay store' => [['sign_requests' => false, 'replay_guard' => ['store' => 'redis']], 'The value "redis" is not allowed'];
        yield 'replay connection' => [['sign_requests' => false, 'replay_guard' => ['connection' => '']], 'replay_guard.connection'];
        yield 'replay table' => [['sign_requests' => false, 'replay_guard' => ['table' => 'Used-Assertion']], 'The table of the accepted assertions is up to 63 lowercase letters'];
        yield 'replay table too long' => [['sign_requests' => false, 'replay_guard' => ['table' => str_repeat('t', 64)]], 'The table of the accepted assertions'];
        yield 'replay table after a newline' => [['sign_requests' => false, 'replay_guard' => ['table' => "used\n"]], 'The table of the accepted assertions'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalid')]
    public function testAnInvalidConfigurationIsRefused(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->process($config);
    }

    public function testUnsignedRequestsNeedNoKey(): void
    {
        self::assertArrayNotHasKey('signing', $this->process(['sign_requests' => false]));
        self::assertFalse($this->process(['enabled' => false])['enabled']);
    }

    public function testADisabledBundleRegistersNothing(): void
    {
        self::assertFalse($this->load(['enabled' => false])->hasDefinition(IdentityProviders::class));
    }

    public function testTheServicesAreRegistered(): void
    {
        $container = $this->load([
            'signing' => Saml::pemPair('sp'),
            'accounts' => 'app.accounts',
            'http_client' => 'app.client',
            'identity_providers' => [
                'acme' => ['metadata_xml' => '<x/>', 'preset' => 'entra_id', 'attributes' => ['email' => ['mail']], 'binding' => 'post'],
                'okta' => ['metadata_url' => 'https://example.okta.com/metadata', 'preset' => 'okta'],
                'ldap' => ['metadata_file' => '/etc/idp.xml', 'preset' => 'generic', 'group_roles' => ['a-b' => 'ROLE_A']],
            ],
        ]);

        foreach ([IdentityProviders::class, SamlAuthenticator::class, SamlController::class, SingleLogoutListener::class, CheckCommand::class] as $id) {
            self::assertTrue($container->hasDefinition($id), $id);
        }

        self::assertTrue($container->getDefinition(SamlController::class)->isPublic());
        self::assertTrue($container->getDefinition(SamlController::class)->hasTag('controller.service_arguments'));
        self::assertSame([['event' => LogoutEvent::class, 'method' => 'onLogout', 'priority' => 32]], $container->getDefinition(SingleLogoutListener::class)->getTag('kernel.event_listener'));
        self::assertTrue($container->getDefinition(IdentityProviders::class)->hasTag('kernel.reset'));
        self::assertSame(['command' => 'phil:saml:check'], $container->getDefinition(CheckCommand::class)->getTag('console.command')[0]);
        self::assertTrue($container->getAlias('phil_saml_sp.user_provider')->isPublic());
        self::assertEquals(new Reference('app.accounts'), $container->getDefinition(SamlUsers::class)->getArgument(2));

        $settings = $container->getDefinition(IdentityProviders::class)->getArgument(0);
        self::assertIsArray($settings);
        self::assertCount(3, $settings);
        [$acme, $okta, $ldap] = $settings;
        self::assertInstanceOf(Definition::class, $acme);
        self::assertInstanceOf(Definition::class, $okta);
        self::assertInstanceOf(Definition::class, $ldap);
        self::assertSame('acme', $acme->getArgument(0));
        self::assertSame('<x/>', $acme->getArgument(2));
        self::assertSame(Binding::Post, $acme->getArgument(12));
        self::assertSame(Binding::Redirect, $okta->getArgument(12));
        self::assertSame('/etc/idp.xml', $ldap->getArgument(3));
        self::assertSame(['a-b' => 'ROLE_A'], $ldap->getArgument(7));

        $map = static function (Definition $definition): array {
            $attributes = $definition->getArgument(6);
            self::assertInstanceOf(Definition::class, $attributes);
            $names = $attributes->getArgument(0);
            self::assertIsArray($names);

            return $names;
        };
        self::assertSame(['mail'], $map($acme)['email']);
        self::assertSame(['http://schemas.microsoft.com/identity/claims/objectidentifier'], $map($acme)['identifier']);
        self::assertSame(['user_id'], $map($okta)['identifier']);
        self::assertSame(['mail', 'email', 'urn:oid:0.9.2342.19200300.100.1.3'], $map($ldap)['email']);
    }

    public function testNoPresetMapsNothing(): void
    {
        $container = $this->load(['sign_requests' => false, 'identity_providers' => ['acme' => ['metadata_xml' => '<x/>']]]);
        $settings = $container->getDefinition(IdentityProviders::class)->getArgument(0);
        self::assertIsArray($settings);
        self::assertInstanceOf(Definition::class, $settings[0]);
        $attributes = $settings[0]->getArgument(6);
        self::assertInstanceOf(Definition::class, $attributes);

        self::assertSame([], $attributes->getArgument(0));
        self::assertNull($container->getDefinition(SamlUsers::class)->getArgument(2));
    }

    public function testTheArgumentsOfTheServices(): void
    {
        $pair = Saml::pemPair('sp');
        $container = $this->load([
            'entity_id' => 'https://sp.example.org/entity',
            'signing' => $pair,
            'encryption' => [$pair],
            'name_id_format' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent',
            'clock_skew' => 77,
            'max_age' => 99,
            'allow_sha1' => true,
            'min_rsa_bits' => 3072,
            'allow_localhost' => true,
            'bind_to_browser' => false,
            'secure_cookie' => false,
            'default_target_path' => '/home',
            'logout_target_path' => '/bye',
            'cache_pool' => 'app.pool',
            'metadata_lifetime' => 4567,
            'request_lifetime' => 123,
            'clock' => 'app.clock',
            'http_client' => 'app.client',
            'accounts' => 'app.accounts',
            'identity_providers' => ['acme' => ['metadata_xml' => '<x/>']],
        ]);
        $clock = new Reference('app.clock');
        $pool = new Reference('app.pool');
        $events = new Reference('event_dispatcher');
        $providers = new Reference(IdentityProviders::class);
        $signatures = static fn (): Definition => new Definition(SignaturePolicy::class, [true, 3072]);
        $arguments = static fn (string $id): array => $container->getDefinition($id)->getArguments();

        self::assertEquals([77, 99, $signatures(), new Definition(XmlBudget::class)], $arguments(SecurityPolicy::class));

        $limits = new Definition(Limits::class)->setFactory([SafeHttpMetadataFetcher::class, 'defaultLimits']);
        self::assertEquals([new Definition(SafeHttpClient::class, [new Reference('app.client'), $limits])], $arguments(SafeHttpMetadataFetcher::class));
        self::assertSame([['method' => 'reset']], $container->getDefinition(SafeHttpMetadataFetcher::class)->getTag('kernel.reset'));

        self::assertEquals([
            new Reference(SafeHttpMetadataFetcher::class),
            $clock,
            $pool,
            4567,
            new Definition(MetadataReader::class, [$signatures(), new Definition(XmlBudget::class, [4_194_304, 48, 200_000]), true]),
        ], $arguments(MetadataLoader::class));
        self::assertEquals([new Reference('router'), true], $arguments(RoutedEndpoints::class));

        $identityProviders = $arguments(IdentityProviders::class);
        self::assertCount(4, $identityProviders);
        self::assertEquals(new Definition(ServiceProviderSettings::class, ['https://sp.example.org/entity', $pair, [$pair], 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent', true]), $identityProviders[1]);
        self::assertEquals([new Reference(MetadataLoader::class), new Reference(RoutedEndpoints::class)], \array_slice($identityProviders, 2));
        self::assertSame([['method' => 'reset']], $container->getDefinition(IdentityProviders::class)->getTag('kernel.reset'));

        self::assertEquals([$clock], $arguments(MessageFactory::class));
        self::assertSame(['store' => 'auto', 'connection' => 'default', 'table' => 'phil_saml_used_assertion', 'pool' => 'app.pool', 'clock' => 'app.clock', 'single_server' => false], $container->getParameter(ReplayGuardPass::PARAMETER));
        self::assertEquals([$pool, 123], $arguments(CachePendingRequests::class));
        self::assertEquals([$clock, new Reference(ReplayGuard::class), new Reference(SecurityPolicy::class)], $arguments(ResponseProcessor::class));
        self::assertEquals([$clock, new Reference(SecurityPolicy::class)], $arguments(LogoutProcessor::class));
        self::assertEquals([$providers, new Reference(MessageFactory::class), new Reference(ResponseProcessor::class), new Reference(CachePendingRequests::class), $clock, '/home'], $arguments(SamlLogin::class));
        self::assertEquals([$providers, new Reference(MessageFactory::class), new Reference(LogoutProcessor::class), new Reference(CachePendingRequests::class), $clock, $events, '/bye'], $arguments(SamlLogout::class));
        self::assertEquals([$providers, $events, new Reference('app.accounts')], $arguments(SamlUsers::class));
        self::assertEquals([new Reference(SamlLogin::class), new Reference(SamlUsers::class), $events, false], $arguments(SamlAuthenticator::class));
        self::assertTrue($container->hasDefinition(SamlUserProvider::class));
        self::assertEquals([new Reference(SamlLogout::class), $providers, $events], $arguments(SingleLogoutListener::class));
        self::assertEquals(
            [$providers, new Reference(SamlLogin::class), new Reference(SamlLogout::class), $events, new Reference(SecurityPolicy::class), new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE), false, false, 123],
            $arguments(SamlController::class),
        );
        self::assertEquals([$providers, $clock], $arguments(CheckCommand::class));
    }

    public function testTheDefaultHttpClientIsTheOneOfTheFetcher(): void
    {
        $container = $this->load(['sign_requests' => false]);
        $client = $container->getDefinition(SafeHttpMetadataFetcher::class)->getArgument(0);

        self::assertInstanceOf(Definition::class, $client);
        self::assertNull($client->getArgument(0));
        self::assertCount(2, $client->getArguments());
    }

    /**
     * @return iterable<string, array{string, int, int}>
     */
    public static function bounds(): iterable
    {
        yield 'clock_skew' => ['clock_skew', 0, 600];
        yield 'max_age' => ['max_age', 30, 86_400];
        yield 'min_rsa_bits' => ['min_rsa_bits', 1024, 16_384];
        yield 'metadata_lifetime' => ['metadata_lifetime', 60, 2_592_000];
        yield 'request_lifetime' => ['request_lifetime', 60, 3600];
    }

    #[DataProvider('bounds')]
    public function testTheBoundsOfAnInteger(string $name, int $min, int $max): void
    {
        $signing = ['signing' => Saml::pemPair('sp')];

        self::assertSame($min, $this->process([...$signing, $name => $min])[$name]);
        self::assertSame($max, $this->process([...$signing, $name => $max])[$name]);

        foreach ([$min - 1, $max + 1] as $outside) {
            try {
                $this->process([...$signing, $name => $outside]);
                self::fail($name.' '.$outside.' was accepted.');
            } catch (InvalidConfigurationException $exception) {
                self::assertStringContainsString($name, $exception->getMessage());
            }
        }
    }

    public function testBothBindingsAreAccepted(): void
    {
        foreach (['redirect', 'post'] as $binding) {
            $providers = $this->process(['sign_requests' => false, 'identity_providers' => ['acme' => ['metadata_xml' => '<x/>', 'binding' => $binding]]])['identity_providers'];
            self::assertIsArray($providers);
            self::assertIsArray($providers['acme']);
            self::assertSame($binding, $providers['acme']['binding']);
        }
    }

    public function testTheKeyOfAnIdentityProviderEndsWithItsLastCharacter(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The key of an identity provider is 1 to 64');

        $this->process(['sign_requests' => false, 'identity_providers' => ["acme\n" => ['metadata_xml' => '<x/>']]]);
    }

    public function testTheTextOfTheAttributesRefusal(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('are mapped to the fields identifier, email, given_name, family_name, display_name, groups, roles.');

        $this->process(['sign_requests' => false, 'identity_providers' => ['acme' => ['metadata_xml' => '<x/>', 'attributes' => ['phone' => ['tel']]]]]);
    }

    public function testTheInfoOfTheAttributes(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        self::assertStringContainsString('# For each field — identifier, email, given_name, family_name, display_name, groups, roles — the attribute names to read, in order, over the preset.', new YamlReferenceDumper()->dump($configuration));
    }

    public function testTheAlias(): void
    {
        self::assertSame('phil_saml_sp', $this->extension()->getAlias());
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        $extension = $this->extension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        $processed = new Processor()->processConfiguration($configuration, [$config]);
        $keys = array_filter(array_keys($processed), is_string(...));
        self::assertCount(\count($processed), $keys);

        return array_combine($keys, array_values($processed));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load([$config], $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilSamlSpBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
