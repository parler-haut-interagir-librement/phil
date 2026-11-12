<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle, DoctrineBundle on SQLite, and the bundle with
 * problems under `/api/` — or under the block a test hands the constructor —; the endpoints of `ApiController`.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $apiContractConfig the `phil_api_contract` block
     */
    public function __construct(string $environment, bool $debug, private readonly array $apiContractConfig = [])
    {
        parent::__construct($environment, $debug);
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
        return sys_get_temp_dir().'/phil-api-contract-bundle-test/'.$this->configurationHash().'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-api-contract-bundle-test/'.$this->configurationHash().'/log';
    }

    private function configurationHash(): string
    {
        return hash('xxh128', __DIR__.json_encode($this->apiContractConfig, \JSON_THROW_ON_ERROR));
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

        // Nothing the tests do is meant to be logged; the default logger would print it.
        $container->services()->set('logger', NullLogger::class);

        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:'], 'orm' => []]);
        $container->services()->set(ApiController::class)->public()->tag('controller.service_arguments');
        $container->extension('phil_api_contract', ['problem_details' => ['paths' => ['^/api/']], ...$this->apiContractConfig]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('orders', '/api/orders')->controller([ApiController::class, 'createOrder'])->methods(['POST']);
        $routes->add('failing', '/api/failing')->controller([ApiController::class, 'failing'])->methods(['POST']);
        $routes->add('missing', '/api/missing')->controller([ApiController::class, 'missing']);
        $routes->add('old', '/api/old')->controller([ApiController::class, 'old']);
        $routes->add('old_orders', '/api/old-orders')->controller([ApiController::class, 'oldOrder'])->methods(['POST']);
        $routes->add('page', '/page')->controller([ApiController::class, 'missing']);
    }
}
