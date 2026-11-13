<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Tests\Functional\App;

use Override;
use Phil\EpubBundle\Application\Event\EpubStored;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle with Messenger handling at once, phil/fonts-bundle on
 * the fonts of the tests, a book source and a listener of its own.
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
        return sys_get_temp_dir().'/phil-epub-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-epub-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    public function booksDirectory(): string
    {
        return sys_get_temp_dir().'/phil-epub-bundle-test/'.hash('xxh128', __DIR__).'/books';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test', 'test' => true, 'http_method_override' => false, 'handle_all_throwables' => true, 'php_errors' => ['log' => true], 'messenger' => ['default_bus' => 'messenger.bus.default']]);
        $container->extension('phil_fonts', ['directory' => '%kernel.project_dir%/fonts']);
        $container->extension('phil_epub', ['storage' => ['directory' => $this->booksDirectory()]]);

        $services = $container->services();
        $services->set(Stories::class)->autowire()->autoconfigure();
        $services->set(StoredBooks::class)->public()->tag('kernel.event_listener', ['event' => EpubStored::class]);
    }
}
