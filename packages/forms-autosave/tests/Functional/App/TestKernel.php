<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle in French with its stateless CSRF protection,
 * DoctrineBundle on SQLite in memory, TwigBundle, Symfony's KeyManagement bundle with a local `sodium://` client and the bundle with its server store,
 * its routes imported, the user named by a header, and a page with a form kept as a draft.
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
        return sys_get_temp_dir().'/phil-forms-autosave-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-forms-autosave-test/'.hash('xxh128', __DIR__).'/log';
    }

    #[Override]
    public function boot(): void
    {
        $_SERVER['DRAFTS_KEY'] = rtrim(strtr(base64_encode(str_repeat('d', 32)), '+/', '-_'), '=');

        parent::boot();
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
            'default_locale' => 'fr',
            'translator' => ['fallbacks' => ['en']],
            'csrf_protection' => true,
            'form' => ['csrf_protection' => false],
        ]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:'], 'orm' => []]);
        $container->extension('twig', ['default_path' => '%kernel.project_dir%/templates', 'strict_variables' => true]);
        $container->extension('key_management', ['clients' => ['local' => 'sodium://?keys[d1]=%env(DRAFTS_KEY)%']]);
        $container->extension('phil_forms_autosave', ['server' => ['enabled' => true, 'key' => 'd1', 'owner' => HeaderOwner::class]]);

        $services = $container->services()->defaults()->autowire();
        $services->set('logger', NullLogger::class);
        $services->set('clock', MockClock::class)->args(['2026-11-08 15:00:00 UTC'])->public();
        $services->set(HeaderOwner::class);
        $services->set(PostController::class)->tag('controller.service_arguments')->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 3).'/config/routes.php')->prefix('/_autosave');
        $routes->add('post', '/posts/{id}')->controller(PostController::class)->methods(['GET', 'POST'])->requirements(['id' => '\d+']);
    }
}
