<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit;

use Phil\AuditBundle\Application\AuditTrail;
use Phil\IdentityProviderBundle\Infrastructure\DependencyInjection\IdentityProviderServices;
use Phil\IdentityProviderBundle\PhilIdentityProviderBundle;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\ContainerDump;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

/**
 * The services each configuration registers, line by line: the arguments, the visibility and the tags are the contract
 * with the container, and a wrong one fails only when the application boots. A snapshot is rewritten with
 * UPDATE_SNAPSHOTS=1, then read in the diff.
 */
#[CoversClass(IdentityProviderServices::class)]
#[CoversClass(PhilIdentityProviderBundle::class)]
final class ContainerSnapshotTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('configurations')]
    public function testTheWiring(string $name, array $config, bool $audit): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');

        if ($audit) {
            $container->setDefinition(AuditTrail::class, new Definition(AuditTrail::class));
        }

        $extension = new PhilIdentityProviderBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);
        $extension->load([$config], $container);

        $file = __DIR__.'/Fixture/container/'.$name.'.txt';
        $dump = ContainerDump::of($container);

        if (false !== getenv('UPDATE_SNAPSHOTS')) {
            file_put_contents($file, $dump);
        }

        self::assertStringEqualsFile($file, $dump);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, bool}>
     */
    public static function configurations(): iterable
    {
        yield 'defaults' => ['defaults', [], false];

        yield 'everything-memory' => ['everything-memory', [
            'store' => 'memory',
            'modules' => ['passkeys' => true, 'totp' => true, 'recovery_codes' => true, 'login_link' => true, 'sms' => true, 'external' => true, 'oidc' => true],
            'passkeys' => ['rp_id' => 'example.com', 'rp_name' => 'Example Passkeys', 'origins' => ['https://example.com'], 'require_user_verification' => true],
            'oidc' => ['issuer' => 'https://example.com', 'algorithm' => 'ES256', 'allow_localhost' => true],
            'secrets' => ['kms_key' => 'identity'],
            'audit' => 'app.audit',
            'mailer' => ['from' => 'no-reply@example.com'],
            'password' => ['breach_check' => true, 'min_score' => 3, 'http_client' => 'app.http'],
            'sms' => ['sender' => 'app.sms', 'from' => 'ACME', 'message' => 'Code {code}', 'lifetime' => 300, 'attempts' => 3],
            'login_route' => 'app_login',
            'open_routes' => ['app_logout'],
            'site_name' => 'Example',
            'registration' => false,
            'require_verified_email' => true,
            'default_target' => '/home',
        ], false];

        yield 'everything-doctrine' => ['everything-doctrine', [
            'entity_manager' => 'tenant',
            'clock' => 'app.clock',
            'modules' => ['passkeys' => true, 'totp' => true, 'recovery_codes' => true, 'login_link' => true, 'sms' => true, 'external' => true, 'oidc' => true],
            'passkeys' => ['rp_id' => 'example.com', 'origins' => ['https://example.com', 'https://app.example.com']],
            'oidc' => ['issuer' => 'https://example.com', 'code_lifetime' => 120, 'access_token_lifetime' => 300, 'id_token_lifetime' => 200, 'refresh_token_lifetime' => 1000, 'key_activation_delay' => 10, 'retired_key_retention' => 20],
            'external' => ['link_verified_email' => true, 'register' => false],
            'totp' => ['issuer' => 'Example', 'window' => 2],
            'lockout' => ['threshold' => 3, 'base' => 30, 'max' => 120],
            'links' => ['verify_lifetime' => 600, 'reset_lifetime' => 300],
            'sessions' => ['touch_interval' => 10],
            'step_up' => ['attribute' => 'IS_AUTHENTICATED_RECENTLY', 'lifetime' => 120],
            'kit_subdirectory' => 'kit',
            'layout' => 'base.html.twig',
            'secret' => 'a-secret-of-at-least-32-characters-long',
            'secrets' => ['kms_key' => 'identity', 'encrypter' => 'app.encrypter', 'decrypter' => 'app.decrypter'],
        ], true];

        yield 'second-factors-and-oidc-only' => ['second-factors-and-oidc-only', [
            'store' => 'memory',
            'modules' => ['password' => false, 'sessions' => false, 'step_up' => false, 'totp' => true, 'recovery_codes' => true, 'external' => true, 'oidc' => true],
            'oidc' => ['issuer' => 'https://example.com'],
            'audit' => 'none',
        ], false];

        yield 'audit-auto-with-the-trail' => ['audit-auto-with-the-trail', ['store' => 'memory'], true];
    }
}
