<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Functional\App;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * An application as small as the bundle needs: FrameworkBundle with a router, a session, a translator and a mailer,
 * DoctrineBundle on SQLite, Twig with its components, Symfony UX Turbo, and the bundle with its routes, a recipient
 * named by a header, a directory of two people, the preferences in the database, the signed link that stops a digest,
 * toasts in the session and Web Push.
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
        return sys_get_temp_dir().'/phil-notifications-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-notifications-bundle-test/'.hash('xxh128', __DIR__).'/log';
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
            'default_locale' => 'en',
            'translator' => ['fallbacks' => ['en']],
            'mailer' => ['dsn' => 'null://null'],
        ]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:']]);
        $container->extension('twig', ['strict_variables' => true, 'default_path' => __DIR__.'/templates']);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('phil_notifications', [
            'preferences' => 'dbal',
            'current_recipient' => HeaderRecipient::class,
            'recipient_directory' => People::class,
            'types' => [
                'comment.replied' => ['collapse' => true],
            ],
            'digest' => ['preferences_route' => 'settings'],
            // A key pair made for these tests only, by phil:notifications:vapid-keys.
            'web_push' => [
                'enabled' => true,
                'subject' => 'mailto:ops@example.com',
                'public_key' => 'BDpaYQAvNYnB8XOkYHuo8Lx4WGLWm5KzaN4ujfH356uc4dzZCkieV20Lj2mauyQZvOXvbl18TnC8wNls9UadiNs',
                'private_key' => 'xr5Ew_BzGnh-zbhwoowfdo_yEaVqOebrEurbzyWez6Y',
            ],
        ]);

        $services = $container->services()->defaults()->autowire();
        $services->set('logger', NullLogger::class);
        // An application requires FrameworkBundle, and with it the event dispatcher, in production; installed on its
        // own, the bundle has them in development only, which the container takes as absent.
        $services->set('event_dispatcher', EventDispatcher::class)->public()->tag('event_dispatcher.dispatcher', ['name' => 'event_dispatcher']);
        $services->alias(EventDispatcherInterface::class, 'event_dispatcher');
        $services->alias(PsrEventDispatcherInterface::class, 'event_dispatcher');
        $services->set('clock', MockClock::class)->args(['2026-11-08 10:00:00 UTC'])->public();
        $services->set(HeaderRecipient::class);
        $services->set(People::class);
        $services->set(PageController::class)->tag('controller.service_arguments')->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 3).'/config/routes.php')->prefix('/notifications');
        $routes->import(\dirname(__DIR__, 3).'/config/push_routes.php')->prefix('/notifications');
        $routes->import(\dirname(__DIR__, 3).'/config/digest_routes.php')->prefix('/notifications');
        $routes->add('settings', '/settings')->controller(PageController::class);
        $routes->add('page', '/page')->controller(PageController::class);
        $routes->add('turbo_page', '/turbo-page')->controller(PageController::class)->defaults(['template' => 'turbo.html.twig']);
    }
}
