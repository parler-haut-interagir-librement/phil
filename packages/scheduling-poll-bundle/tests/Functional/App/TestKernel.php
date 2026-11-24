<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Tests\Functional\App;

use FilesystemIterator;
use Override;
use Phil\SchedulingPollBundle\Tests\Unit\Fixture\Fixtures;
use Psr\Log\NullLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Extra\Intl\IntlExtension;

/**
 * An application as small as the bundle needs: FrameworkBundle, DoctrineBundle on SQLite, TwigBundle with the
 * translator and the dates of `twig/intl-extra`, TwigComponentBundle with the recipes of the PHIL UX kit copied where
 * `ux:install` would put them, the bundle and its routes under `/scheduling-poll`.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /** The recipes the templates of the bundle call. */
    public const array RECIPES = ['button', 'field', 'input', 'native-select', 'table'];

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return $this->temporary().'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->temporary().'/log';
    }

    private function temporary(): string
    {
        return sys_get_temp_dir().'/phil-scheduling-poll-bundle-test/'.hash('xxh128', __DIR__);
    }

    /**
     * The templates of the recipes, where the kit's manifests copy them.
     */
    private function installRecipes(): string
    {
        $root = $this->temporary().'/phil';
        $target = $root.'/templates/components/ui';
        $kit = array_find([__DIR__.'/../../../../ux-kit', __DIR__.'/../../../vendor/phil/ux-kit', __DIR__.'/../../../../../vendor/phil/ux-kit'], static fn (string $directory): bool => is_file($directory.'/manifest.json')) ?? __DIR__.'/../../../vendor/phil/ux-kit';

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
        $container->parameters()->set('.ux_toolkit.components.subdirectory', 'ui');
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
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:']]);
        $container->extension('phil_scheduling_poll', ['links' => ['keys' => [Fixtures::KEY], 'page' => 'https://example.org/scheduling-poll/answer']]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set('logger', NullLogger::class);
        $services->set('clock', MockClock::class)->args([Fixtures::NOW])->public();
        $services->set(IntlExtension::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 3).'/config/routes.php')->prefix('/scheduling-poll');
    }
}
