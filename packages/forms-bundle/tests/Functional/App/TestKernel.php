<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Functional\App;

use Override;
use Phil\FormsBundle\PhilFormsBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle with forms, the translator in `fr`, a mailer that
 * sends nowhere — `SendEmailMessage` routed to an asynchronous transport in memory, as an application that queues its
 * emails does — and an HTTP client that answers every webhook; DoctrineBundle on SQLite; TwigBundle; the spam
 * protection with a proof of work of 4 bits and no minimum filling time; the bundle configured in YAML, from
 * `config/packages/phil_forms.yaml`, as an application does; the forms under `/forms`.
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
        return sys_get_temp_dir().'/phil-forms-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getShareDir(): string
    {
        return sys_get_temp_dir().'/phil-forms-bundle-test/'.hash('xxh128', __DIR__).'/share';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-forms-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'a test secret of sixteen bytes',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'form' => ['csrf_protection' => false],
            'csrf_protection' => false,
            'translator' => ['fallbacks' => ['fr']],
            'default_locale' => 'fr',
            'mailer' => ['dsn' => 'null://null'],
            'messenger' => [
                'transports' => ['async' => 'in-memory://'],
                'routing' => [SendEmailMessage::class => 'async'],
            ],
        ]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:'], 'orm' => []]);
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('phil_spam_protection', ['timing' => ['minimum' => 0, 'maximum' => 600], 'proof_of_work' => 4]);
        $container->import(__DIR__.'/config/packages/*.yaml');

        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set(SentMessages::class)->tag('kernel.event_subscriber')->public();
        $services->set(Webhooks::class)->public();
        $services->alias('http_client', Webhooks::class);
        // The test has no DNS: the webhooks go straight to the recording client.
        $services->alias(PhilFormsBundle::WEBHOOK_CLIENT, Webhooks::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/../../../config/routes.php')->prefix('/forms');
    }
}
