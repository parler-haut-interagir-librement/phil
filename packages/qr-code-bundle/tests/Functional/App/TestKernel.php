<?php

declare(strict_types=1);

namespace Phil\QrCodeBundle\Tests\Functional\App;

use Override;
use Phil\SvgBlocksBundle\PhilSvgBlocksBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle, Twig and its components, phil/svg-blocks-bundle for
 * the blocks of UJO documents when it is installed, the route of the codes, two logos, and a page whose template draws codes every way.
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
        return sys_get_temp_dir().'/phil-qr-code-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-qr-code-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test-secret-of-the-application',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['default_uri' => 'https://example.com'],
        ]);
        $container->extension('twig', ['default_path' => __DIR__.'/templates']);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);

        if (class_exists(PhilSvgBlocksBundle::class)) {
            $container->extension('phil_svg_blocks', ['secret' => str_repeat('k', 32)]);
        }

        $container->extension('phil_qr_code', [
            'error_correction' => 'Q',
            'dark' => '#112233',
            'quiet_zone' => 2,
            'logos' => [
                'brand' => ['path' => __DIR__.'/../../Fixtures/logo.svg', 'zone' => 0.25],
                'photo' => ['path' => __DIR__.'/../../Fixtures/logo.png'],
            ],
            'route' => ['enabled' => true, 'cache_max_age' => 3600],
        ]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set(PageController::class)->public()->tag('controller.service_arguments');
        $services->set('logger', NullLogger::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@PhilQrCodeBundle/config/routes.php');
        $routes->add('page', '/page')->controller(PageController::class);
    }
}
