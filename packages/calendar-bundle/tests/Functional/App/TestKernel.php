<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Functional\App;

use FilesystemIterator;
use Override;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle with forms, the validator and the translator in
 * `fr_CA`; DoctrineBundle on SQLite; TwigBundle with TwigComponent and LiveComponent, and the recipes of the PHIL UX
 * kit the templates call, copied where `ux:install` puts them — in the subdirectory `ui` of the components, the Toolkit
 * itself stood for by its parameter —; PhilRecurrenceBundle and PhilTaxonomyBundle; events kept in Toronto; the feed
 * under `/calendar`.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /** The recipes the templates of the bundle call. */
    public const array RECIPES = ['badge', 'button', 'button-group', 'card', 'empty', 'popover'];

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-calendar-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-calendar-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'default_locale' => 'fr_CA',
            'form' => ['csrf_protection' => false],
            'validation' => true,
            'translator' => ['fallbacks' => ['fr']],
        ]);

        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:'], 'orm' => []]);
        $container->parameters()->set('.ux_toolkit.components.subdirectory', 'ui');
        $container->extension('twig', ['strict_variables' => true, 'paths' => [$this->installRecipes()]]);
        $container->extension('phil_calendar', [
            'default_timezone' => 'America/Toronto',
            'name' => 'Le Club',
            'translations' => ['locales' => ['fr_CA' => ['fr'], 'fr' => [], 'en' => []]],
        ]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);

        // The form factory, for the tests to build forms with.
        $container->services()->alias('test.form.factory', 'form.factory')->public();
        $container->services()->set(LiveRequestSwitches::class)->autoconfigure()->public();
    }

    /**
     * The templates of the recipes, where the kit's manifests copy them.
     */
    private function installRecipes(): string
    {
        $root = sys_get_temp_dir().'/phil-calendar-bundle-test/'.hash('xxh128', __DIR__).'/templates';
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
        $routes->import(__DIR__.'/../../../config/routes.php')->prefix('/calendar');
        $routes->import('@LiveComponentBundle/config/routes.php')->prefix('/_components');
    }
}
