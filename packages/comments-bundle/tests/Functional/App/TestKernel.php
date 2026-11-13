<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Functional\App;

use FilesystemIterator;
use Override;
use Psr\Log\NullLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Environment;

/**
 * An application as small as the bundle needs: FrameworkBundle with a router and the translator, DoctrineBundle on
 * SQLite, TwigBundle, TwigComponentBundle with the recipes of the kit copied where `ux:install` would put them — in
 * the subdirectory of the application's Toolkit configuration, the Toolkit itself stood for by its parameter — and the
 * bundle, its routes imported, an author named by a header, a policy of its own, and an article whose page carries the
 * comments on the whole page. `$ariaRoles` turns the roles of WAI-ARIA 1.3 on.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /** The recipes the template of the page calls. */
    public const array RECIPES = ['alert', 'badge', 'button', 'card', 'field', 'textarea'];

    public function __construct(
        string $environment,
        bool $debug,
        private readonly bool $ariaRoles = false,
        private readonly string $subdirectory = 'ui',
    ) {
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
        return $this->root().'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->root().'/log';
    }

    public function article(): Response
    {
        $services = $this->getContainer()->get('test.service_container');
        \assert($services instanceof ContainerInterface);
        $twig = $services->get('twig');
        \assert($twig instanceof Environment);

        return new Response($twig->render('article.html.twig'));
    }

    private function root(): string
    {
        return sys_get_temp_dir().'/phil-comments-bundle-test/'.hash('xxh128', __DIR__.($this->ariaRoles ? 'aria' : '').$this->subdirectory);
    }

    /**
     * The templates of the recipes, where the kit's manifests copy them.
     */
    private function installRecipes(): string
    {
        $target = rtrim($this->root().'/templates/components/'.$this->subdirectory, '/');
        // The kit beside this package in the monorepo, else among the development requirements of the monorepo's root,
        // else among those of the package alone: a kit is a directory with its manifest.
        $kit = array_find(
            [__DIR__.'/../../../../ux-kit', __DIR__.'/../../../../../vendor/phil/ux-kit'],
            static fn (string $directory): bool => is_file($directory.'/manifest.json'),
        ) ?? __DIR__.'/../../../vendor/phil/ux-kit';

        foreach (self::RECIPES as $recipe) {
            $source = $kit.'/'.$recipe.'/templates/components';

            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)) as $file) {
                \assert($file instanceof SplFileInfo);
                $copy = $target.substr($file->getPathname(), \strlen($source));

                if (!is_dir(\dirname($copy))) {
                    mkdir(\dirname($copy), recursive: true);
                }

                copy($file->getPathname(), $copy);
            }
        }

        return $this->root().'/templates';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->parameters()->set('.ux_toolkit.components.subdirectory', '' === $this->subdirectory ? null : $this->subdirectory);
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['utf8' => true],
            'default_locale' => 'en',
            'translator' => ['fallbacks' => ['en']],
        ]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:']]);
        $container->extension('twig', ['strict_variables' => true, 'default_path' => __DIR__.'/templates', 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('phil_comments', ['author_provider' => HeaderAuthor::class, 'policy' => EditorsPolicy::class, 'aria_roles' => $this->ariaRoles]);

        $services = $container->services()->defaults()->autowire();
        $services->set('logger', NullLogger::class);
        $services->set('clock', MockClock::class)->args(['2026-11-06 10:00:00 UTC'])->public();
        $services->set(HeaderAuthor::class);
        $services->set(EditorsPolicy::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 3).'/config/routes.php')->prefix('/_comments');
        $routes->import(\dirname(__DIR__, 3).'/config/page_routes.php')->prefix('/_comments');
        $routes->add('article', '/article')->controller($this->article(...))->methods(['GET']);
    }
}
