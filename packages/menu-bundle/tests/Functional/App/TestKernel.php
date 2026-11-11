<?php

declare(strict_types=1);

namespace Phil\MenuBundle\Tests\Functional\App;

use Override;
use Phil\MenuBundle\Tests\Unit\Fixtures\MainMenu;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Environment;

/**
 * An application as small as the bundle needs: FrameworkBundle with the router and the translator in `fr`,
 * TwigBundle, a main menu found by autoconfiguration, a decorator of the site, and pages that render the templates.
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
        return sys_get_temp_dir().'/phil-menu-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-menu-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    public function page(Environment $twig): Response
    {
        return new Response($twig->render('page.html.twig'));
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'default_locale' => 'fr',
            'translator' => ['default_path' => __DIR__.'/translations', 'fallbacks' => ['fr']],
        ]);
        $container->extension('twig', ['strict_variables' => true, 'default_path' => __DIR__.'/templates']);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set('logger', NullLogger::class);
        $services->set(MainMenu::class);
        $services->set(SiteDecorator::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        foreach (['home' => '/', 'games' => '/games', 'game' => '/games/{slug}', 'about' => '/about'] as $name => $path) {
            $routes->add($name, $path)->controller('kernel::page');
        }
    }
}
