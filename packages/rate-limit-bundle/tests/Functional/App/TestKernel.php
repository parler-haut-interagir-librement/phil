<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle with forms and the translator in `fr`, TwigBundle,
 * the limiters' state in a filesystem pool, and actions limited by attribute, by route default and by form name, one
 * by a limiter in observation. In the
 * `invalid` environment, one more route names a limiter nothing declares.
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
        return sys_get_temp_dir().'/phil-rate-limit-bundle-test/'.hash('xxh128', $this->environment).'/cache';
    }

    #[Override]
    public function getShareDir(): string
    {
        return sys_get_temp_dir().'/phil-rate-limit-bundle-test/'.hash('xxh128', $this->environment).'/share';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-rate-limit-bundle-test/'.hash('xxh128', $this->environment).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'form' => ['csrf_protection' => false],
            'translator' => ['fallbacks' => ['fr']],
            'default_locale' => 'fr',
            'cache' => ['pools' => ['cache.limits' => ['adapter' => 'cache.adapter.filesystem']]],
        ]);
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('phil_rate_limit', [
            'cache_pool' => 'cache.limits',
            'limiters' => [
                'burst' => ['policy' => 'sliding_window', 'limit' => 50, 'interval' => '1 minute'],
                'search' => ['policy' => 'fixed_window', 'limit' => 2, 'interval' => '1 minute'],
                'api' => ['policy' => 'token_bucket', 'limit' => 1, 'rate' => ['interval' => '1 hour']],
                'forms' => ['policy' => 'fixed_window', 'limit' => 1, 'interval' => '10 minutes'],
                'watched' => ['policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 minute', 'observe' => true],
            ],
            'forms' => ['contact' => 'forms'],
        ]);

        // The 429 responses the tests ask for are logged; the default logger would print them.
        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set(RecordingSubscriber::class)->autoconfigure()->public();
        $services->set(TestController::class)->autowire()->tag('controller.service_arguments')->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        if ('invalid' === $this->environment) {
            $routes->add('unknown', '/unknown')->controller([TestController::class, 'search'])->defaults(['_rate_limit' => 'nothing']);
        }

        $routes->add('search', '/search')->controller([TestController::class, 'search']);
        $routes->add('api', '/api')->controller([TestController::class, 'api'])->defaults(['_rate_limit' => 'api', '_format' => 'json']);
        $routes->add('watched', '/watched')->controller([TestController::class, 'api'])->defaults(['_rate_limit' => 'watched', '_format' => 'json']);
        $routes->add('contact', '/contact')->controller([TestController::class, 'contact']);
    }
}
