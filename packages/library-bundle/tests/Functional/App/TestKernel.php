<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Tests\Functional\App;

use FilesystemIterator;
use Override;
use Phil\LibraryBundle\PhilLibraryBundle;
use Psr\Log\NullLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle with forms, CSRF on a mocked session, the translator in
 * `en` and Messenger on a synchronous bus; DoctrineBundle on a SQLite file; Twig and TwigComponentBundle with the
 * recipes of the PHIL UX kit copied where `ux:install` would put them, in the `ui` subdirectory of the Toolkit; a clock
 * stopped in Montréal; no log; the role and the member of a request read from its headers; the pages under `/library`.
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
        return $this->root().'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->root().'/log';
    }

    public function root(): string
    {
        return sys_get_temp_dir().'/phil-library-bundle-test/'.hash('xxh128', __DIR__);
    }

    /**
     * The templates of the recipes the bundle declares, where the kit's manifests copy them.
     */
    private function installRecipes(): string
    {
        $target = $this->root().'/templates/components/ui';
        $kit = array_find(
            [__DIR__.'/../../../../../vendor/phil/ux-kit', __DIR__.'/../../../vendor/phil/ux-kit'],
            static fn (string $directory): bool => is_file($directory.'/manifest.json'),
        ) ?? throw new RuntimeException('phil/ux-kit is not installed.');

        foreach (PhilLibraryBundle::RECIPES as $recipe) {
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
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'default_locale' => 'en',
            'translator' => ['fallbacks' => ['en']],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'csrf_protection' => true,
            'form' => ['csrf_protection' => true],
            'validation' => true,
            'messenger' => ['default_bus' => 'messenger.bus.default', 'buses' => ['messenger.bus.default' => []]],
        ]);
        $container->parameters()->set('.ux_toolkit.components.subdirectory', 'ui');
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///'.$this->root().'/library.sqlite'], 'orm' => []]);
        $container->extension('twig', ['strict_variables' => true, 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('phil_library', [
            'timezone' => 'America/Toronto',
            'loans' => ['days' => 14, 'max_renewals' => 1, 'max_open' => 3],
            'reminders' => ['before_due' => [1440]],
            'per_page' => 2,
            'member_resolver' => HeaderAccess::class,
        ]);

        $services = $container->services();
        $services->set('clock', MockClock::class)->args(['2026-11-13 10:00:00', 'America/Toronto'])->public();
        $services->set(HeaderAccess::class)->args([new Reference('request_stack')]);
        $services->alias('security.authorization_checker', HeaderAccess::class);
        $services->set(ReminderHandler::class)->tag('messenger.message_handler')->public();
        $services->set('logger', NullLogger::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@PhilLibraryBundle/config/routes.php')->prefix('/library');
    }
}
