<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Tests\Functional\App;

use FilesystemIterator;
use Override;
use Phil\AccessBundle\Tests\Fixtures\Page;
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
 * An application as small as the bundle needs: FrameworkBundle with sessions and CSRF, SecurityBundle with one session
 * firewall over members named by stable identifiers, TwigBundle, TwigComponentBundle with the recipes of the kit
 * copied where `ux:install` would put them — the Toolkit stood for by its parameter —, and the bundle on its in-memory
 * store, its routes imported, and a page that draws the banner, the summary and the Twig functions.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /** The recipes the templates of the bundle call. */
    public const array RECIPES = ['alert', 'badge', 'button', 'table'];

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

    public function page(): Response
    {
        $services = $this->getContainer()->get('test.service_container');
        \assert($services instanceof ContainerInterface);
        $twig = $services->get('twig');
        \assert($twig instanceof Environment);

        return new Response($twig->render('page.html.twig', ['page' => new Page('1')]));
    }

    private function root(): string
    {
        return sys_get_temp_dir().'/phil-access-bundle-test/'.hash('xxh128', __DIR__);
    }

    /**
     * The templates of the recipes, where the kit's manifests copy them.
     */
    private function installRecipes(): string
    {
        $target = $this->root().'/templates/components/ui';
        // The kit among the development requirements of the monorepo's root, else among those of the package alone.
        $kit = array_find(
            [__DIR__.'/../../../../../vendor/phil/ux-kit', __DIR__.'/../../../vendor/phil/ux-kit'],
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
        $container->parameters()->set('.ux_toolkit.components.subdirectory', 'ui');
        $container->extension('framework', [
            'secret' => 'a test secret of sixteen bytes',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'csrf_protection' => true,
            'router' => ['utf8' => true],
            'default_locale' => 'en',
            'translator' => ['fallbacks' => ['en']],
        ]);
        $container->extension('security', [
            'providers' => ['members' => ['memory' => ['users' => ['ada' => ['password' => 'p', 'roles' => ['ROLE_USER']], 'ann' => ['password' => 'p', 'roles' => ['ROLE_USER']], 'olga' => ['password' => 'p', 'roles' => ['ROLE_USER']]]]]],
            'firewalls' => ['main' => ['provider' => 'members', 'lazy' => true]],
        ]);
        $container->extension('twig', ['strict_variables' => true, 'default_path' => __DIR__.'/templates', 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('phil_access', [
            'store' => 'memory',
            'item_kinds' => ['page'],
            'view_as' => ['redirect' => '/page'],
            'groups' => ['supporters' => ['entry_points' => ['purchase'], 'duration' => 'P1M']],
        ]);

        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set('clock', MockClock::class)->args(['2026-11-13 10:00:00 UTC'])->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 3).'/config/routes.php')->prefix('/_access');
        $routes->add('page', '/page')->controller($this->page(...))->methods(['GET']);
    }
}
