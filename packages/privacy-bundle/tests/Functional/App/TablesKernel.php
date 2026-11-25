<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Functional\App;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Override;
use Phil\PrivacyBundle\Infrastructure\Doctrine\PrivacySchemaListener;
use stdClass;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application with Twig, Messenger, a lock and an SQLite database in memory, with or without symfony/security-bundle:
 * which tables the bundle adds to the schema. The schema listener is public under {@see self::LISTENER}, and the
 * services `app.deletions`, `app.proofs`, `app.incidents` and `app.email_tracking` stand for stores of the application.
 */
final class TablesKernel extends Kernel
{
    use MicroKernelTrait;

    public const string LISTENER = 'test.privacy_schema_listener';

    /**
     * @param array<string, mixed> $configuration the `phil_privacy` configuration
     */
    public function __construct(private readonly bool $security, private readonly array $configuration = [])
    {
        parent::__construct('test', true);
    }

    /**
     * The bundles: those of `tables/config/bundles.php`, with symfony/security-bundle in
     * `tables/config/bundles-with-security.php`.
     */
    private function getBundlesPath(): string
    {
        return __DIR__.'/tables/config/'.($this->security ? 'bundles-with-security.php' : 'bundles.php');
    }

    #[Override]
    public function getProjectDir(): string
    {
        // Outside the repository: the kernel writes the reference of its configuration under config/ of its project.
        return $this->varDir().'/project';
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
        return sys_get_temp_dir().'/phil-privacy-bundle-tables/'.hash('xxh128', __DIR__.json_encode([$this->security, $this->configuration], \JSON_THROW_ON_ERROR));
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
            'csrf_protection' => ['enabled' => true],
            'lock' => 'flock',
            'messenger' => ['default_bus' => 'messenger.bus.default', 'buses' => ['messenger.bus.default' => null]],
        ]);
        $container->extension('twig', ['default_path' => __DIR__.'/templates']);

        if ($this->security) {
            $container->extension('security', ['firewalls' => ['main' => ['security' => false]]]);
        }

        $container->extension('phil_privacy', ['account_deletion' => ['export_directory' => $this->varDir().'/exports']]);
        $container->extension('phil_privacy', $this->configuration);

        $services = $container->services();
        $services->set('database_connection', Connection::class)
            ->factory(DriverManager::getConnection(...))
            ->args([['driver' => 'pdo_sqlite', 'memory' => true]])
            ->public();
        $services->alias(self::LISTENER, PrivacySchemaListener::class)->public();

        foreach (['app.deletions', 'app.proofs', 'app.incidents', 'app.email_tracking'] as $id) {
            $services->set($id, stdClass::class);
        }
    }

    private function configureRoutes(): void
    {
    }
}
