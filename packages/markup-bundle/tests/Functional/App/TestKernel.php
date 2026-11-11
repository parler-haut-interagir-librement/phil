<?php

declare(strict_types=1);

namespace Phil\MarkupBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle with forms and the translator in French, TwigBundle,
 * four formats, BBCode kept to the forum level, the forum level by default, and images from one host.
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
        return sys_get_temp_dir().'/phil-markup-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-markup-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'form' => ['csrf_protection' => false],
            'translator' => ['default_path' => '%kernel.project_dir%/translations'],
            'default_locale' => 'fr',
        ]);
        $container->extension('twig', ['strict_variables' => true, 'default_path' => '%kernel.project_dir%/templates']);
        $container->extension('phil_markup', [
            'formats' => ['markdown', 'bbcode', 'html', 'text'],
            'levels' => ['bbcode' => 'forum'],
            'default_level' => 'forum',
            'media_hosts' => ['cdn.example.com'],
        ]);
        $container->services()->set('logger', NullLogger::class);
        $container->services()->alias('test.form.factory', 'form.factory')->public();
    }
}
