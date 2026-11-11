<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheckBundle\Tests\Functional\App;

use Override;
use Phil\RouteAccessCheckBundle\Tests\Functional\App\Controller\ReportController;
use Phil\RouteAccessCheckBundle\Tests\Functional\App\Controller\StatusController;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application with FrameworkBundle, SecurityBundle and the check: a firewall without security for the profiler, a
 * main firewall, four access_control entries, and the controllers of Controller/ routed by their attributes.
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
        return sys_get_temp_dir().'/phil-route-access-check-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-route-access-check-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
        ]);
        $container->parameters()->set('app.admin_path', '^/admin');
        $container->extension('security', [
            'providers' => ['memory' => ['memory' => null]],
            'firewalls' => [
                'dev' => ['pattern' => '^/(_profiler|_wdt|status)', 'security' => false],
                'main' => ['lazy' => true],
            ],
            'access_control' => [
                ['path' => '^/login', 'roles' => 'PUBLIC_ACCESS'],
                ['path' => '%app.admin_path%', 'roles' => 'ROLE_ADMIN'],
                ['path' => '^/api', 'methods' => 'POST, PUT', 'allow_if' => "is_granted('ROLE_API')"],
                ['path' => '^/never', 'roles' => 'ROLE_NOBODY'],
            ],
        ]);
        $container->extension('phil_route_access_check', ['ignore' => ['route_names' => ['_wdt'], 'paths' => ['/_profiler']]]);
        $container->services()
            ->load('Phil\RouteAccessCheckBundle\Tests\Functional\App\Controller\\', __DIR__.'/Controller/')
            ->tag('controller.service_arguments');
        $container->services()->set('app.reports_controller', ReportController::class)->tag('controller.service_arguments');
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/Controller/', 'attribute');
        $routes->add('status', '/status')->controller(StatusController::class);
        $routes->add('reports_by_service', '/reports/by-service')->controller('app.reports_controller::list');
        $routes->add('health', '/health')->options(['public' => 'Probed by the load balancer.']);
        $routes->add('_wdt', '/_wdt/{token}');
    }
}
