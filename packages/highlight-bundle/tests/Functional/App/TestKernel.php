<?php

declare(strict_types=1);

namespace Phil\HighlightBundle\Tests\Functional\App;

use Override;
use Phil\HighlightBundle\Tests\Unit\ShoutLanguage;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle with AssetMapper, TwigBundle, phil/ujo-bundle, a
 * language of its own, autoconfigured, and an alias.
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
        return sys_get_temp_dir().'/phil-highlight-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-highlight-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'asset_mapper' => ['paths' => []],
        ]);
        $container->extension('twig', ['strict_variables' => true, 'default_path' => '%kernel.project_dir%/templates']);
        $container->extension('phil_highlight', ['aliases' => ['vue' => 'html']]);
        $container->services()->set('logger', NullLogger::class);
        $container->services()->set(ShoutLanguage::class)->autoconfigure();
    }
}
