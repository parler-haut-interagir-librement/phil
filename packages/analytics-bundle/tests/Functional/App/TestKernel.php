<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle, DoctrineBundle on a SQLite file unless a test leaves it
 * out, the bundle under the `phil_analytics` block a test hands the constructor, and the collection route imported
 * under `/analytics`.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $analyticsConfig the `phil_analytics` block
     */
    public function __construct(
        string $environment,
        bool $debug,
        private readonly array $analyticsConfig = [],
        private readonly bool $doctrine = true,
    ) {
        parent::__construct($environment, $debug);
    }

    /**
     * The bundles of `config/bundles.php`, or of `config/bundles_without_doctrine.php`.
     */
    private function getBundlesPath(): string
    {
        return __DIR__.'/config/'.($this->doctrine ? 'bundles.php' : 'bundles_without_doctrine.php');
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        // One container per configuration: the block is compiled into it.
        return sys_get_temp_dir().'/phil-analytics-bundle-test/'.$this->configurationHash().'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-analytics-bundle-test/'.$this->configurationHash().'/log';
    }

    private function configurationHash(): string
    {
        return hash('xxh128', __DIR__.json_encode([$this->analyticsConfig, $this->doctrine], \JSON_THROW_ON_ERROR));
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

        // Nothing the tests do is meant to be logged; the default logger would print it.
        $container->services()->set('logger', NullLogger::class);

        if ($this->doctrine) {
            $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///%kernel.cache_dir%/analytics.sqlite']]);
        }

        $container->extension('phil_analytics', $this->analyticsConfig);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@PhilAnalyticsBundle/config/routes.php')->prefix('/analytics');
    }
}
