<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Tests\Functional\App;

use Override;
use Phil\SearchBundle\Application\SearchIndex;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * The soft delete beside phil/search-bundle, which indexes the entities of each flush: the pages, in an index that
 * remembers what it was told.
 */
final class SearchTestKernel extends Kernel
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
        return sys_get_temp_dir().'/phil-doctrine-behaviours-bundle-search-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-doctrine-behaviours-bundle-search-test/'.hash('xxh128', __DIR__).'/log';
    }

    /**
     * Its bundles: those of the other kernel but the slug's, and phil/search-bundle.
     */
    private function getBundlesPath(): string
    {
        return __DIR__.'/config/search-bundles.php';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test', 'test' => true, 'http_method_override' => false, 'php_errors' => ['log' => true]]);
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm' => ['mappings' => ['App' => ['type' => 'attribute', 'dir' => '%kernel.project_dir%/Entity', 'prefix' => __NAMESPACE__.'\Entity']]],
        ]);
        $container->extension('phil_doctrine_behaviours', []);
        $container->extension('phil_search', []);

        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set(RecordingIndex::class)->public();
        $services->alias(SearchIndex::class, RecordingIndex::class);
        $services->set(PageDocuments::class)->autoconfigure();
    }
}
