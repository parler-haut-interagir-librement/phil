<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Tests\Unit;

use Phil\Lti\Contract\AccessTokens;
use Phil\Lti\Contract\KeyStore;
use Phil\Lti\Contract\LineItemStore;
use Phil\Lti\Contract\LoginStates;
use Phil\Lti\Contract\Nonces;
use Phil\Lti\Contract\Roster;
use Phil\Lti\Contract\ScoreStore;
use Phil\Lti\Http\SafeHttpServiceClient;
use Phil\Lti\Key\InMemoryKeyStore;
use Phil\Lti\Key\SafeHttpKeySetSource;
use Phil\Lti\Platform\Ags\InMemoryLineItemStore;
use Phil\Lti\Platform\Ags\InMemoryScoreStore;
use Phil\Lti\Platform\Nrps\InMemoryRoster;
use Phil\Lti\Platform\Token\InMemoryAccessTokens;
use Phil\Lti\Security\InMemoryLoginStates;
use Phil\Lti\Security\InMemoryNonces;
use Phil\Lti\Service\Scope;
use Phil\LtiBundle\Application\Contract\LaunchMessages;
use Phil\LtiBundle\Application\Contract\PendingScores;
use Phil\LtiBundle\Infrastructure\Configuration\Registrations;
use Phil\LtiBundle\Infrastructure\Console\PurgeCommand;
use Phil\LtiBundle\Infrastructure\Console\RotateKeysCommand;
use Phil\LtiBundle\Infrastructure\Console\SchemaCommand;
use Phil\LtiBundle\Infrastructure\Dbal\DbalAccessTokens;
use Phil\LtiBundle\Infrastructure\Dbal\DbalKeyStore;
use Phil\LtiBundle\Infrastructure\Dbal\DbalLoginStates;
use Phil\LtiBundle\Infrastructure\Dbal\DbalNonces;
use Phil\LtiBundle\Infrastructure\Dbal\LtiSchemaListener;
use Phil\LtiBundle\Infrastructure\DependencyInjection\DefaultsPass;
use Phil\LtiBundle\Infrastructure\DependencyInjection\LtiServices;
use Phil\LtiBundle\Infrastructure\KeyManagement\KeyManagementSealer;
use Phil\LtiBundle\Infrastructure\Memory\InMemoryPendingScores;
use Phil\LtiBundle\Infrastructure\Twig\KitComponents;
use Phil\LtiBundle\Infrastructure\Twig\LtiExtension;
use Phil\LtiBundle\PhilLtiBundle;
use Phil\LtiBundle\Tests\Unit\Fixture\TestKeys;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;

#[CoversClass(PhilLtiBundle::class)]
#[CoversClass(LtiServices::class)]
#[CoversClass(DefaultsPass::class)]
#[CoversClass(Registrations::class)]
#[CoversClass(KitComponents::class)]
#[CoversClass(LtiExtension::class)]
final class PhilLtiBundleTest extends TestCase
{
    public function testTheAlias(): void
    {
        self::assertSame('phil_lti', $this->extension()->getAlias());
    }

    public function testTheDefaultsKeepEverythingInTablesWithSealedKeys(): void
    {
        $container = $this->load([['keys' => ['master_key' => 'lti']]]);

        self::assertSame(DbalNonces::class, (string) $container->getAlias(Nonces::class));
        self::assertSame(DbalKeyStore::class, (string) $container->getAlias(KeyStore::class));
        self::assertEquals([new Reference(DataKeyGeneratorInterface::class), 'lti'], $container->getDefinition(KeyManagementSealer::class)->getArguments());
        self::assertSame(604_800, $container->getDefinition(DbalKeyStore::class)->getArgument(4));
        self::assertSame(2048, $container->getDefinition(DbalKeyStore::class)->getArgument(5));
        self::assertSame(7_776_000, $container->getDefinition(RotateKeysCommand::class)->getArgument(1));
        self::assertTrue($container->hasDefinition(SchemaCommand::class));
        self::assertTrue($container->hasDefinition(PurgeCommand::class));
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'default']]], $container->getDefinition(LtiSchemaListener::class)->getTags());
        self::assertFalse($container->has(LoginStates::class));
        self::assertFalse($container->has(AccessTokens::class));
        self::assertFalse($container->getParameter(LtiServices::PLATFORM_PARAMETER));
    }

    public function testTheClientsAreBuiltWithTheLimitsOfTheirOwnUse(): void
    {
        $container = $this->load([['store' => 'memory']]);

        foreach ([LtiServices::KEY_SET_HTTP_CLIENT => SafeHttpKeySetSource::class, LtiServices::SERVICE_HTTP_CLIENT => SafeHttpServiceClient::class] as $client => $user) {
            $limits = $container->getDefinition($client)->getArgument(1);

            self::assertInstanceOf(Definition::class, $limits);
            self::assertSame([$user, 'defaultLimits'], $limits->getFactory(), $client);
        }
    }

    public function testBothSidesInMemoryWithKeysOfTheConfiguration(): void
    {
        $container = $this->load([[
            'store' => 'memory',
            'keys' => ['source' => 'config', 'private_key' => TestKeys::pem(), 'key_id' => 'k1'],
            'tool' => ['platforms' => [['issuer' => 'https://lms.example.org', 'client_id' => 'c', 'authorization_url' => 'https://lms.example.org/a', 'token_url' => 'https://lms.example.org/t', 'key_set_url' => 'https://lms.example.org/j', 'deployments' => ['1']]]],
            'platform' => ['issuer' => 'https://courses.example.com', 'token_url' => 'https://courses.example.com/token', 'tools' => [['client_id' => 'c', 'login_url' => 'https://tool.example.com/l', 'redirect_urls' => ['https://tool.example.com/r'], 'key_set_url' => 'https://tool.example.com/j', 'scopes' => [Scope::SCORE]]]],
        ]]);

        foreach ([Nonces::class => InMemoryNonces::class, LoginStates::class => InMemoryLoginStates::class, AccessTokens::class => InMemoryAccessTokens::class, LineItemStore::class => InMemoryLineItemStore::class, PendingScores::class => InMemoryPendingScores::class, ScoreStore::class => InMemoryScoreStore::class] as $port => $adapter) {
            self::assertSame($adapter, (string) $container->getAlias($port));
            self::assertSame([['method' => 'reset']], $container->getDefinition($adapter)->getTag('kernel.reset'), $adapter);
        }

        self::assertFalse($container->hasDefinition(SchemaCommand::class));
        self::assertFalse($container->hasDefinition(DbalAccessTokens::class));
        self::assertFalse($container->hasDefinition(DbalLoginStates::class));
        self::assertTrue($container->getParameter(LtiServices::PLATFORM_PARAMETER));
        self::assertSame(InMemoryKeyStore::class, (string) $container->getAlias(KeyStore::class));
        self::assertSame([Registrations::class, 'keys'], $container->getDefinition(InMemoryKeyStore::class)->getFactory());

        $container->register(LaunchMessages::class, LaunchMessages::class);
        new DefaultsPass()->process($container);
        self::assertSame(InMemoryRoster::class, (string) $container->getAlias(Roster::class));
        self::assertTrue($container->hasDefinition(InMemoryRoster::class));
        self::assertTrue($container->getAlias(Roster::class)->isPublic());
        self::assertSame('', $container->getDefinition(KitComponents::class)->getArgument(0));
    }

    public function testThePlatformNeedsTheLaunchesOfTheApplication(): void
    {
        $container = $this->load([['store' => 'memory', 'platform' => ['issuer' => 'https://courses.example.com', 'token_url' => 'https://courses.example.com/token']]]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('implement it and alias it to its service');
        new DefaultsPass()->process($container);
    }

    public function testTheKitFollowsTheToolkitUnlessTheConfigurationNamesIt(): void
    {
        $container = $this->load([['store' => 'memory']]);
        $container->setParameter('.ux_toolkit.components.subdirectory', 'ui/kit');
        new DefaultsPass()->process($container);
        self::assertSame('ui/kit', $container->getDefinition(KitComponents::class)->getArgument(0));

        $named = $this->load([['store' => 'memory', 'kit_subdirectory' => 'mine']]);
        $named->setParameter('.ux_toolkit.components.subdirectory', 'ui');
        new DefaultsPass()->process($named);
        self::assertSame('mine', $named->getDefinition(KitComponents::class)->getArgument(0));

        new DefaultsPass()->process(new ContainerBuilder());
        self::assertSame('ui:kit:Button', new LtiExtension(new KitComponents('/ui/kit/'))->kit('Button'));
        self::assertSame('Button', new LtiExtension()->kit('Button'));
    }

    public function testTheConfigurationIsChecked(): void
    {
        $cases = [
            [['connection' => 'bad name']],
            [['table_prefix' => 'Bad']],
            [['keys' => ['source' => 'config']]],
            [['keys' => ['source' => 'dbal']]],
            [['store' => 'memory', 'platform' => ['enabled' => true]]],
            [['store' => 'memory', 'platform' => ['issuer' => 'https://courses.example.com']]],
            [['store' => 'memory', 'platform' => ['issuer' => 'https://c.example.com', 'token_url' => 'https://c.example.com/t', 'tools' => [['client_id' => 'c', 'login_url' => 'https://t.example.com/l', 'redirect_urls' => ['https://t.example.com/r'], 'key_set_url' => 'https://t.example.com/j', 'scopes' => ['openid']]]]]],
            [['keys' => ['master_key' => 'k', 'bits' => 1024]]],
        ];

        foreach ($cases as $index => $configs) {
            try {
                $this->load($configs);
                self::fail('Case '.$index.' was accepted.');
            } catch (InvalidConfigurationException) {
                self::addToAssertionCount(1);
            }
        }

        self::assertSame(DbalKeyStore::class, (string) $this->load([['keys' => ['master_key' => 'k', 'bits' => 4096]]])->getAlias(KeyStore::class));
    }

    public function testTheNumbersAndNamesOfTheConfigurationHaveTheirBounds(): void
    {
        $keys = ['master_key' => 'k'];
        $platform = ['issuer' => 'https://c.example.com', 'token_url' => 'https://c.example.com/t'];
        $cases = [
            'lifetime' => [static fn (int $value): array => ['keys' => $keys + ['lifetime' => $value]], 86_400, null],
            'overlap' => [static fn (int $value): array => ['keys' => $keys + ['overlap' => $value]], 3_600, null],
            'bits' => [static fn (int $value): array => ['keys' => $keys + ['bits' => $value]], 2_048, 4_096],
            'state lifetime' => [static fn (int $value): array => ['keys' => $keys, 'tool' => ['state_lifetime' => $value]], 60, 3_600],
            'token lifetime' => [static fn (int $value): array => ['keys' => $keys, 'platform' => $platform + ['token_lifetime' => $value]], 60, 86_400],
            'refused score retention' => [static fn (int $value): array => ['keys' => $keys, 'tool' => ['refused_score_retention' => $value]], 86_400, null],
            'deep linking retention' => [static fn (int $value): array => ['keys' => $keys, 'platform' => $platform + ['deep_linking_retention' => $value]], 600, 2_592_000],
        ];

        foreach ($cases as $name => [$config, $min, $max]) {
            $this->load([['store' => 'memory'] + $config($min)]);
            $this->assertRefused($config($min - 1), $name.' below its minimum');

            if (null !== $max) {
                $this->load([['store' => 'memory'] + $config($max)]);
                $this->assertRefused($config($max + 1), $name.' above its maximum');
            }
        }

        foreach (['connection' => ['a', str_repeat('a', 64), 'a_1'], 'table_prefix' => ['a', 'a1_', str_repeat('a', 40), '_a']] as $option => $valid) {
            foreach ($valid as $value) {
                $this->load([[$option => $value, 'keys' => $keys]]);
            }
        }

        foreach (['connection' => ['', str_repeat('a', 65), "ab\n", 'ab-', 'x-ab', 5], 'table_prefix' => ['', str_repeat('a', 41), "ab\n", 'ab-', 'x-ab', '1a', 'A', 5]] as $option => $invalid) {
            foreach ($invalid as $value) {
                $this->assertRefused([$option => $value, 'keys' => $keys], $option.' '.var_export($value, true));
            }
        }

        $this->assertRefused(['store' => 'neither', 'keys' => $keys], 'store');
        $this->load([['store' => 'dbal', 'keys' => $keys]]);
        $this->assertRefused(['keys' => ['source' => 'neither', 'master_key' => 'k']], 'source');
        $this->load([['store' => 'memory', 'keys' => ['source' => 'config', 'private_key' => 'pem']]]);
        $this->load([['keys' => ['source' => 'dbal', 'master_key' => 'k']]]);
    }

    public function testTheRegistrationsAndKeysOfTheConfigurationAreBuilt(): void
    {
        $platforms = Registrations::platforms([['issuer' => 'https://lms.localhost', 'client_id' => 'c', 'authorization_url' => 'https://lms.localhost/a', 'token_url' => 'https://lms.localhost/t', 'key_set_url' => 'https://lms.localhost/j', 'token_audience' => 'aud', 'deployments' => ['1']]], true);
        $platform = $platforms->find('https://lms.localhost', 'c');
        self::assertNotNull($platform);
        self::assertSame('aud', $platform->audience());
        self::assertTrue($platforms->hasDeployment($platform, '1'));

        $tools = Registrations::tools([['client_id' => 'c', 'name' => 'Tool', 'login_url' => 'https://tool.localhost/l', 'redirect_urls' => ['https://tool.localhost/r'], 'key_set_url' => 'https://tool.localhost/j', 'deep_linking_url' => 'https://tool.localhost/dl', 'scopes' => [Scope::SCORE], 'deployments' => ['1']]], true);
        $tool = $tools->find('c');
        self::assertSame(['Tool', 'https://tool.localhost/dl'], [$tool?->name, $tool?->deepLinkingUrl?->toString()]);
        self::assertNotNull($tool);
        self::assertContainsOnlyInstancesOf(Url::class, $tool->redirectUrls);
        self::assertCount(1, $tool->redirectUrls);
        self::assertNull(Registrations::tools([['client_id' => 'c', 'name' => null, 'login_url' => 'https://tool.localhost/l', 'redirect_urls' => ['https://tool.localhost/r'], 'key_set_url' => 'https://tool.localhost/j', 'deep_linking_url' => null, 'scopes' => [], 'deployments' => []]], true)->find('c')?->deepLinkingUrl);

        $private = openssl_pkey_get_private(TestKeys::pem());
        $details = false === $private ? false : openssl_pkey_get_details($private);
        self::assertIsArray($details);
        self::assertIsString($details['key'] ?? null);
        $keys = Registrations::keys(TestKeys::pem(), 'k1', [$details['key']]);
        self::assertSame('k1', $keys->signingKey()->keyId());
        self::assertCount(2, $keys->publicKeys()->keys);
        self::assertSame($keys->signingKey()->publicKey->thumbprint(), $keys->publicKeys()->keys[1]->keyId);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function assertRefused(array $config, string $case): void
    {
        try {
            $this->load([$config]);
            self::fail($case.' was accepted.');
        } catch (InvalidConfigurationException) {
            self::addToAssertionCount(1);
        }
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilLtiBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
