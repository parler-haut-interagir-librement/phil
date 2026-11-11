<?php

declare(strict_types=1);

namespace Phil\SlugBundle\Tests\Functional\App;

use Override;
use Phil\SlugBundle\Tests\Functional\App\Service\DirectoryNames;
use Phil\SlugBundle\Tests\Functional\App\Service\NoDigits;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle, TwigBundle, three named sluggers — one decorated by
 * the application —, and a service that asks for two of them.
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
        return sys_get_temp_dir().'/phil-slug-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-slug-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
        ]);

        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('phil_slug', ['sluggers' => [
            'default' => [],
            'directory' => ['leading_words' => ['le', 'la', 'les', 'l', 'the']],
            'titles' => ['max_length' => 20],
        ]]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set(DirectoryNames::class)->public();
        $services->set(NoDigits::class);
    }
}
