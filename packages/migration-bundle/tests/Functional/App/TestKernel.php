<?php

declare(strict_types=1);

namespace Phil\MigrationBundle\Tests\Functional\App;

use Override;
use Phil\Migration\Publisher;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle with a chatter, DBAL on SQLite in memory, the
 * migrations of `Migrations/`, two channels, and a publisher that keeps what it is given instead of the chatter.
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
        return sys_get_temp_dir().'/phil-migration-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-migration-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'default_locale' => 'fr',
            'notifier' => ['chatter_transports' => ['discord_alice' => 'null://null']],
        ]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:']]);
        $container->extension('doctrine_migrations', ['migrations_paths' => [__NAMESPACE__.'\Migrations' => '%kernel.project_dir%/Migrations']]);
        $container->extension('phil_migration', ['channels' => [
            'discord_alice' => ['title' => '# Nouvelle version{counter}', 'continued' => '## Suite{counter}'],
            'news' => ['transport' => 'discord_news', 'content' => 'news', 'heading' => '# {title}', 'link' => 'Pour voir la nouvelle {url}'],
        ]]);

        $container->services()->set(RecordingPublisher::class)->public();
        $container->services()->alias(Publisher::class, RecordingPublisher::class);
        $container->services()->set('logger', NullLogger::class);
    }
}
