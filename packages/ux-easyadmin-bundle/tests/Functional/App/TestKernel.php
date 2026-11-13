<?php

declare(strict_types=1);

namespace Phil\UxEasyAdminBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An EasyAdmin application as small as the bundle needs: FrameworkBundle with AssetMapper, SecurityBundle, TwigBundle
 * and its extras, TwigComponentBundle, DoctrineBundle on SQLite, EasyAdmin with two dashboards, and the bundle.
 * `$config` is the bundle's configuration.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private readonly array $config = [])
    {
        parent::__construct('test', true);
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return $this->varDir().'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->varDir().'/log';
    }

    private function varDir(): string
    {
        return sys_get_temp_dir().'/phil-ux-easyadmin-bundle-test/'.hash('xxh128', __DIR__.serialize($this->config));
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'asset_mapper' => ['paths' => ['assets/']],
        ]);
        $container->extension('security', [
            'providers' => ['none' => ['memory' => null]],
            'firewalls' => ['main' => ['stateless' => true, 'provider' => 'none']],
        ]);
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:'], 'orm' => []]);
        $container->extension('phil_ux_easy_admin', $this->config);
        $container->services()->set('logger', NullLogger::class);
        $container->services()->defaults()->autowire()->autoconfigure()
            ->set(DashboardController::class)
            ->set(ListedDashboardController::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'easyadmin.routes');
    }
}
