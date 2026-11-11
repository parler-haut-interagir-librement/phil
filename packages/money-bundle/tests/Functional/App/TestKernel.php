<?php

declare(strict_types=1);

namespace Phil\MoneyBundle\Tests\Functional\App;

use Override;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle with forms, the validator and the translator in
 * `fr_CA`; DoctrineBundle on SQLite with a `Product` that embeds its price; TwigBundle. The application accepts
 * the Canadian and the American dollar.
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
        return sys_get_temp_dir().'/phil-money-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-money-bundle-test/'.hash('xxh128', __DIR__).'/log';
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
            'translator' => ['fallbacks' => ['fr']],
            'form' => ['csrf_protection' => false],
            'validation' => true,
        ]);

        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm' => [
                'mappings' => [
                    'App' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => __DIR__.'/Entity',
                        'prefix' => 'Phil\MoneyBundle\Tests\Functional\App\Entity',
                    ],
                ],
            ],
        ]);

        $container->extension('twig', ['strict_variables' => true]);

        // The form factory, for the tests to build forms with.
        $container->services()->alias('test.form.factory', 'form.factory')->public();

        $container->extension('phil_money', ['currencies' => ['CAD', 'USD']]);
    }
}
