<?php

declare(strict_types=1);

namespace Phil\SlugBundle\Tests\Functional\HistoryApp;

use Override;
use Phil\SlugBundle\Application\Contract\SlugHistory;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application with the slug history enabled: FrameworkBundle, DoctrineBundle on SQLite (or on the database of
 * `PHIL_SLUG_TEST_DATABASE_URL`), and a frozen clock.
 */
final class HistoryKernel extends Kernel
{
    use MicroKernelTrait;

    public const string NOW = '2026-11-10 12:00:00';

    public const string HISTORY = 'test.slug_history';

    public static function databaseUrl(): string
    {
        $url = getenv('PHIL_SLUG_TEST_DATABASE_URL');

        return \is_string($url) && '' !== $url ? $url : 'sqlite:///%kernel.cache_dir%/history.sqlite';
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-slug-bundle-history-test/'.hash('xxh128', __DIR__.self::databaseUrl()).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-slug-bundle-history-test/'.hash('xxh128', __DIR__.self::databaseUrl()).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
        ]);

        $container->extension('doctrine', [
            'dbal' => ['url' => self::databaseUrl()],
            'orm' => [],
        ]);

        $container->extension('phil_slug', ['history' => ['clock' => 'test.frozen_clock']]);

        $services = $container->services();
        $services->set('test.frozen_clock', MockClock::class)->args([self::NOW]);
        // The history as the application's services receive it, through its autowiring alias.
        $services->alias(self::HISTORY, SlugHistory::class)->public();
    }
}
