<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Functional\Sites;

use Override;
use Phil\TenancyBundle\Infrastructure\Messenger\TenantMiddleware;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Several sites in one application: four shop hosts under `shop.test`, whose session cookie they share, so that only
 * the bundle keeps an account signed in on one shop from being signed in on another. `general` includes the
 * catalogue of `rose`; `tavern` stands alone. SecurityBundle signs in the account named by `X-Login`, from a memory
 * provider the bundle scopes. Mail is sent through a queue, and each shop sends it its own way.
 */
final class SitesKernel extends Kernel
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
        return sys_get_temp_dir().'/phil-tenancy-bundle-test/sites/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-tenancy-bundle-test/sites/log';
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
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file', 'cookie_domain' => '.shop.test'],
            'mailer' => ['transports' => ['main' => 'null://null', 'rose_smtp' => 'null://null']],
            'messenger' => [
                'transports' => ['async' => 'in-memory://'],
                'routing' => [SendEmailMessage::class => 'async'],
                'buses' => ['messenger.bus.default' => ['middleware' => [TenantMiddleware::class]]],
            ],
        ]);

        $container->extension('security', [
            'providers' => ['members' => ['memory' => ['users' => ['ana' => ['roles' => ['ROLE_USER']]]]]],
            'firewalls' => ['main' => ['lazy' => true, 'provider' => 'members', 'custom_authenticators' => [HeaderAuthenticator::class]]],
            'access_control' => [['path' => '^/account', 'roles' => 'ROLE_USER']],
        ]);

        $container->extension('phil_tenancy', [
            'hosts' => ['general.shop.test' => 'general', 'rose.shop.test' => 'rose', 'tavern.shop.test' => 'tavern'],
            'shops' => ['general' => ['includes' => ['rose']]],
            'authentication' => ['user_providers' => ['security.user.provider.concrete.members']],
            'mailer' => [
                'platform_sender' => 'Shops <mail@platform.test>',
                'tenants' => [
                    'rose' => ['transport' => 'rose_smtp', 'sender' => 'Rose <hello@rose.test>'],
                    'tavern' => ['name' => 'The Tavern', 'reply_to' => 'tavern@mail.test'],
                ],
            ],
        ]);

        $services = $container->services();
        $services->defaults()->autowire();
        $services->set('logger', NullLogger::class);
        $services->set(HeaderAuthenticator::class);
        $services->set(SitesController::class)->tag('controller.service_arguments')->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('whoami', '/whoami')->controller([SitesController::class, 'whoami']);
        $routes->add('account', '/account')->controller([SitesController::class, 'whoami']);
        $routes->add('mail', '/mail')->controller([SitesController::class, 'mail']);
        $routes->add('catalogues', '/catalogues')->controller([SitesController::class, 'catalogues']);
    }
}
