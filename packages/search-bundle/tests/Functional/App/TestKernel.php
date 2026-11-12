<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle, DoctrineBundle on SQLite with pages and notes and
 * their providers, the bundle on its database adapter with a directory of synonyms, and a results page drawn with the
 * recipes of the kit, copied where `ux:install` would put them, in the subdirectory `ui`.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public const string RECIPES = 'ui';

    private const array KIT_RECIPES = ['badge/templates/components/Badge', 'button/templates/components/Button', 'checkbox/templates/components/Checkbox', 'field/templates/components/Field', 'field/templates/components/Field/Label', 'field/templates/components/Field/Legend', 'field/templates/components/Field/Set'];

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return $this->root().'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->root().'/log';
    }

    private function root(): string
    {
        return sys_get_temp_dir().'/phil-search-bundle-test/'.hash('xxh128', __DIR__);
    }

    /**
     * The templates of the recipes the filters draw with, where the kit's manifests copy them.
     */
    private function installRecipes(): string
    {
        $target = $this->root().'/templates/components/'.self::RECIPES;

        if (!is_dir($target.'/Field')) {
            mkdir($target.'/Field', recursive: true);
        }

        // The kit among the development requirements of the monorepo's root, else among those of the package alone.
        $kit = array_find(
            [__DIR__.'/../../../../../vendor/phil/ux-kit', __DIR__.'/../../../vendor/phil/ux-kit'],
            static fn (string $directory): bool => is_file($directory.'/manifest.json'),
        ) ?? throw new RuntimeException('The kit phil/ux-kit is missing: composer install.');

        foreach (self::KIT_RECIPES as $recipe) {
            $name = explode('/components/', $recipe)[1];

            if (!copy($kit.'/'.$recipe.'.html.twig', $target.'/'.$name.'.html.twig')) {
                throw new RuntimeException(\sprintf('The recipe %s of the kit cannot be copied from %s.', $name, $kit));
            }
        }

        return $this->root().'/templates';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->parameters()->set('.ux_toolkit.components.subdirectory', self::RECIPES);
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'php_errors' => ['log' => true],
            'default_locale' => 'fr',
            'translator' => ['fallbacks' => ['fr']],
            'router' => ['utf8' => true],
        ]);
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm' => ['mappings' => ['App' => ['type' => 'attribute', 'is_bundle' => false, 'dir' => __DIR__.'/Entity', 'prefix' => 'Phil\SearchBundle\Tests\Functional\App\Entity']]],
        ]);
        $container->extension('twig', ['strict_variables' => true, 'default_path' => __DIR__.'/templates', 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('phil_search', ['synonyms' => ['directory' => __DIR__.'/synonyms']]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set('logger', NullLogger::class);
        $services->set(Pages::class);
        $services->set(Notes::class);
        $services->set(GamesController::class)->public()->tag('controller.service_arguments');
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('games', '/games')->controller(GamesController::class);
    }
}
