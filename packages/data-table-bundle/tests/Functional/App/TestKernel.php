<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Functional\App;

use FilesystemIterator;
use Override;
use Phil\DataTableBundle\Tests\Fixtures\RecordingAccess;
use Phil\DataTableBundle\Tests\Fixtures\Shelf;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle with the validator, the stateless CSRF token and the
 * translator in French, Twig with its components and the Button recipe of the PHIL UX kit copied where `ux:install`
 * puts it — in the subdirectory `ui` of the components, the Toolkit itself stood for by its parameter —, a page of two
 * tables, and an access that grants all but what a test refuses.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /** The recipes the templates of the bundle call. */
    public const array RECIPES = ['button'];

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-data-table-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-data-table-bundle-test/'.hash('xxh128', __DIR__).'/log';
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
            'validation' => true,
            'csrf_protection' => true,
            'translator' => ['fallbacks' => ['fr']],
            'router' => ['utf8' => true],
        ]);
        $container->parameters()->set('.ux_toolkit.components.subdirectory', 'ui');
        $container->extension('twig', ['strict_variables' => true, 'default_path' => __DIR__.'/templates', 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('phil_data_table', ['access' => RecordingAccess::class, 'cell_themes' => ['cell_theme.html.twig']]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set(Shelf::class)->public();
        $services->set(RecordingAccess::class)->public();
        $services->set(BooksTable::class);
        $services->set(BenchTable::class);
        $services->set(PageController::class)->public();
    }

    /**
     * The templates of the recipes, where the kit's manifests copy them.
     */
    private function installRecipes(): string
    {
        $root = sys_get_temp_dir().'/phil-data-table-bundle-test/'.hash('xxh128', __DIR__).'/templates';
        // The kit beside this package in the monorepo, else among the development requirements of the monorepo's root,
        // else among those of the package alone.
        $kit = array_find(
            [__DIR__.'/../../../../ux-kit', __DIR__.'/../../../../../vendor/phil/ux-kit'],
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
        $routes->import(__DIR__.'/../../../config/routes.php')->prefix('/_tables');
        $routes->import('@LiveComponentBundle/config/routes.php')->prefix('/_components');
        $routes->add('page', '/books')->controller(PageController::class);
        // The page of the browser suite: one table, with Stimulus and the controllers of Live Component and the bundle.
        $routes->add('browser', '/browser')->controller(PageController::class)->defaults(['template' => 'browser.html.twig']);
    }
}
