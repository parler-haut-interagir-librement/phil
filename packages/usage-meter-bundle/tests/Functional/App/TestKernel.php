<?php

declare(strict_types=1);

namespace Phil\UsageMeterBundle\Tests\Functional\App;

use Override;
use Phil\UsageMeterBundle\Tests\Unit\Fixture\HeaderKey;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle, DoctrineBundle with the ORM on SQLite, the DBAL store,
 * a meter by rolling window and one by calendar day, and two actions behind limiters of phil/rate-limit-bundle
 * that count on them.
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
        return sys_get_temp_dir().'/phil-usage-meter-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-usage-meter-bundle-test/'.hash('xxh128', __DIR__).'/log';
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
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///%kernel.cache_dir%/usage.sqlite'],
            'orm' => [],
        ]);
        $container->extension('phil_rate_limit', ['headers' => 'legacy']);
        $container->extension('phil_usage_meter', [
            'meters' => [
                'requests' => ['limit' => 3, 'rolling' => 3600, 'buckets' => 60],
                'exports' => ['limit' => 5, 'calendar' => 'day', 'timezone' => 'America/Toronto'],
            ],
            'rate_limiters' => [
                'api' => ['meter' => 'requests'],
                'accounts' => ['meter' => 'exports', 'key' => HeaderKey::class],
            ],
        ]);

        // The 429 responses the tests ask for are logged; the default logger would print them.
        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set(HeaderKey::class);
        $services->set(TestController::class)->tag('controller.service_arguments')->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('search', '/search')->controller([TestController::class, 'search']);
        $routes->add('export', '/export')->controller([TestController::class, 'export']);
    }
}
