<?php

declare(strict_types=1);

namespace Phil\SvgBlocksBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle with forms, a router, Messenger and a mocked HTTP
 * client standing for the Mermaid service, phil/ujo-bundle, the bundle and its route, and documents in memory.
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
        return sys_get_temp_dir().'/phil-svg-blocks-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-svg-blocks-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'a test secret that is long enough to sign',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'csrf_protection' => true,
            'form' => ['csrf_protection' => false],
            'router' => ['utf8' => true],
            'messenger' => ['enabled' => true],
            'http_client' => ['mock_response_factory' => MermaidResponses::class],
        ]);
        $container->extension('phil_svg_blocks', [
            'qr_code' => ['error_correction' => 'H'],
            'mermaid' => ['url' => 'http://mermaid:3000', 'documents' => InMemoryDocuments::class],
        ]);
        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set(InMemoryDocuments::class)->public();
        $services->set(MermaidResponses::class)->public();
        $services->alias('test.form.factory', 'form.factory')->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 3).'/config/routes.php')->prefix('/_svg-blocks');
    }
}
