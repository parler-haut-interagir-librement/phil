<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle with forms, their CSRF protection and the translator
 * in `fr`; TwigBundle; the settings in memory, the user named by the `X-User` header, a section declared with
 * attributes, and the preferences under `/preferences`.
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
        return sys_get_temp_dir().'/phil-settings-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getShareDir(): string
    {
        return sys_get_temp_dir().'/phil-settings-bundle-test/'.hash('xxh128', __DIR__).'/share';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-settings-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'a test secret of sixteen bytes',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'csrf_protection' => true,
            'form' => ['csrf_protection' => true],
            'translator' => ['fallbacks' => ['fr']],
            'default_locale' => 'fr',
        ]);
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('phil_settings', ['store' => 'memory', 'current_user' => HeaderUser::class]);

        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set(HeaderUser::class)->args([new Reference('request_stack')]);
        $services->set(AuthorSettings::class)->autoconfigure();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/../../../config/routes.php')->prefix('/preferences');
    }
}
