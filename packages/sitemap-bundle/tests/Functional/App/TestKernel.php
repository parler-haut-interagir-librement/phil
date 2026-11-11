<?php

declare(strict_types=1);

namespace Phil\SitemapBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle, routes with a sitemap option — one localized —, a
 * provider, a listener of the populate event, and parts of two pages. The environment `localhost` sets no address for
 * the router.
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
        return sys_get_temp_dir().'/phil-sitemap-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-sitemap-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            // The environment "localhost" leaves the router on its own address, as an application that sets none.
            'router' => 'localhost' === $this->environment ? [] : ['default_uri' => 'https://example.com'],
        ]);
        $container->extension('phil_sitemap', [
            'items_by_set' => 2,
            'defaults' => ['priority' => 0.5, 'changefreq' => 'yearly'],
            // The environment "localhost" is a development machine: its pages are on http://localhost.
            'allow_localhost' => 'localhost' === $this->environment,
        ]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set(ArticleProvider::class);
        $services->set(ContactListener::class);
        $services->set('logger', NullLogger::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@PhilSitemapBundle/config/routes.php');
        $routes->add('home', '/')->options(['sitemap' => ['priority' => 1.0, 'changefreq' => 'daily', 'lastmod' => '2026-11-01T08:00:00+00:00']]);
        $routes->add('about', ['fr' => '/a-propos', 'en' => '/about'])->options(['sitemap' => ['section' => 'pages']]);
        $routes->add('hidden', '/cache')->options(['sitemap' => false]);
        $routes->add('plain', '/plain');
    }
}
