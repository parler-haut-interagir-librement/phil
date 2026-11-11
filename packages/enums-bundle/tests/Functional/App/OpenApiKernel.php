<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Functional\App;

use Override;
use Phil\EnumsBundle\Tests\Functional\App\Api\ParcelView;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * The bundle beside the tools that document an API and write code: API Platform with one resource, NelmioApiDoc with
 * one model — the same class —, and MakerBundle writing into the application's namespace.
 */
final class OpenApiKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * The bundles of this kernel, beside those of the main test kernel.
     */
    private function getBundlesPath(): string
    {
        return __DIR__.'/config/openapi_bundles.php';
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-enums-bundle-test/'.hash('xxh128', __DIR__).'/openapi/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-enums-bundle-test/'.hash('xxh128', __DIR__).'/openapi/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'serializer' => true,
            'property_access' => true,
        ]);
        $container->services()->set('logger', NullLogger::class);
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('api_platform', [
            'mapping' => ['paths' => [__DIR__.'/Api']],
            'doctrine' => ['enabled' => false],
        ]);
        $container->extension('nelmio_api_doc', [
            'areas' => ['default' => ['path_patterns' => ['^/api']]],
            'models' => ['names' => [['alias' => 'ParcelView', 'type' => ParcelView::class]]],
        ]);
        $container->extension('maker', ['root_namespace' => 'Phil\EnumsBundle\Tests\Functional\App']);
        $container->extension('phil_enums', ['paths' => [__DIR__.'/src']]);
    }
}
