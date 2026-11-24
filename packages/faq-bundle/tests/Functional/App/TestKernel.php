<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Tests\Functional\App;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs, with the recipes of the PHIL UX kit copied where `ux:install` would put
 * them, in the subdirectory of the application's Toolkit configuration: the Toolkit itself is stood for by its
 * parameter. The questions are kept in a SQLite file of the kernel's own, so that a request reads what an earlier one
 * wrote. The administration is guarded by the roles of the `X-Roles` header. With a project directory, AssetMapperBundle
 * is registered too.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /** The recipes of the kit the component draws. */
    public const array RECIPES = ['button/templates/components/Button', 'input/templates/components/Input', 'copy-button/templates/components/CopyButton'];

    public function __construct(
        string $environment,
        bool $debug,
        private readonly ?string $projectDir = null,
    ) {
        parent::__construct($environment, $debug);
    }

    #[Override]
    public function getProjectDir(): string
    {
        return $this->projectDir ?? __DIR__;
    }

    /**
     * Empties the tables: they are created again from the mapping, as an application's schema tools create them.
     */
    public function resetDatabase(): void
    {
        $entityManager = $this->getContainer()->get('test.entity_manager');
        \assert($entityManager instanceof EntityManagerInterface);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schema = new SchemaTool($entityManager);
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
    }

    #[Override]
    public function getCacheDir(): string
    {
        return $this->root().'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->root().'/log';
    }

    private function root(): string
    {
        return sys_get_temp_dir().'/phil-faq-bundle-test/'.hash('xxh128', __DIR__.$this->projectDir);
    }

    /**
     * Replaces the trait's own, which reads `config/bundles.php` only.
     */
    private function getBundlesPath(): string
    {
        return __DIR__.'/config/'.(null === $this->projectDir ? 'bundles.php' : 'bundles-with-assets.php');
    }

    /**
     * The templates of the recipes, where the kit's manifests copy them.
     */
    private function installRecipes(): string
    {
        $target = $this->root().'/templates/components/ui';

        if (!is_dir($target)) {
            mkdir($target, recursive: true);
        }

        // The kit beside this package in the public monorepo, else among the development requirements of the monorepo's
        // root, else among those of the package alone.
        $kit = array_find(
            [__DIR__.'/../../../../../../phil/packages/ux-kit', __DIR__.'/../../../../../vendor/phil/ux-kit', __DIR__.'/../../../../ux-kit'],
            static fn (string $directory): bool => is_file($directory.'/manifest.json'),
        ) ?? __DIR__.'/../../../vendor/phil/ux-kit';

        foreach (self::RECIPES as $recipe) {
            if (!copy($kit.'/'.$recipe.'.html.twig', $target.'/'.basename($recipe).'.html.twig')) {
                throw new RuntimeException(\sprintf('The recipe %s of the kit cannot be copied from %s.', basename($recipe), $kit));
            }
        }

        return $this->root().'/templates';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->parameters()->set('.ux_toolkit.components.subdirectory', 'ui');
        $container->extension('framework', [
            'secret' => 'a test secret that is long enough',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'default_locale' => 'en',
            'enabled_locales' => ['en', 'fr'],
            'set_locale_from_accept_language' => true,
            'csrf_protection' => true,
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'translator' => ['fallbacks' => ['en']],
            'router' => ['utf8' => true],
        ]);
        $container->extension('twig', ['strict_variables' => true, 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('doctrine', ['dbal' => ['driver' => 'pdo_sqlite', 'path' => $this->root().'/faq.sqlite'], 'orm' => []]);

        if (null !== $this->projectDir) {
            $container->extension('asset_mapper', ['paths' => []]);
        }

        $container->extension('phil_faq', [
            'contact_url' => '/contact',
            'admin' => ['enabled' => true, 'role' => 'ROLE_EDITOR', 'locales' => ['en', 'fr']],
        ]);

        $services = $container->services();
        $services->set('security.authorization_checker', RoleChecker::class)->args([new Reference('request_stack')])->public();
        $services->alias('test.entity_manager', 'doctrine.orm.entity_manager')->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/../../../config/routes.php')->prefix('/faq');
        $routes->import(__DIR__.'/../../../config/admin_routes.php')->prefix('/admin/faq');
    }
}
