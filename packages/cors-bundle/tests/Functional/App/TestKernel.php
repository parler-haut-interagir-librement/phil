<?php

declare(strict_types=1);

namespace Phil\CorsBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: one API route that answers GET only and sets a CORS header of its own,
 * one page outside the configured paths, and two rules — the API with credentials for one origin, a public feed for
 * any origin.
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
        return sys_get_temp_dir().'/phil-cors-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-cors-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    public static function books(): JsonResponse
    {
        return new JsonResponse(['books' => []], headers: ['ETag' => '"1"', 'Access-Control-Allow-Origin' => '*']);
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'a secret of the test application',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
        ]);
        $container->extension('phil_cors', [
            'paths' => [
                '^/api/' => [
                    'allow_origins' => ['https://app.example.com'],
                    'allow_methods' => ['GET', 'PUT'],
                    'allow_headers' => ['Content-Type'],
                    'expose_headers' => ['ETag'],
                    'allow_credentials' => true,
                    'max_age' => 3600,
                ],
                '^/feed$' => ['allow_origins' => '*'],
            ],
        ]);

        // Installed on its own, the bundle has symfony/event-dispatcher only for development, which Symfony takes as
        // no dispatcher for the kernel: the application gives it one, as any application does.
        $container->services()->set('event_dispatcher', EventDispatcher::class)->public();
        $container->services()->set('logger', NullLogger::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('books', '/api/books')->controller([self::class, 'books'])->methods(['GET']);
        $routes->add('feed', '/feed')->controller([self::class, 'books'])->methods(['GET']);
        $routes->add('page', '/page')->controller([self::class, 'books'])->methods(['GET']);
    }
}
