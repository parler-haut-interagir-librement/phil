<?php

declare(strict_types=1);

namespace Phil\HttpCacheTagsBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * An application as small as the bundle needs: FrameworkBundle with sessions, DoctrineBundle on SQLite with pages,
 * TwigBundle, the bundle purging a Varnish that writes down what it is asked, and a listener that tags late.
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
        return sys_get_temp_dir().'/phil-http-cache-tags-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-http-cache-tags-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test', 'test' => true, 'http_method_override' => false, 'php_errors' => ['log' => true], 'session' => ['storage_factory_id' => 'session.storage.factory.mock_file']]);
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm' => ['mappings' => ['App' => ['type' => 'attribute', 'is_bundle' => false, 'dir' => __DIR__.'/Entity', 'prefix' => 'Phil\HttpCacheTagsBundle\Tests\Functional\App\Entity']]],
        ]);
        $container->extension('twig', ['default_path' => __DIR__.'/templates', 'strict_variables' => true]);
        $container->extension('phil_http_cache_tags', ['purger' => 'varnish', 'varnish' => ['urls' => ['http://varnish']]]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set('logger', NullLogger::class);
        $services->set(RecordingVarnish::class)->public();
        $services->set('http_client', MockHttpClient::class)->args([service(RecordingVarnish::class)]);
        $services->set(PageTags::class);
        $services->set(LateTags::class)->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onResponse', 'priority' => -500]);
        $services->set(PageController::class)->public()->tag('controller.service_arguments');
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('page', '/{site}/pages/{id}')->controller([PageController::class, 'show'])->methods(['GET']);
        $routes->add('rename', '/{site}/pages/{id}')->controller([PageController::class, 'rename'])->methods(['POST']);
    }
}
