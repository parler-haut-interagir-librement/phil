<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Tests\Functional\App;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\ReactionsBundle\Domain\Model\Reaction;
use Phil\ReactionsBundle\Domain\Repository\ReactionRepository;
use Phil\ReactionsBundle\Infrastructure\Twig\KitComponents;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs, with the recipes of one kit copied where `ux:install` would put them, in
 * the subdirectory of the application's Toolkit configuration: the Toolkit itself is stood for by its parameter. The
 * reactions are kept by the Doctrine store in a SQLite file of the kernel's own, so that a request reads what an earlier
 * one wrote, in a worker or a process per request alike. With a project directory, AssetMapperBundle is registered too.
 */
final class TestKernel extends Kernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    public function __construct(
        string $environment,
        bool $debug,
        private readonly string $kit = 'ux-kit',
        private readonly string $subdirectory = 'ui',
        private readonly int $perMember = 1,
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
     * Empties the reactions: the table is created again from the mapping, as an application's schema tools create it.
     */
    public function resetDatabase(): void
    {
        $entityManager = $this->getContainer()->get('test.entity_manager');
        \assert($entityManager instanceof EntityManagerInterface);
        $tables = [$entityManager->getClassMetadata(Reaction::class)];
        $schema = new SchemaTool($entityManager);
        $schema->dropSchema($tables);
        $schema->createSchema($tables);
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

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        $container->getDefinition(KitComponents::class)->setPublic(true);
    }

    private function root(): string
    {
        return sys_get_temp_dir().'/phil-reactions-bundle-test/'.hash('xxh128', __DIR__.$this->kit.$this->subdirectory.$this->perMember.$this->projectDir);
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
        $target = rtrim($this->root().'/templates/components/'.$this->subdirectory, '/');
        if (!is_dir($target)) {
            mkdir($target, recursive: true);
        }

        // The kit beside this package in the monorepo, else among the development requirements of the monorepo's root,
        // else among those of the package alone: a kit is a directory with its manifest, not a directory left behind
        // by a kit that moved away, with only its caches.
        $kit = array_find(
            [__DIR__.'/../../../../'.$this->kit, __DIR__.'/../../../../../vendor/phil/'.$this->kit],
            static fn (string $directory): bool => is_file($directory.'/manifest.json'),
        ) ?? __DIR__.'/../../../vendor/phil/'.$this->kit;

        foreach (['reaction-button/templates/components/ReactionButton', 'reaction-picker/templates/components/ReactionPicker'] as $recipe) {
            if (!copy($kit.'/'.$recipe.'.html.twig', $target.'/'.basename($recipe).'.html.twig')) {
                throw new RuntimeException(\sprintf('The recipe %s of the kit %s cannot be copied from %s.', basename($recipe), $this->kit, $kit));
            }
        }

        return $this->root().'/templates';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->parameters()->set('.ux_toolkit.components.subdirectory', '' === $this->subdirectory ? null : $this->subdirectory);
        $container->extension('framework', [
            'secret' => 'a test secret that is long enough',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'default_locale' => 'fr',
            'csrf_protection' => true,
            'translator' => ['fallbacks' => ['fr']],
            'router' => ['utf8' => true],
        ]);
        $container->extension('twig', ['strict_variables' => true, 'default_path' => __DIR__.'/templates', 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('doctrine', ['dbal' => ['driver' => 'pdo_sqlite', 'path' => $this->root().'/reactions.sqlite'], 'orm' => []]);

        if (null !== $this->projectDir) {
            $container->extension('asset_mapper', ['paths' => []]);
        }

        $container->extension('phil_reactions', [
            'unicode' => [
                ['code' => 'like', 'emoji' => '👍', 'label' => 'reaction.like'],
                ['code' => 'not_useful', 'emoji' => '🫤', 'label' => 'reaction.thanks', 'private' => true],
                ['code' => 'refused', 'emoji' => '🚫', 'label' => 'reaction.laugh'],
                ['code' => 'spark', 'emoji' => '✨', 'label' => 'reaction.celebrate', 'quick' => false],
            ],
            'custom' => [['code' => 'gnome', 'image' => '/e/gnome.webp', 'alt' => 'reaction.love', 'label' => 'reaction.insightful']],
            'visibility' => ['names' => 'members'],
            'access' => HeaderMember::class,
            'current_member' => HeaderMember::class,
            'member_names' => HeaderMember::class,
            'per_member' => $this->perMember,
        ]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set(HeaderMember::class);
        $services->set(PageController::class)->public();
        $services->alias('test.reactions', ReactionRepository::class)->public();
        $services->alias('test.entity_manager', 'doctrine.orm.entity_manager')->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/../../../config/routes.php')->prefix('/_reactions');
        $routes->import('@LiveComponentBundle/config/routes.php')->prefix('/_components');
        $routes->add('page', '/article')->controller(PageController::class);
    }
}
