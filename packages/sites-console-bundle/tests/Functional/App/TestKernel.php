<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Functional\App;

use FilesystemIterator;
use Override;
use Phil\SitesConsoleBundle\Tests\Unit\Support\Keys;
use Psr\Log\NullLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * An application that is both the console and a site: FrameworkBundle with Messenger, sessions and CSRF,
 * SecurityBundle with a viewer and an operator behind HTTP basic, TwigBundle and TwigComponentBundle with the recipes
 * of the kit copied where `ux:install` would put them, phil/monitoring-bundle for the health, DoctrineBundle on
 * SQLite, a clock fixed at 2026-11-20 10:00 UTC — and the console's HTTP client handing each request back to the
 * application, whose agent answers as the site "shop". A rotating kernel gives the console its next key.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /** The recipes the templates of the bundle call. */
    public const array RECIPES = ['badge', 'button', 'card', 'table'];

    public function __construct(
        string $environment,
        bool $debug,
        private readonly string $store = 'memory',
        private readonly bool $rotating = false,
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
        return $this->root().'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->root().'/log';
    }

    public function root(): string
    {
        return sys_get_temp_dir().'/phil-sites-console-bundle-test/'.hash('xxh128', __DIR__.$this->store.($this->rotating ? ':rotating' : ''));
    }

    private function installRecipes(): string
    {
        $target = $this->root().'/templates/components/ui';
        $kit = array_find(
            [__DIR__.'/../../../../../../phil/packages/ux-kit', __DIR__.'/../../../../../vendor/phil/ux-kit', __DIR__.'/../../../vendor/phil/ux-kit'],
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
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['utf8' => true],
            'default_locale' => 'en',
            'translator' => ['fallbacks' => ['en']],
            'cache' => ['app' => 'cache.adapter.array'],
            'csrf_protection' => true,
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'messenger' => ['transports' => [], 'routing' => []],
            'lock' => 'flock',
            'http_client' => true,
        ]);
        $container->extension('security', [
            'password_hashers' => [InMemoryUser::class => 'plaintext'],
            'providers' => ['people' => ['memory' => ['users' => [
                'viewer' => ['password' => 'p', 'roles' => ['ROLE_SITES_CONSOLE_VIEWER']],
                'operator' => ['password' => 'p', 'roles' => ['ROLE_SITES_CONSOLE_VIEWER', 'ROLE_SITES_CONSOLE_OPERATOR']],
                'nobody' => ['password' => 'p', 'roles' => ['ROLE_USER']],
            ]]]],
            'firewalls' => ['console' => ['pattern' => '^/admin', 'http_basic' => null, 'provider' => 'people']],
        ]);
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///'.$this->root().'/console.sqlite'],
            'orm' => [],
        ]);
        $container->extension('phil_monitoring', []);

        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set('clock', MockClock::class)->args(['2026-11-20 10:00:00', 'UTC'])->public();
        $services->set('test.loopback', LoopbackHttpClient::class)->args([new ServiceClosureArgument(new Reference('http_kernel'))]);
        $services->set(MemoryApplier::class)->public();
        // The agent's pool reads the fixed clock, as the idempotency keys and the nonces do.
        $services->set('test.agent_cache', ArrayAdapter::class)->args([0, true, 0, 0, new Reference('clock')]);

        $container->extension('twig', ['strict_variables' => true, 'default_path' => __DIR__.'/templates', 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);

        $container->extension('phil_sites_console', [
            'console' => [
                'private_key' => Keys::CONSOLE_SEED,
                'next_private_key' => $this->rotating ? Keys::NEXT_SEED : null,
                'store' => $this->store,
                'http_client' => 'test.loopback',
                'watched_packages' => ['phil/*'],
            ],
            'agent' => [
                'site_id' => 'shop',
                'private_key' => Keys::SITE_SEED,
                'console_public_key' => Keys::console()->verifyingKey()->toBase64(),
                'lock_file' => __DIR__.'/composer.lock',
                'configuration_keys' => ['mail.*'],
                'configuration_applier' => MemoryApplier::class,
                'update_runner' => MemoryApplier::class,
                'cache_pool' => 'test.agent_cache',
            ],
        ]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@PhilSitesConsoleBundle/config/routes/console.php')->prefix('/admin/sites');
        $routes->import('@PhilSitesConsoleBundle/config/routes/agent.php')->prefix('/_sites-console');
    }
}
