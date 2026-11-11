<?php

declare(strict_types=1);

namespace Phil\TaxonomyBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle with forms and the translator in `fr_CA`, DoctrineBundle
 * on SQLite, and the bundle.
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
        return sys_get_temp_dir().'/phil-taxonomy-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-taxonomy-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test', 'test' => true, 'http_method_override' => false, 'php_errors' => ['log' => true], 'default_locale' => 'fr_CA', 'form' => ['csrf_protection' => false], 'translator' => ['fallbacks' => ['fr']]]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:'], 'orm' => []]);
        $container->extension('phil_taxonomy', ['fallback_locale' => 'fr']);
        $container->services()->set('logger', NullLogger::class);
        $container->services()->alias('test.form.factory', 'form.factory')->public();
        $container->services()->alias('test.translator', 'translator')->public();
    }
}
