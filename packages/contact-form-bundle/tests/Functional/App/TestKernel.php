<?php

declare(strict_types=1);

namespace Phil\ContactFormBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle with forms, the validator, the translator in `fr`, a
 * mailer that sends nowhere and a cache; TwigBundle; the spam protection with a proof of work of 4 bits and no
 * minimum filling time; the contact page under `/contact`.
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
        return sys_get_temp_dir().'/phil-contact-form-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getShareDir(): string
    {
        return sys_get_temp_dir().'/phil-contact-form-bundle-test/'.hash('xxh128', __DIR__).'/share';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-contact-form-bundle-test/'.hash('xxh128', __DIR__).'/log';
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
            'validation' => true,
            'translator' => ['fallbacks' => ['fr']],
            'default_locale' => 'fr',
            'mailer' => ['dsn' => 'null://null'],
        ]);
        $container->services()->set('logger', NullLogger::class);
        $container->services()->set(SentMessages::class)->tag('kernel.event_subscriber')->public();
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('phil_spam_protection', ['timing' => ['minimum' => 0, 'maximum' => 600], 'proof_of_work' => 4]);
        $container->extension('phil_contact_form', ['sender' => 'contact@example.com', 'recipients' => ['info@example.com'], 'subject_prefix' => '[Contact] ']);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/../../../config/routes.php')->prefix('/contact');
    }
}
