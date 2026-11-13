<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Environment;

/**
 * An application as small as the bundle needs: FrameworkBundle with AssetMapper serving its assets, TwigBundle with
 * the translator, a home page whose layout is the bundle's, and a precache provider — the routes of the bundle
 * imported under `$prefix`.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(
        string $environment,
        bool $debug,
        private readonly string $prefix = '',
    ) {
        parent::__construct($environment, $debug);
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-pwa-bundle-test/'.hash('xxh128', __DIR__.$this->prefix).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-pwa-bundle-test/'.hash('xxh128', __DIR__.$this->prefix).'/log';
    }

    public function home(Environment $twig): Response
    {
        return new Response($twig->render('home.html.twig'));
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['utf8' => true],
            'default_locale' => 'en',
            'translator' => ['fallbacks' => ['en']],
            'asset_mapper' => ['paths' => ['assets/'], 'server' => true],
        ]);

        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set(ScheduleData::class)->autoconfigure();

        $container->extension('twig', ['strict_variables' => true, 'default_path' => __DIR__.'/templates']);
        $container->extension('phil_pwa', [
            'manifest' => [
                'name' => 'Example',
                'short_name' => 'Ex',
                'theme_color' => '#1b1b1b',
                'display' => 'fullscreen',
                'icons' => [['src' => 'images/icon.png', 'sizes' => '192x192', 'type' => 'image/png']],
            ],
            'service_worker' => ['precache' => ['app.css', '/']],
        ]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@PhilPwaBundle/config/routes.php')->prefix($this->prefix);
        $routes->add('home', '/')->controller('kernel::home');
    }
}
