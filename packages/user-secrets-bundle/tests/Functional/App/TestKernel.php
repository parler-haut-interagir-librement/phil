<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Functional\App;

use Override;
use Phil\UserSecretsBundle\Domain\Credential\OAuthToken;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\FixedRefresher;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\Keys;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\RecordingAuditor;
use Phil\UserSecretsBundle\Tests\Unit\Fixture\RecordingRevoker;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ReferenceConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle, DoctrineBundle on SQLite, the privacy bundle,
 * Symfony's KeyManagement bundle with a local `sodium://` client whose master key is read from an environment variable,
 * FrameworkBundle's lock factory over files, and the bundle, a token refresher, a token revoker and an auditor of the
 * unit tests autoconfigured.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-user-secrets-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-user-secrets-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    #[Override]
    public function boot(): void
    {
        // The key comes from the environment each time the kernel boots, as in an application, cached container or not.
        $_SERVER['USER_SECRETS_KEY'] = Keys::key('f');

        parent::boot();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test', 'test' => true, 'http_method_override' => false, 'php_errors' => ['log' => true], 'lock' => 'flock']);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:']]);
        $container->extension('key_management', ['clients' => ['local' => 'sodium://?keys[k2026]=%env(USER_SECRETS_KEY)%']]);
        $container->extension('phil_user_secrets', ['current_key' => 'k2026']);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set('logger', NullLogger::class);
        $services->set('clock', MockClock::class)->args(['2026-11-06 10:00:00 UTC'])->public();
        $services->set(RecordingAuditor::class)->public();
        $services->set('fresh_token', OAuthToken::class)->factory([OAuthToken::class, 'of'])->args(['fresh-token']);
        $services->set(FixedRefresher::class)->args(['github', new ReferenceConfigurator('fresh_token')]);
        $services->set(RecordingRevoker::class)->args(['github'])->public();
    }
}
