<?php

declare(strict_types=1);

namespace Phil\StructuredDataBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle, Twig, a menu of phil/menu-bundle, the site and its
 * owner in the configuration, a provider of the application, and a page whose template adds its event.
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
        return sys_get_temp_dir().'/phil-structured-data-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-structured-data-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
        ]);
        $container->extension('twig', ['default_path' => __DIR__.'/templates']);
        $container->extension('phil_social_cards', ['preview' => ['enabled' => false]]);
        $container->extension('phil_structured_data', [
            'request_language' => true,
            'website' => ['name' => 'Example Press', 'search_url' => 'https://example.com/recherche?q={search_term_string}'],
            'organization' => ['name' => 'Example Press', 'logo' => 'https://example.com/logo.png'],
            'breadcrumb' => ['menu' => 'main'],
            'nonce' => ['request_attribute' => 'script_nonce'],
        ]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set(MainMenu::class);
        $services->set(TelephoneProvider::class)->public();
        $services->set(PageController::class)->public()->tag('controller.service_arguments');
        $services->set('logger', NullLogger::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('event', '/jeux/tournoi')->controller([PageController::class, 'event']);
        $routes->add('shared', '/partage')->controller([PageController::class, 'shared']);
        $routes->add('withdrawn', '/jeux/prive')->controller([PageController::class, 'withdrawn']);
        $routes->add('plain', '/plain')->controller([PageController::class, 'plain']);
    }
}
