<?php

declare(strict_types=1);

namespace Phil\MapBundle\Tests\Functional\App;

use FilesystemIterator;
use Override;
use Phil\MapBundle\Application\Tiles\ProxiedTiles;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle with the translator in French, Twig with its
 * components and the Item recipe of the PHIL UX kit copied where `ux:install` puts it — in the subdirectory `ui` of
 * the components, the Toolkit itself stood for by its parameter —, Stimulus, Symfony UX Map, the tile proxy under
 * `/_map` with a tile source that answers one PNG, and a page of maps.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /** The recipes the template of the bundle calls. */
    public const array RECIPES = ['item'];

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-map-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-map-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'a test secret that is long enough',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'default_locale' => 'fr',
            'translator' => ['fallbacks' => ['fr']],
            'router' => ['utf8' => true],
        ]);
        $container->parameters()->set('.ux_toolkit.components.subdirectory', 'ui');
        $container->extension('twig', ['strict_variables' => true, 'default_path' => __DIR__.'/templates', 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('phil_map', [
            'tiles' => ['url' => 'https://tiles.example.org/{z}/{x}/{y}.png', 'attribution' => '© Example', 'proxy' => ['user_agent' => 'example.org maps', 'cache' => 'cache.array']],
            'neighbourhood' => ['minimum' => 2],
        ]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set('cache.array', ArrayAdapter::class);
        $services->set('http_client', MockHttpClient::class)->factory(TileClient::create(...));
        $services->set(PageController::class)->arg('$proxied', new Reference(ProxiedTiles::class))->public();
    }

    /**
     * The templates of the recipes, where the kit's manifests copy them.
     */
    private function installRecipes(): string
    {
        $root = sys_get_temp_dir().'/phil-map-bundle-test/'.hash('xxh128', __DIR__).'/templates';
        // The kit in the published monorepo beside this one, else among the development requirements of the monorepo's
        // root, else among those of the package alone.
        $kit = array_find(
            [__DIR__.'/../../../../../vendor/phil/ux-kit', __DIR__.'/../../../vendor/phil/ux-kit'],
            static fn (string $directory): bool => is_file($directory.'/manifest.json'),
        ) ?? __DIR__.'/../../../vendor/phil/ux-kit';

        foreach (self::RECIPES as $recipe) {
            $source = $kit.'/'.$recipe.'/templates/components';

            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)) as $file) {
                \assert($file instanceof SplFileInfo);
                $copy = $root.'/components/ui'.substr($file->getPathname(), \strlen($source));

                if (!is_dir(\dirname($copy))) {
                    mkdir(\dirname($copy), recursive: true);
                }

                copy($file->getPathname(), $copy);
            }
        }

        return $root;
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/../../../config/routes.php')->prefix('/_map');
        $routes->add('page', '/maps')->controller(PageController::class);
        // The page of the browser suite: the same maps and an archive, with Stimulus, Leaflet and the controllers.
        $routes->add('browser', '/')->controller(PageController::class)->defaults(['template' => 'browser.html.twig']);
    }
}
