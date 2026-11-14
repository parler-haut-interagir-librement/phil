<?php

declare(strict_types=1);

namespace Phil\StockroomBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle, DoctrineBundle with the ORM on SQLite, and the
 * stockroom in its tables under the prefix `props_`, dated by a frozen clock.
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
        return sys_get_temp_dir().'/phil-stockroom-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-stockroom-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
        ]);
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///%kernel.cache_dir%/stockroom.sqlite'],
            'orm' => [],
        ]);
        $container->extension('phil_stockroom', ['table_prefix' => 'props_']);

        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set('clock', MockClock::class)->args(['2026-11-13 10:00:00'])->public();

        // Installed on its own, the bundle has symfony/event-dispatcher in development only, which Symfony then counts
        // as missing; an application has it through HttpKernel.
        $services->set('event_dispatcher', EventDispatcher::class)->public()->tag('event_dispatcher.dispatcher', ['name' => 'event_dispatcher']);
    }
}
