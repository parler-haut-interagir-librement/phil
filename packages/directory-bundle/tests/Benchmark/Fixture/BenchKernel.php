<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Tests\Benchmark\Fixture;

use Override;
use Phil\DirectoryBundle\Application\Directory;
use Phil\DirectoryBundle\Infrastructure\Security\ListingVoter;
use Psr\Log\NullLogger;
use Symfony\Bridge\Twig\Extension\SecurityExtension;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;

/**
 * The application of the functional suite (`TestKernel`), on SQLite in memory and in a cache directory of its own,
 * so a benchmark never touches the database file of a test running beside it: larps played at larp sites.
 */
final class BenchKernel extends Kernel
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
        return sys_get_temp_dir().'/phil-directory-bundle-bench/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-directory-bundle-bench/'.hash('xxh128', __DIR__).'/log';
    }

    /**
     * The bundles of the functional suite's application.
     */
    private function getBundlesPath(): string
    {
        return \dirname(__DIR__, 2).'/Functional/App/config/bundles.php';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'bench',
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'default_locale' => 'fr',
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'form' => ['csrf_protection' => false],
        ]);
        $container->services()->set('logger', NullLogger::class);
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm' => [],
        ]);

        $services = $container->services();
        $services->set('security.token_storage', TokenStorage::class)->public();
        $services->set('bench.role_voter', RoleVoter::class);
        $services->set('security.access.decision_manager', AccessDecisionManager::class)
            ->args([[new Reference(ListingVoter::class), new Reference('bench.role_voter')]]);
        $services->set('security.authorization_checker', AuthorizationChecker::class)
            ->args([new Reference('security.token_storage'), new Reference('security.access.decision_manager')]);
        $services->set('twig.extension.security', SecurityExtension::class)
            ->args([new Reference('security.authorization_checker')])
            ->tag('twig.extension');

        $services->alias('bench.directory', Directory::class)->public();
        $services->alias('bench.entity_manager', 'doctrine.orm.entity_manager')->public();

        $container->extension('phil_content', ['locales' => ['fr' => []]]);
        $container->extension('phil_directory', [
            'genres' => [
                'larp' => ['path' => 'gn', 'title' => 'GN'],
                'larp_site' => ['path' => 'terrains', 'title' => 'Terrains de GN'],
            ],
            'relations' => [
                'played_at' => ['from' => 'larp', 'to' => 'larp_site', 'title' => 'Joué à', 'inverse_title' => 'GN joués ici'],
            ],
        ]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'phil_directory');
    }
}
