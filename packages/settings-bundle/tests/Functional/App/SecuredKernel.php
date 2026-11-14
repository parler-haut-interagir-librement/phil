<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * An application signed in to by SecurityBundle: users in memory, read by HTTP Basic — ada an administrator, who is
 * also an editor, eve an editor, bob a member —, the current user named by Symfony Security, the site's settings
 * under `/admin/settings` behind the firewall's access control and each setting's role, the preferences under
 * `/preferences`, and the migration of a setting renamed.
 */
final class SecuredKernel extends Kernel
{
    use MicroKernelTrait;

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__.'/secured';
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-settings-bundle-secured/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getShareDir(): string
    {
        return sys_get_temp_dir().'/phil-settings-bundle-secured/'.hash('xxh128', __DIR__).'/share';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-settings-bundle-secured/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'a test secret of sixteen bytes',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'csrf_protection' => true,
            'form' => ['csrf_protection' => true],
            'translator' => ['fallbacks' => ['en']],
            'default_locale' => 'en',
        ]);
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('security', [
            'password_hashers' => [InMemoryUser::class => 'plaintext'],
            'providers' => ['users' => ['memory' => ['users' => [
                'ada' => ['password' => 'ada-password', 'roles' => ['ROLE_ADMIN']],
                'eve' => ['password' => 'eve-password', 'roles' => ['ROLE_EDITOR']],
                'bob' => ['password' => 'bob-password', 'roles' => ['ROLE_USER']],
            ]]]],
            'role_hierarchy' => ['ROLE_ADMIN' => ['ROLE_EDITOR']],
            'firewalls' => ['main' => ['stateless' => true, 'http_basic' => null, 'provider' => 'users']],
            'access_control' => [['path' => '^/admin', 'roles' => 'ROLE_EDITOR']],
        ]);
        $container->extension('phil_settings', [
            'store' => 'memory',
            'sections' => ['site' => []],
            'settings' => [
                'site.open' => ['type' => 'boolean', 'default' => true, 'scope' => 'site', 'section' => 'site'],
                'site.motto' => ['type' => 'text', 'default' => 'Hello', 'scope' => 'site', 'section' => 'site', 'role' => 'ROLE_EDITOR', 'max_length' => 40],
            ],
        ]);

        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set(MottoRenamed::class)->autoconfigure();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/../../../config/routes.php')->prefix('/preferences');
        $routes->import(__DIR__.'/../../../config/routes/site.php')->prefix('/admin/settings');
    }
}
