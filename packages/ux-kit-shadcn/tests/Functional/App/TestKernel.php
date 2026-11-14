<?php

declare(strict_types=1);

namespace Phil\UxKitShadcn\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application where every recipe of the kit is installed as ux:install would: each recipe's `templates/` is a
 * path of Twig, so its components resolve as anonymous components, `<twig:Logo>` and the others.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public const string KIT_DIR = __DIR__.'/../../..';

    /**
     * The bundles the kernel registers, listed in `bundles.php` as an application lists them.
     */
    private function getBundlesPath(): string
    {
        return __DIR__.'/bundles.php';
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-ux-kit-shadcn-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-ux-kit-shadcn-test/'.hash('xxh128', __DIR__).'/log';
    }

    /**
     * The `templates/` directory of each recipe of the kit.
     *
     * @return list<string>
     */
    public static function recipeTemplates(): array
    {
        $directories = glob(self::KIT_DIR.'/*/templates', \GLOB_ONLYDIR);

        return false === $directories ? [] : $directories;
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            // preload(), which the image recipe calls for its main image.
            'web_link' => ['enabled' => true],
        ]);
        $container->extension('phil_ux_kit', ['image' => ['hosts' => ['images.example.com']]]);
        $container->extension('twig', ['strict_variables' => true, 'paths' => array_fill_keys(self::recipeTemplates(), null)]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->services()->set('logger', NullLogger::class);
        $container->services()->set(ChartjsStubExtension::class)->tag('twig.extension');
    }
}
