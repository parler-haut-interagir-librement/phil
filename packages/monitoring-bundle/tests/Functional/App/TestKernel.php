<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Functional\App;

use Override;
use Phil\MonitoringBundle\Application\Heartbeats;
use Phil\MonitoringBundle\Application\Maintenance;
use Phil\MonitoringBundle\Domain\Contract\HeartbeatStore;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle with two in-memory Messenger transports, one of them
 * the failure transport, and a chat transport of the Notifier that sends nothing; TwigBundle; a frozen clock; the
 * maintenance flag in the kernel's own directory; two checks and a task; and the health endpoints under `/_health`.
 * Without security, the page of the queues is not served.
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
        return $this->base().'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->base().'/log';
    }

    public function maintenanceFile(): string
    {
        return $this->base().'/maintenance.json';
    }

    private function base(): string
    {
        return sys_get_temp_dir().'/phil-monitoring-bundle-test/'.hash('xxh128', __DIR__);
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'cache' => ['app' => 'cache.adapter.array'],
            'notifier' => ['chatter_transports' => ['ops' => 'null://null']],
            'messenger' => [
                'failure_transport' => 'failed',
                'transports' => ['async' => 'in-memory://', 'failed' => 'in-memory://'],
            ],
        ]);
        $container->extension('twig', []);
        $container->extension('phil_monitoring', [
            'health' => [
                'token' => 's3cret',
                'cache_pools' => ['cache.app'],
                'messenger' => ['async' => 10, 'failed' => 0],
            ],
            'maintenance' => ['file' => $this->maintenanceFile(), 'allowed_ips' => ['10.0.0.1']],
            'heartbeats' => ['tasks' => ['backup' => ['every' => 3600]]],
        ]);

        $services = $container->services();
        $services->set('clock', MockClock::class)->args(['2026-11-08 10:00:00 UTC'])->public();
        $services->set('logger', NullLogger::class);

        foreach ([Heartbeats::class, Maintenance::class, HeartbeatStore::class] as $id) {
            $services->alias('test.'.$id, $id)->public();
        }

        $services->alias('test.bus', 'messenger.default_bus')->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@PhilMonitoringBundle/config/routes/health.php')->prefix('/_health');
        $routes->add('home', '/')->controller('kernel::home');
    }

    public function home(): Response
    {
        return new Response('Home');
    }
}
