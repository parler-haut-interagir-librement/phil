<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Functional\App;

use Override;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application with AssetMapperBundle, which serves the bundle's controller and stylesheet from the paths the
 * bundle writes in the `asset_mapper` extension.
 */
final class AssetsKernel extends Kernel
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
        return sys_get_temp_dir().'/phil-calendar-bundle-test/'.hash('xxh128', __DIR__).'/assets-cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-calendar-bundle-test/'.hash('xxh128', __DIR__).'/assets-log';
    }

    /**
     * Its bundles, beside those of the other kernel.
     */
    private function getBundlesPath(): string
    {
        return __DIR__.'/config/assets-bundles.php';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test', 'test' => true, 'http_method_override' => false]);
        $container->extension('asset_mapper', ['paths' => []]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:'], 'orm' => []]);
    }
}
