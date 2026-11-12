<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Tests\Functional\App;

use Override;
use Phil\ScheduledTransitionsBundle\Tests\Functional\App\Entity\Page;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle with a `page` workflow, DoctrineBundle on SQLite with
 * pages and their resolver, the bundle, and a clock the tests move.
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
        return sys_get_temp_dir().'/phil-scheduled-transitions-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-scheduled-transitions-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'php_errors' => ['log' => true],
            'workflows' => ['page' => [
                'type' => 'state_machine',
                'marking_store' => ['type' => 'method', 'property' => 'marking'],
                'supports' => [Page::class],
                'initial_marking' => 'draft',
                'places' => ['draft', 'published'],
                'transitions' => ['publish' => ['from' => 'draft', 'to' => 'published'], 'unpublish' => ['from' => 'published', 'to' => 'draft']],
            ]],
        ]);
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm' => ['mappings' => ['App' => ['type' => 'attribute', 'is_bundle' => false, 'dir' => __DIR__.'/Entity', 'prefix' => 'Phil\ScheduledTransitionsBundle\Tests\Functional\App\Entity']]],
        ]);
        $container->extension('phil_scheduled_transitions', []);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set('logger', NullLogger::class);
        $services->set('clock', MockClock::class)->args(['2026-11-09 12:00:00 UTC'])->public();
        $services->set(PageSubjects::class);
    }
}
