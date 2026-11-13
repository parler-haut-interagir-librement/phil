<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Tests\Functional\App;

use Override;
use Phil\EmbedBundle\Tests\Fixtures\Keys;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle, Twig, the bundle with two keys and two embeds — a
 * calendar for the origins of the tests, by its route's `_embed` default, and a form for any origin, by the attribute
 * of its controller —, the script's routes under `/_embed`, and a page of embed codes. In the environment "security",
 * phil/browser-security-bundle sends the policy, which the framing then changes.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /** The origin of the page that embeds, in the browser suite. */
    public const string HOST = 'http://localhost:9188';

    /** The origin of the site, in the browser suite. */
    public const string SITE = 'http://localhost:9189';

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-embed-bundle-test/'.hash('xxh128', __DIR__).'/'.$this->environment.'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-embed-bundle-test/'.hash('xxh128', __DIR__).'/'.$this->environment.'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'a test secret that is long enough',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['utf8' => true],
        ]);
        $container->extension('twig', ['strict_variables' => true, 'default_path' => __DIR__.'/templates']);
        $container->extension('phil_embed', [
            'allow_localhost' => true,
            'keys' => [['id' => '2026-11', 'secret' => Keys::CURRENT], ['id' => '2026-05', 'secret' => Keys::PREVIOUS]],
            'embeds' => [
                'calendar' => ['origins' => ['https://partner.example', self::HOST], 'themes' => ['light', 'dark'], 'default_theme' => 'light'],
                'form' => [],
            ],
        ]);

        if ('security' === $this->environment) {
            $container->extension('phil_browser_security', [
                'policies' => ['default' => ['directives' => ['default-src' => ["'self'"], 'frame-ancestors' => ["'self'"]]]],
                'reporting' => ['rate_limit' => ['enabled' => false]],
            ]);
        }

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set(PageController::class)->public();
        $services->set(FormController::class)->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/../../../config/routes.php')->prefix('/_embed');
        $routes->add('calendar', '/embed/calendar/{id}')->controller(PageController::class)->defaults(['_embed' => 'calendar', 'template' => 'frame.html.twig']);
        $routes->add('form', '/embed/form')->controller(FormController::class);
        $routes->add('plain', '/plain')->controller(PageController::class)->defaults(['template' => 'frame.html.twig']);
        $routes->add('codes', '/codes')->controller(PageController::class)->defaults(['template' => 'codes.html.twig']);
        $routes->add('host', '/host')->controller(PageController::class)->defaults(['template' => 'host.html.twig']);
    }
}
