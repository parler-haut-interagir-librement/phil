<?php

declare(strict_types=1);

namespace Phil\VideoBundle\Tests\Functional\App;

use FilesystemIterator;
use Override;
use Psr\Log\NullLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the player needs: FrameworkBundle with the translator, TwigBundle, TwigComponentBundle
 * with the recipes of the kit copied where `ux:install` would put them, the bundle of the kit for `image_origin()`, and
 * the bundle, tracking on, with a consent the tests turn on and off, and its route imported.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /** The recipes the template of the player calls, and the image recipe the video recipe calls. */
    public const array RECIPES = ['button', 'image', 'video'];

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-video-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-video-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function installRecipes(): string
    {
        $root = sys_get_temp_dir().'/phil-video-bundle-test/'.hash('xxh128', __DIR__);
        // The recipes call one another by their names at the root of the components directory, `Image:Figure`.
        $target = $root.'/templates/components';
        $kit = array_find(
            [__DIR__.'/../../../../../vendor/phil/ux-kit', __DIR__.'/../../../vendor/phil/ux-kit'],
            static fn (string $directory): bool => is_file($directory.'/manifest.json'),
        ) ?? __DIR__.'/../../../vendor/phil/ux-kit';

        foreach (self::RECIPES as $recipe) {
            $source = $kit.'/'.$recipe.'/templates/components';

            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)) as $file) {
                \assert($file instanceof SplFileInfo);
                $copy = $target.substr($file->getPathname(), \strlen($source));

                if (!is_dir(\dirname($copy))) {
                    mkdir(\dirname($copy), recursive: true);
                }

                copy($file->getPathname(), $copy);
            }
        }

        return $root.'/templates';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'translator' => ['default_path' => '%kernel.project_dir%/translations', 'fallbacks' => ['en']],
            'router' => ['utf8' => true],
        ]);
        $container->extension('twig', ['strict_variables' => true, 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('phil_ux_kit', ['image' => ['hosts' => ['cdn.example.com']]]);
        $container->extension('phil_video', ['tracking' => ['enabled' => true, 'consent' => SwitchableConsent::class]]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set('logger', NullLogger::class);
        $services->set(SwitchableConsent::class)->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/../../../config/routes.php');
    }
}
