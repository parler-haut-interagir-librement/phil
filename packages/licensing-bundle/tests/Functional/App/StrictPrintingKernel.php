<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application that prints with phil/paged-media-bundle under the strict printing: FrameworkBundle with the
 * translator, DoctrineBundle on an in-memory SQLite database, and invoices declared unlicensed.
 */
final class StrictPrintingKernel extends Kernel
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
        return sys_get_temp_dir().'/phil-licensing-bundle-test/'.hash('xxh128', __DIR__).'/strict/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-licensing-bundle-test/'.hash('xxh128', __DIR__).'/strict/log';
    }

    /**
     * The bundles of this application, beside those of `TestKernel` in the same directory.
     */
    private function getBundlesPath(): string
    {
        return __DIR__.'/config/strict-bundles.php';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'default_locale' => 'en',
            'translator' => ['fallbacks' => ['en']],
        ]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:'], 'orm' => []]);
        $container->extension('phil_licensing', ['printing' => ['strict' => true, 'unlicensed_subjects' => ['invoice']]]);
        $container->services()->set('logger', NullLogger::class);
    }
}
