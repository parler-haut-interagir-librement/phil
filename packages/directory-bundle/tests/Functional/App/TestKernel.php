<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Tests\Functional\App;

use Override;
use Phil\DirectoryBundle\Infrastructure\Security\ListingVoter;
use Phil\DirectoryBundle\Infrastructure\Sitemap\DirectoryUrlProvider;
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
 * An application as small as the directory needs: FrameworkBundle, DoctrineBundle on SQLite, TwigBundle, the token
 * storage and the voters of Symfony Security, content-bundle in French, the contact, address and geo bundles, and a
 * directory of podcasts, larps and larp sites, a larp played at sites and heard on one podcast.
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
        return sys_get_temp_dir().'/phil-directory-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-directory-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            // A debug kernel tells robots noindex on every page; the listing's own choice is what is tested.
            'disallow_search_engine_index' => false,
            'php_errors' => ['log' => true],
            'default_locale' => 'fr',
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'form' => ['csrf_protection' => false],
            'translator' => ['default_path' => '%kernel.project_dir%/translations'],
        ]);
        $container->services()->set('logger', NullLogger::class);
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///%kernel.cache_dir%/directory.sqlite'],
            'orm' => [],
        ]);
        $container->extension('twig', ['strict_variables' => true]);

        // Symfony Security without its bundle: the token the tests sign in with, and the voters that decide.
        $services = $container->services();
        $services->set('security.token_storage', TokenStorage::class)->public();
        $services->set('test.role_voter', RoleVoter::class);
        $services->set('security.access.decision_manager', AccessDecisionManager::class)
            ->args([[new Reference(ListingVoter::class), new Reference('test.role_voter')]]);
        $services->set('security.authorization_checker', AuthorizationChecker::class)
            ->args([new Reference('security.token_storage'), new Reference('security.access.decision_manager')]);
        $services->set('twig.extension.security', SecurityExtension::class)
            ->args([new Reference('security.authorization_checker')])
            ->tag('twig.extension');

        // The pages of the sitemap, which no sitemap bundle asks for here.
        $services->alias('test.directory_urls', DirectoryUrlProvider::class)->public();
        $container->extension('phil_content', ['locales' => ['fr' => []]]);
        $container->extension('phil_directory', [
            // The pages of the sitemap are on http://localhost, which they may be in tests only.
            'allow_localhost' => true,
            'genres' => [
                'podcast' => ['path' => 'balados', 'title' => 'Balados'],
                'larp' => ['path' => 'gn', 'title' => 'GN'],
                'larp_site' => ['path' => 'terrains', 'title' => 'Terrains de GN'],
                'season_2' => ['path' => 'saison-2', 'title' => 'Saison 2'],
            ],
            'relations' => [
                'played_at' => ['from' => 'larp', 'to' => 'larp_site', 'title' => 'Joué à', 'inverse_title' => 'GN joués ici'],
                'heard_on' => ['from' => 'larp', 'to' => 'podcast', 'multiple' => false, 'title' => 'Entendu sur', 'inverse_title' => 'GN entendus ici'],
            ],
            'page_size' => 2,
            'words' => ['new' => 'ajouter', 'edit' => 'modifier', 'editors' => 'redacteurs', 'history' => 'historique', 'publish' => 'publier'],
        ]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'phil_directory');
    }
}
