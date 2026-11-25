<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Functional\App;

use Override;
use Phil\SearchBundle\Application\SearchIndex;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * The bundle indexing through the outbox: DoctrineBundle on SQLite with pages and notes, phil/outbox-bundle keeping its
 * records in the same database, on a clock the test moves, and the index of the database behind a switch that takes
 * it down.
 */
final class OutboxKernel extends Kernel
{
    use MicroKernelTrait;

    #[Override]
    public function getProjectDir(): string
    {
        // The bundles of config/bundles.php there: the outbox beside the search, without Twig.
        return __DIR__.'/outbox';
    }

    #[Override]
    public function getCacheDir(): string
    {
        return $this->root().'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->root().'/log';
    }

    private function root(): string
    {
        return sys_get_temp_dir().'/phil-search-bundle-outbox-test/'.hash('xxh128', __DIR__);
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test', 'test' => true, 'http_method_override' => false, 'php_errors' => ['log' => true]]);
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm' => ['mappings' => ['App' => ['type' => 'attribute', 'is_bundle' => false, 'dir' => __DIR__.'/Entity', 'prefix' => 'Phil\SearchBundle\Tests\Functional\App\Entity']]],
        ]);
        $container->extension('phil_outbox', ['clock' => 'test.clock', 'relay' => ['lock' => false]]);
        $container->extension('phil_search', ['index_entities' => 'outbox']);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set('logger', NullLogger::class);
        $services->set('test.clock', MockClock::class)->args(['2026-11-24 10:00:00'])->public();
        $services->set(Pages::class);
        $services->set(Notes::class);
        $services->set(SwitchableIndex::class)->decorate(SearchIndex::class)->args([service('.inner')])->public();
    }
}
