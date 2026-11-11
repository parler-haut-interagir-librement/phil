<?php

declare(strict_types=1);

namespace Phil\ContentWarningsBundle\Tests\Functional\App;

use Override;
use Phil\ContentWarningsBundle\Infrastructure\Memory\InMemoryWarningPreferences;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle with forms and the translator in `fr_CA`;
 * DoctrineBundle on SQLite with a `Book` that keeps its warnings; TwigBundle; this bundle with a theme of its own and
 * the preferences kept in memory.
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
        return sys_get_temp_dir().'/phil-content-warnings-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-content-warnings-bundle-test/'.hash('xxh128', __DIR__).'/log';
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
            'translator' => ['fallbacks' => ['fr']],
        ]);

        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm' => [
                'mappings' => [
                    'App' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => __DIR__.'/Entity',
                        'prefix' => 'Phil\ContentWarningsBundle\Tests\Functional\App\Entity',
                    ],
                ],
            ],
        ]);

        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('phil_content_warnings', [
            'catalogue' => ['themes' => ['crowds' => ['category' => 'phobias', 'label' => ['en' => 'Crowds', 'fr' => 'Foules']]]],
            'preferences_store' => InMemoryWarningPreferences::class,
        ]);

        $container->services()->alias('test.form.factory', 'form.factory')->public();
    }
}
