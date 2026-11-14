<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Functional\Members;

use Override;
use Phil\TenancyBundle\Tests\Functional\Sites\HeaderAuthenticator;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Two clubs in one application, each with its own members: `club-a.test`, also reached as `www.club-a.test`, and
 * `club-b.test`, which signs its members in with a firewall of its own. Each club loads its accounts from its own
 * provider; a request in no club, from the staff's. SecurityBundle signs in the account named by `X-Login`.
 */
final class MembersKernel extends Kernel
{
    use MicroKernelTrait;

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    /**
     * Beside the kernel, out of a `config/` directory, where the kernel would write its configuration reference.
     */
    private function getBundlesPath(): string
    {
        return __DIR__.'/bundles.php';
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-tenancy-bundle-test/members/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-tenancy-bundle-test/members/log';
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
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
        ]);

        $container->extension('security', [
            'providers' => [
                'a_members' => ['memory' => ['users' => ['ana' => ['roles' => ['ROLE_MEMBER']]]]],
                'b_members' => ['memory' => ['users' => ['ben' => ['roles' => ['ROLE_MEMBER']]]]],
                'staff' => ['memory' => ['users' => ['sam' => ['roles' => ['ROLE_STAFF']]]]],
                'members' => ['id' => 'phil_tenancy.user_provider.members'],
            ],
            'firewalls' => [
                'club_b' => ['request_matcher' => 'phil_tenancy.request_matcher.club_b', 'lazy' => true, 'provider' => 'members', 'custom_authenticators' => [HeaderAuthenticator::class]],
                'main' => ['lazy' => true, 'provider' => 'members', 'custom_authenticators' => [HeaderAuthenticator::class]],
            ],
        ]);

        $container->extension('phil_tenancy', [
            'hosts' => ['club-a.test' => 'a', 'club-b.test' => 'b'],
            'host_aliases' => ['www.club-a.test' => 'club-a.test'],
            'authentication' => [
                'tenant_firewalls' => ['club_b' => ['b']],
                'tenant_user_providers' => [
                    'members' => [
                        'tenants' => ['a' => 'security.user.provider.concrete.a_members', 'b' => 'security.user.provider.concrete.b_members'],
                        'default' => 'security.user.provider.concrete.staff',
                    ],
                ],
            ],
        ]);

        $services = $container->services();
        $services->defaults()->autowire();
        $services->set('logger', NullLogger::class);
        $services->set(HeaderAuthenticator::class);
        $services->set(MembersController::class)->tag('controller.service_arguments')->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('whoami', '/whoami')->controller([MembersController::class, 'whoami']);
    }
}
