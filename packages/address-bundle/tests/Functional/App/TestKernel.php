<?php

declare(strict_types=1);

namespace Phil\AddressBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle with forms, the validator and the translator in
 * `fr_CA`; DoctrineBundle on SQLite with a `Shop` that keeps its address; TwigBundle; addresses in Canada by default,
 * the format endpoint under `/address-format`.
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
        return sys_get_temp_dir().'/phil-address-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-address-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'default_locale' => 'fr_CA',
            'form' => ['csrf_protection' => false],
            'validation' => true,
            'translator' => ['fallbacks' => ['fr']],
        ]);
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm' => ['mappings' => ['App' => ['type' => 'attribute', 'is_bundle' => false, 'dir' => __DIR__.'/Entity', 'prefix' => 'Phil\AddressBundle\Tests\Functional\App\Entity']]],
        ]);
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('phil_address', ['default_country' => 'CA', 'format_url' => '/address-format/__COUNTRY__']);
        $container->services()->set('logger', NullLogger::class);
        $container->services()->alias('test.form.factory', 'form.factory')->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/../../../config/routes.php')->prefix('/address-format');
    }
}
