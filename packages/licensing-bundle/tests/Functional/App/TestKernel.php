<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Functional\App;

use Override;
use Phil\LicensingBundle\Application\Event\ExportChecking;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle with the translator, the forms and the validator,
 * DoctrineBundle on an in-memory SQLite database, TwigBundle and SecurityBundle, and a license of the application.
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
        return sys_get_temp_dir().'/phil-licensing-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-licensing-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'default_locale' => 'en',
            'translator' => ['default_path' => '%kernel.project_dir%/translations', 'fallbacks' => ['en']],
            'form' => ['enabled' => true, 'csrf_protection' => false],
            'validation' => ['enabled' => true],
        ]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:'], 'orm' => []]);
        $container->extension('twig', ['default_path' => '%kernel.project_dir%/templates', 'strict_variables' => true]);
        $container->extension('security', ['firewalls' => ['main' => ['security' => false]]]);
        $container->extension('phil_licensing', ['licenses' => ['house' => ['name' => 'House license', 'url' => 'https://example.com/license', 'requirements' => ['attribution']]]]);

        // Nothing the tests do is meant to be logged; the default logger would print it.
        $container->services()->set('logger', NullLogger::class);
        // The forms the tests build, as a controller would have them injected.
        $container->services()->alias('test.form.factory', 'form.factory')->public();
        $container->services()->set(AttributionPageListener::class)->tag('kernel.event_listener', ['event' => ExportChecking::class]);
    }
}
