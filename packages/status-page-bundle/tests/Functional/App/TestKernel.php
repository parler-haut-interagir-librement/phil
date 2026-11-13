<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Tests\Functional\App;

use FilesystemIterator;
use Override;
use Phil\MonitoringBundle\Application\Contract\MonitoringStatus;
use Psr\Log\NullLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle with Messenger and the Scheduler, TwigBundle with the
 * translator, TwigComponentBundle with the recipes of the kit copied where `ux:install` would put them, a clock fixed
 * at 2026-11-12 12:00 UTC, incidents and a maintenance in the configuration, a snapshot target in a directory of the
 * cache — and, when asked, the read model of phil/monitoring-bundle with its database down.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /** The recipes the templates of the bundle call. */
    public const array RECIPES = ['badge', 'card'];

    public function __construct(
        string $environment,
        bool $debug,
        private readonly bool $monitoring = false,
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

    public function snapshotDirectory(): string
    {
        return $this->root().'/snapshot';
    }

    private function root(): string
    {
        return sys_get_temp_dir().'/phil-status-page-bundle-test/'.hash('xxh128', __DIR__.($this->monitoring ? 'monitoring' : 'declared'));
    }

    /**
     * The templates of the recipes, where the kit's manifests copy them.
     */
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
            'messenger' => ['transports' => ['sync' => 'sync://'], 'routing' => []],
            'scheduler' => true,
        ]);

        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set('clock', MockClock::class)->args(['2026-11-12 12:00:00', 'UTC'])->public();

        if ($this->monitoring) {
            $services->set(MonitoringStatus::class, MonitoringStub::class)->public();
        }

        $container->extension('twig', ['strict_variables' => true, 'default_path' => __DIR__.'/templates', 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);

        $container->extension('phil_status_page', [
            'title' => 'Example status',
            'components' => [
                'website' => ['name' => 'Website', 'description' => 'The public pages', 'checks' => ['database']],
                'api' => ['name' => 'API', 'group' => 'Platform'],
                'sign-in' => ['name' => 'Sign-in', 'group' => 'Platform', 'status' => 'degraded'],
            ],
            'records' => [
                'incidents' => [
                    ['id' => 'api-errors', 'title' => 'Errors <b>on</b> the API', 'impact' => 'partial_outage', 'components' => ['api'], 'updates' => [
                        ['at' => '2026-11-12T11:00:00Z', 'state' => 'investigating', 'message' => "Looking.\nSecond line."],
                    ]],
                    ['id' => 'past', 'title' => 'Past outage', 'impact' => 'major_outage', 'components' => ['website'], 'updates' => [
                        ['at' => '2026-11-10T10:00:00Z', 'state' => 'investigating', 'message' => 'Down.'],
                        ['at' => '2026-11-10T11:00:00Z', 'state' => 'resolved', 'message' => 'Back.'],
                    ]],
                ],
                'maintenances' => [
                    ['id' => 'upgrade', 'title' => 'Upgrade', 'components' => ['website'], 'starts_at' => '2026-11-20T02:00:00Z', 'ends_at' => '2026-11-20T03:00:00Z'],
                ],
            ],
            'snapshot' => [
                'base_url' => 'https://status.example.org/',
                'schedule' => '5 minutes',
                'targets' => ['mirror' => ['type' => 'directory', 'path' => $this->snapshotDirectory()]],
            ],
        ]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@PhilStatusPageBundle/config/routes.php');
    }
}
