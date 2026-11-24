<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Tests\Functional\App;

use Override;
use Phil\FeedBundle\Infrastructure\WebSub\AnnounceFeedUpdate;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle with Messenger on an in-memory transport, TwigBundle,
 * two sources of its own, a hub, and the routes of the bundle. The environment `nohub` configures no hub and serves
 * Atom alone, without a cache.
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
        return sys_get_temp_dir().'/phil-feed-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-feed-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['default_uri' => 'https://example.com'],
            'messenger' => [
                'transports' => ['async' => 'in-memory://'],
                'routing' => [AnnounceFeedUpdate::class => 'async'],
            ],
        ]);
        $container->extension('twig', ['default_path' => '%kernel.project_dir%/templates']);

        $hubs = 'nohub' === $this->environment;
        $container->extension('phil_feed', $hubs ? ['formats' => ['atom'], 'cache' => ['max_age' => 0]] : [
            'limit' => 2,
            'cache' => ['max_age' => 600, 'shared_max_age' => 3600],
            'websub' => ['hubs' => ['https://hub.example.net/']],
        ]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set(NewsSource::class);
        $services->set(EmptySource::class);
        $services->set('logger', NullLogger::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@PhilFeedBundle/config/routes.php');
    }
}
