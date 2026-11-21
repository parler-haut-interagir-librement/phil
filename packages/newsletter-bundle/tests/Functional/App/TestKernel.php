<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * An application as small as the bundle needs: FrameworkBundle with the mailer on the null transport, Messenger
 * handling every message at once but `SendEmailMessage`, routed to an asynchronous transport in memory as an
 * application that queues its emails does, CSRF tokens and the translator, DoctrineBundle on SQLite, TwigBundle, Symfony
 * Security with an administrator signing in by HTTP Basic, and the newsletter of the site `example.com`.
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

    private function root(): string
    {
        return sys_get_temp_dir().'/phil-newsletter-bundle-test/'.hash('xxh128', __DIR__);
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'a-test-secret-of-enough-bytes',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'default_locale' => 'en',
            'translator' => ['fallbacks' => ['en']],
            'csrf_protection' => true,
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'mailer' => ['dsn' => 'null://null'],
            'messenger' => [
                'transports' => ['async' => 'in-memory://'],
                'routing' => [SendEmailMessage::class => 'async'],
            ],
            'scheduler' => null,
            'rate_limiter' => null,
            'cache' => ['app' => 'cache.adapter.array'],
        ]);
        $container->services()->set('logger', NullLogger::class);
        // On disk, as a shared pool would be: the kernel resets an array pool between two requests of a test.
        $container->services()->set('cache.rate_limiter')->parent('cache.adapter.filesystem')->tag('cache.pool')->public();
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///%kernel.cache_dir%/newsletter.sqlite']]);
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('security', [
            'password_hashers' => [InMemoryUser::class => 'plaintext'],
            'providers' => ['people' => ['memory' => ['users' => [
                'admin' => ['password' => 'admin', 'roles' => ['ROLE_ADMIN']],
                'reader' => ['password' => 'reader', 'roles' => ['ROLE_USER']],
                'editor' => ['password' => 'editor', 'roles' => ['ROLE_NEWSLETTER_APPROVER']],
            ]]]],
            'firewalls' => ['main' => ['http_basic' => null, 'provider' => 'people']],
        ]);
        $container->extension('phil_newsletter', [
            'check_mail_domain' => false,
            'sites' => ['default' => ['base_url' => 'https://example.com']],
            'profiles' => ['default' => ['from' => 'news@example.com', 'from_name' => 'Example']],
            'bounces' => ['verp' => ['domain' => 'example.com'], 'remote_event_consumer' => 'mail_provider'],
            'approval' => ['four_eyes' => true],
        ]);
        $container->services()->alias('test.connection', 'doctrine.dbal.default_connection')->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@PhilNewsletterBundle/config/routes.php')->prefix('/newsletter');
    }
}
