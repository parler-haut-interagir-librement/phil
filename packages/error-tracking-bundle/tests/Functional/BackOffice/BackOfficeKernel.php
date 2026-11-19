<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Functional\BackOffice;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An EasyAdmin back office as small as the administration pages need: FrameworkBundle with sessions and CSRF,
 * SecurityBundle with one session firewall over two accounts in memory, TwigBundle with the Twig components and the
 * Twig extras EasyAdmin's layout uses, DoctrineBundle on an SQLite file with the ORM EasyAdmin's forms expect,
 * EasyAdmin with the dashboard `Dashboard` under `/admin`, and the bundle with its administration on and its alerts off.
 */
final class BackOfficeKernel extends Kernel
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
        return sys_get_temp_dir().'/phil-error-tracking-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getShareDir(): string
    {
        return sys_get_temp_dir().'/phil-error-tracking-bundle-test/'.hash('xxh128', __DIR__).'/share';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-error-tracking-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'a test secret of sixteen bytes',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'csrf_protection' => true,
            'translator' => ['fallbacks' => ['en']],
            'default_locale' => 'en',
        ]);
        $container->extension('security', [
            'providers' => ['people' => ['memory' => ['users' => [
                'operator' => ['roles' => ['ROLE_ADMIN']],
                'member' => ['roles' => ['ROLE_USER']],
            ]]]],
            'firewalls' => ['main' => ['provider' => 'people', 'lazy' => true, 'logout' => true]],
        ]);
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components']);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///%kernel.cache_dir%/errors.sqlite'], 'orm' => []]);
        $container->extension('phil_error_tracking', [
            'alerts' => false,
            'rate_limit' => false,
            'capture' => ['messenger' => false],
            'admin' => true,
        ]);

        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set(Dashboard::class)->autowire()->autoconfigure();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'easyadmin.routes');
    }
}
