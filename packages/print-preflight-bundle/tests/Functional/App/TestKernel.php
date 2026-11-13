<?php

declare(strict_types=1);

namespace Phil\PrintPreflightBundle\Tests\Functional\App;

use Override;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle, phil/fonts-bundle on the fonts of the tests, a
 * booklet profile, and a check of its own.
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
        return sys_get_temp_dir().'/phil-print-preflight-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-print-preflight-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test', 'test' => true, 'http_method_override' => false, 'handle_all_throwables' => true, 'php_errors' => ['log' => true]]);
        $container->extension('phil_fonts', ['directory' => '%kernel.project_dir%/fonts']);
        $container->extension('phil_print_preflight', ['profiles' => ['booklet' => ['trim_sizes' => ['a5'], 'page_multiple' => 4]]]);
        $container->services()->set(InkLimit::class)->autoconfigure();
    }
}
