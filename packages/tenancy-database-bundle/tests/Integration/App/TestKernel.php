<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Integration\App;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application on a real server: FrameworkBundle, DoctrineBundle on the shared database `phil_tdb_shared`, the
 * tenant of each host — `retail.test`, `wholesale.test`, `newcomer.test` — and the bundle, with `wholesale` and
 * `newcomer` each in a database of its own, kept in memory or, with stored placements, in the table of the shared
 * database. The suspensions are in the table of the shared database. Its migrations are `doctrine:schema:update
 * --force`, run by `console.php`.
 *
 * The server is the one of {@see self::URL_VARIABLE}, which `console.php` reads too.
 *
 * @phpstan-import-type Params from DriverManager
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public const string URL_VARIABLE = 'PHIL_TENANCY_DATABASE_TEST_SERVER_URL';

    public const string SHARED = 'phil_tdb_shared';

    public const array DEDICATED = ['wholesale' => 'phil_tdb_wholesale', 'newcomer' => 'phil_tdb_newcomer'];

    /**
     * @param bool $storedPlacements whether the placements are read from the table of the shared database, rather than
     *                               from {@see MemoryPlacements}
     */
    public function __construct(string $environment, bool $debug, private readonly bool $storedPlacements = false)
    {
        parent::__construct($environment, $debug);
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    /**
     * Beside the kernel, out of a `config/` directory, where the kernel would write its configuration reference.
     */
    private function getBundlesPath(): string
    {
        return __DIR__.'/bundles.php';
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-tenancy-database-bundle-test/'.$this->fingerprint().'/'.$this->environment.'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-tenancy-database-bundle-test/'.$this->fingerprint().'/'.$this->environment.'/log';
    }

    /**
     * The connection parameters of one database on the server, as DBAL reads them.
     *
     * @return Params
     */
    public static function parameters(?string $database = self::SHARED): array
    {
        $url = $_SERVER[self::URL_VARIABLE] ?? getenv(self::URL_VARIABLE);
        \assert(\is_string($url) && '' !== $url);
        $parameters = new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url);
        unset($parameters['dbname']);

        return null === $database ? $parameters : [...$parameters, 'dbname' => $database];
    }

    /**
     * The configuration of DoctrineBundle for the shared database.
     *
     * @return array<string, mixed>
     */
    public static function connection(): array
    {
        $parameters = self::parameters();

        return array_filter([
            'driver' => $parameters['driver'] ?? null,
            'host' => $parameters['host'] ?? null,
            'port' => $parameters['port'] ?? null,
            'user' => $parameters['user'] ?? null,
            'password' => $parameters['password'] ?? null,
            'dbname' => self::SHARED,
            'server_version' => $parameters['serverVersion'] ?? null,
        ], static fn (mixed $value): bool => null !== $value);
    }

    private function fingerprint(): string
    {
        return hash('xxh128', __DIR__.json_encode([self::connection(), $this->storedPlacements], \JSON_THROW_ON_ERROR));
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
            'trusted_hosts' => ['^[a-z]+\.test$'],
        ]);

        $container->extension('doctrine', [
            'dbal' => self::connection(),
            'orm' => [
                'mappings' => [
                    'App' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => __DIR__.'/Entity',
                        'prefix' => 'Phil\TenancyDatabaseBundle\Tests\Integration\App\Entity',
                    ],
                ],
            ],
        ]);

        $services = $container->services();
        $services->defaults()->autowire();
        $services->set('logger', NullLogger::class);

        if (!$this->storedPlacements) {
            $services->set(MemoryPlacements::class)->args([self::DEDICATED])->public();
        }

        $services->set(TenantsController::class)->tag('controller.service_arguments')->public();

        $container->extension('phil_tenancy', [
            'hosts' => ['retail.test' => 'retail', 'wholesale.test' => 'wholesale', 'newcomer.test' => 'newcomer'],
        ]);

        $container->extension('phil_tenancy_database', [
            'placement' => $this->storedPlacements ? 'dbal' : MemoryPlacements::class,
            'retry_after' => 30,
            'migrations' => [
                'console' => __DIR__.'/console.php',
                'command' => ['doctrine:schema:update', '--force'],
                'concurrency' => 2,
                'timeout' => 120,
            ],
        ]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('invoices', '/invoices')->controller([TenantsController::class, 'invoices']);
        $routes->add('both', '/both')->controller([TenantsController::class, 'both']);
    }
}
