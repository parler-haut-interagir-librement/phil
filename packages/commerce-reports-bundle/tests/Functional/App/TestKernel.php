<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Tests\Functional\App;

use Override;
use Phil\CommerceReportsBundle\Application\Contract\ReturnedLines;
use Phil\CommerceReportsBundle\Application\Contract\SoldLines;
use Phil\CommerceReportsBundle\Application\Contract\StockedSkus;
use Phil\CommerceReportsBundle\Application\Contract\StockFigures;
use Phil\ReportingBundle\Application\Report\Reports;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle, the reporting bundle with its stores in memory, the
 * commerce reports with dead stock after 60 days, a shop that connects the ports, and a clock frozen on 20 November
 * 2026.
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
        return sys_get_temp_dir().'/phil-commerce-reports-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-commerce-reports-bundle-test/'.hash('xxh128', __DIR__).'/log';
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
        $container->extension('phil_reporting', ['store' => 'memory']);
        $container->extension('phil_commerce_reports', ['dead_stock_days' => 60]);

        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set('clock', MockClock::class)->args(['2026-11-20T12:00:00Z']);
        $services->set(Shop::class);
        $services->set(Returns::class);
        $services->alias(SoldLines::class, Shop::class);
        $services->alias(StockFigures::class, Shop::class);
        $services->alias(StockedSkus::class, Shop::class);
        $services->alias(ReturnedLines::class, Returns::class);
        $services->alias('test.reports', Reports::class)->public();
    }
}
