<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Tests\Functional\App;

use Override;
use Phil\TranslationBundle\Tests\Unit\Fixture\Policy;
use Phil\TranslationBundle\Tests\Unit\Fixture\Sources;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle with a router, DoctrineBundle on SQLite, TwigBundle and
 * the bundle, its routes imported, its documents and policy those of the unit tests.
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
        return sys_get_temp_dir().'/phil-translation-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-translation-bundle-test/'.hash('xxh128', __DIR__).'/log';
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
        ]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:']]);
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('phil_translation', ['granularity' => 'sentence', 'sources' => Sources::class, 'policy' => Policy::class]);

        $services = $container->services()->defaults()->autowire();
        $services->set('logger', NullLogger::class);
        $services->set('clock', MockClock::class)->args(['2026-11-06 10:00:00 UTC'])->public();
        $services->set(Sources::class);
        $services->set(Policy::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 3).'/config/routes.php')->prefix('/translate');
    }
}
