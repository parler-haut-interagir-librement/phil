<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the editor needs: FrameworkBundle with forms and a router, SecurityBundle with one lazy
 * firewall that lets anonymous visitors through, TwigBundle, phil/ujo-bundle and the bundle, its routes imported inside
 * the firewall with no `access_control` rule, an uploader and two searches of its own.
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
        return sys_get_temp_dir().'/phil-block-editor-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-block-editor-bundle-test/'.hash('xxh128', __DIR__).'/log';
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
            'form' => ['csrf_protection' => false],
            'router' => ['utf8' => true],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
        ]);
        $container->extension('security', [
            'providers' => ['people' => ['memory' => ['users' => ['ada' => ['password' => 'not-used', 'roles' => ['ROLE_USER']]]]]],
            'firewalls' => ['main' => ['lazy' => true, 'provider' => 'people']],
        ]);
        $container->extension('twig', ['strict_variables' => true, 'default_path' => '%kernel.project_dir%/templates']);
        $container->extension('phil_block_editor', [
            'preset' => 'reduced',
            'image_uploader' => FakeUploader::class,
            'mention_search' => FakeSearch::class,
        ]);
        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set(FakeUploader::class);
        $services->set(FakeSearch::class);
        $services->alias('test.form.factory', 'form.factory')->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 3).'/config/routes.php')->prefix('/_block-editor');
    }
}
