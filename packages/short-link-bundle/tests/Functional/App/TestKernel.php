<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * An application as small as the bundle needs: FrameworkBundle with forms and CSRF, DoctrineBundle on SQLite, TwigBundle
 * with the translator, Symfony Security with an administrator and a reader signing in by HTTP Basic, two short link
 * domains — `go.example.com` for every locale and `lien.example.fr` for `fr` —, links allowed to `example.com`, a
 * creation allowance of three links an hour kept in memory, and a page of the site on `example.com`.
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
        return $this->root().'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->root().'/log';
    }

    public function page(): Response
    {
        return new Response('a page of the site');
    }

    private function root(): string
    {
        return sys_get_temp_dir().'/phil-short-link-bundle-test/'.hash('xxh128', __DIR__);
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
            'csrf_protection' => true,
            'form' => ['csrf_protection' => true],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'cache' => ['app' => 'cache.adapter.filesystem'],
        ]);
        $container->services()->set('logger', NullLogger::class);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///%kernel.cache_dir%/links.sqlite'], 'orm' => []]);
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('security', [
            'password_hashers' => [InMemoryUser::class => 'plaintext'],
            'providers' => ['people' => ['memory' => ['users' => [
                'admin' => ['password' => 'admin', 'roles' => ['ROLE_ADMIN']],
                'reader' => ['password' => 'reader', 'roles' => ['ROLE_USER']],
            ]]]],
            'firewalls' => ['main' => ['http_basic' => null, 'provider' => 'people']],
        ]);
        $container->extension('phil_short_link', [
            'domains' => [['host' => 'go.example.com'], ['host' => 'lien.example.fr', 'locale' => 'fr']],
            'home_url' => 'https://example.com/',
            'targets' => ['allowed_hosts' => ['example.com']],
            'rate_limit' => ['limit' => 3, 'single_server' => true],
        ]);
        $container->services()->alias('test.entity_manager', 'doctrine.orm.entity_manager')->public();
        $container->services()->alias('test.cache', 'cache.app')->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@PhilShortLinkBundle/config/routes/admin.php')->prefix('/admin/short-links');
        $routes->add('page', '/{code}')->controller($this->page(...))->requirements(['code' => '.*']);
    }
}
