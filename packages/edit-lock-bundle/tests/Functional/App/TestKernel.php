<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle with a router, DoctrineBundle on SQLite, MercureBundle,
 * phil/ujo-bundle and the bundle, its routes imported, a holder named by a header and a policy of its own. In the
 * `test` environment the bundle publishes to a hub that records; in the `mercure` environment, to the hub of
 * MercureBundle at `MERCURE_URL`.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public const string SECRET = '!ChangeThisMercureHubJWTSecretKey!';

    public const string HUB = 'http://127.0.0.1:3999/.well-known/mercure';

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-edit-lock-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-edit-lock-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['utf8' => true],
        ]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:']]);

        $url = getenv('MERCURE_URL');
        $container->extension('mercure', ['hubs' => ['default' => [
            'url' => false === $url ? self::HUB : $url,
            'jwt' => ['secret' => self::SECRET, 'publish' => ['*'], 'claims' => ['iss' => 'https://localhost', 'sub' => 'app', 'client_id' => 'app']],
        ]]]);
        $container->extension('phil_edit_lock', [
            'seconds' => 30,
            'holder_provider' => HeaderHolder::class,
            'policy' => OwnerPolicy::class,
            'mercure' => 'mercure' === $this->environment ? [] : ['hub' => RecordingHub::class],
        ]);

        $services = $container->services()->defaults()->autowire();
        $services->set('logger', NullLogger::class);
        $services->set('clock', MockClock::class)->args(['2026-11-06 10:00:00 UTC'])->public();
        $services->set(HeaderHolder::class);
        $services->set(OwnerPolicy::class);
        $services->set(RecordingHub::class)->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 3).'/config/routes.php')->prefix('/_edit-lock');
    }
}
