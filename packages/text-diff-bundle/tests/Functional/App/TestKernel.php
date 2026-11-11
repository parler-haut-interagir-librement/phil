<?php

declare(strict_types=1);

namespace Phil\TextDiffBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle with AssetMapper, TwigBundle, phil/ujo-bundle, and a
 * text_diff filter that compares by lines unless the template says otherwise and sanitizes the HTML it is not given as
 * markup.
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
        return sys_get_temp_dir().'/phil-text-diff-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-text-diff-bundle-test/'.hash('xxh128', __DIR__).'/log';
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
            'html_sanitizer' => ['sanitizers' => ['app' => ['allow_safe_elements' => true]]],
        ]);
        $container->extension('twig', ['strict_variables' => true, 'default_path' => '%kernel.project_dir%/templates']);
        $container->extension('phil_text_diff', ['tokenizer' => 'lines', 'max_edits' => 50, 'html_sanitizer' => 'html_sanitizer.sanitizer.app']);
        $container->services()->set('logger', NullLogger::class);
    }
}
