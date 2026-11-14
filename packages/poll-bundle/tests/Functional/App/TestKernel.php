<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Functional\App;

use FilesystemIterator;
use Override;
use Psr\Log\NullLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle, DoctrineBundle on SQLite, TwigBundle with the
 * translator, TwigComponentBundle with the recipes of the kit copied where `ux:install` would put them, the bundle, a
 * counting method of its own and a listener of the ballots cast.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /** The recipes the templates of the bundle call. */
    public const array RECIPES = ['button', 'checkbox', 'field', 'native-select', 'progress', 'radio-group'];

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-poll-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-poll-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    /**
     * The templates of the recipes, where the kit's manifests copy them.
     */
    private function installRecipes(): string
    {
        $root = sys_get_temp_dir().'/phil-poll-bundle-test/'.hash('xxh128', __DIR__);
        $target = $root.'/templates/components/ui';
        $kit = array_find(
            [__DIR__.'/../../../../ux-kit', __DIR__.'/../../../vendor/phil/ux-kit'],
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
        $container->parameters()->set('.ux_toolkit.components.subdirectory', 'ui');
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'translator' => ['default_path' => '%kernel.project_dir%/translations', 'fallbacks' => ['en']],
        ]);
        $container->extension('twig', ['strict_variables' => true, 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:']]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set('logger', NullLogger::class);
        $services->set('clock', MockClock::class)->args(['2026-11-08 10:00:00 UTC'])->public();
        $services->set(FirstPreferences::class);
        $services->set(RecordedEvents::class)->public();
    }
}
