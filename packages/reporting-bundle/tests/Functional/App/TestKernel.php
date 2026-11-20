<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Functional\App;

use Override;
use Phil\ReportingBundle\Application\Contract\TrainingSource;
use Phil\ReportingBundle\Tests\Functional\App\Source\Members;
use Phil\ReportingBundle\Tests\Functional\App\Source\MembersReport;
use Phil\ReportingBundle\Tests\Functional\App\Source\Signups;
use Phil\ReportingBundle\Tests\Functional\App\Source\Trainings;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle with Messenger on its synchronous transport, Mailer on
 * its null transport and the translator in `en`; TwigBundle with TwigComponent and LiveComponent; a metric, a series
 * and a report of its own, training activities; a dashboard and a schedule; the stores in memory; a clock frozen on 8 November 2026.
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
        return sys_get_temp_dir().'/phil-reporting-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-reporting-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'default_locale' => 'en',
            'translator' => ['fallbacks' => ['en']],
            'messenger' => ['transports' => ['sync' => 'sync://']],
            'mailer' => ['dsn' => 'null://null'],
        ]);
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('phil_reporting', [
            'timezone' => 'America/Toronto',
            'store' => 'memory',
            'mail' => ['from' => 'reports@example.com'],
            'png' => ['fonts' => [\dirname(__DIR__, 2).'/Fixtures/fonts/test.ttf']],
            'dashboards' => [
                'members' => [
                    'title' => 'Members',
                    'period' => 'this_month',
                    'periods' => ['this_month', 'last_month'],
                    'widgets' => [
                        ['metric' => 'renewals', 'compare' => true],
                        ['series' => 'signups', 'render' => 'html'],
                        ['series' => 'signups', 'title' => 'Sign-ups drawn', 'chart' => 'line'],
                        ['series' => 'signups', 'title' => 'Sign-ups listed', 'render' => 'table'],
                    ],
                ],
                'board' => ['title' => 'Board', 'role' => 'ROLE_BOARD', 'widgets' => []],
            ],
            'schedules' => [
                'monthly_members' => ['report' => 'members', 'every' => '1 month', 'from' => '2026-12-01 06:00', 'delivery' => 'mail', 'recipients' => ['ada@example.com']],
            ],
        ]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set('logger', NullLogger::class);
        $services->set('clock', MockClock::class)->args(['2026-11-08 10:00:00 America/Toronto'])->public();
        $services->set(Members::class);
        $services->set(Signups::class);
        $services->set(MembersReport::class);
        $services->set(Trainings::class);
        $services->alias(TrainingSource::class, Trainings::class);
        $services->alias('test.twig', 'twig')->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@LiveComponentBundle/config/routes.php')->prefix('/_components');
    }
}
