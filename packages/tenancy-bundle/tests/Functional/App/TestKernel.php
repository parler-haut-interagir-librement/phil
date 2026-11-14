<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Functional\App;

use Override;
use Phil\TenancyBundle\Infrastructure\Messenger\TenantMiddleware;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle with a mock session and three cache pools built on
 * `cache.app`, DoctrineBundle on SQLite — or on the server `PHIL_TENANCY_TEST_DATABASE_URL` names —, and the bundle
 * (`config/bundles.php`) configured with two adapters and a selector route. The account's entitlements come from
 * `X-Reachable-Tenants`, or from `X-Account` through a stand-in for the firewall, its remembered choice from
 * `X-Active-Tenant`.
 *
 * The kernel's argument is laid over that `phil_tenancy` configuration, so that a test can boot the same
 * application on another one, or on one the bundle refuses; each configuration compiles a container of its own.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $configuration laid over the `phil_tenancy` configuration
     * @param ?string              $database      the URL of the database, instead of {@see self::databaseUrl()}
     */
    public function __construct(string $environment = 'test', bool $debug = true, private readonly array $configuration = [], private readonly ?string $database = null)
    {
        parent::__construct($environment, $debug);
    }

    public static function databaseUrl(): string
    {
        $url = getenv('PHIL_TENANCY_TEST_DATABASE_URL');

        return \is_string($url) && '' !== $url ? $url : 'sqlite:///%kernel.cache_dir%/tenancy.sqlite';
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        // One container per database and configuration: both are compiled into it.
        return sys_get_temp_dir().'/phil-tenancy-bundle-test/'.$this->fingerprint().'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-tenancy-bundle-test/'.$this->fingerprint().'/log';
    }

    private function fingerprint(): string
    {
        return hash('xxh128', __DIR__.$this->database().json_encode($this->configuration, \JSON_THROW_ON_ERROR));
    }

    private function database(): string
    {
        return $this->database ?? self::databaseUrl();
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
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            // Two pools built on cache.app, one of them tag-aware, and a pool every tenant shares when
            // phil_tenancy.cache.global_pools lists it; public, so that the tests can read them.
            'cache' => ['pools' => [
                'app.prices' => ['public' => true],
                'app.catalogue' => ['public' => true, 'tags' => true],
                'app.exchange_rates' => ['public' => true],
            ]],
            'messenger' => [
                'transports' => ['async' => 'in-memory://'],
                'routing' => [CountInvoices::class => 'async'],
                'buses' => ['messenger.bus.default' => ['middleware' => [TenantMiddleware::class]]],
            ],
        ]);

        $container->extension('doctrine', [
            'dbal' => ['url' => $this->database()],
            'orm' => [
                'mappings' => [
                    'App' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => __DIR__.'/Entity',
                        'prefix' => 'Phil\TenancyBundle\Tests\Functional\App\Entity',
                    ],
                ],
            ],
        ]);

        $services = $container->services();
        $services->defaults()->autowire();

        // The 403 the tests ask for is logged; the default logger would print it.
        $services->set('logger', NullLogger::class);

        $services->set(HeaderActiveTenantStore::class)->public();
        $services->set(HeaderReachableTenants::class);
        $services->set('security.firewall', StandInFirewall::class)->tag('kernel.event_listener', ['event' => 'kernel.request', 'method' => 'onRequest', 'priority' => -20]);
        $services->set(TenancyController::class)->tag('controller.service_arguments')->public();
        $services->set(TenantCommand::class)->tag('console.command')->public();
        $services->set(CountInvoicesHandler::class)->tag('messenger.message_handler')->public();

        $container->extension('phil_tenancy', [
            'store' => HeaderActiveTenantStore::class,
            'reachable_tenants' => HeaderReachableTenants::class,
            'request' => ['selector_route' => 'tenant_select', 'ignored_routes' => ['currencies']],
            ...$this->configuration,
        ]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('invoices', '/invoices')->controller([TenancyController::class, 'invoices']);
        $routes->add('currencies', '/currencies')->controller([TenancyController::class, 'currencies']);
        $routes->add('current', '/current')->controller([TenancyController::class, 'current']);
        $routes->add('optional_with_attribute', '/optional-with-attribute')->controller([TenancyController::class, 'optional'])->defaults(['tenant' => null]);
        $routes->add('optional', '/optional')->controller([TenancyController::class, 'optional']);
        $routes->add('tenant_select', '/tenant/select')->controller([TenancyController::class, 'select']);
        $routes->import('@PhilTenancyBundle/config/routes.php');
    }
}
