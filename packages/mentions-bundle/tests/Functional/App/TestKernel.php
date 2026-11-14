<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Functional\App;

use Override;
use Phil\MentionsBundle\Tests\Unit\Fixture\Directory;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle, DoctrineBundle on SQLite, Twig, the notifications
 * bundle with its store in memory, the comments bundle with its own, and the bundle on a directory of three members.
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
        return sys_get_temp_dir().'/phil-mentions-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-mentions-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'csrf_protection' => true,
        ]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:']]);
        $container->extension('twig', ['default_path' => __DIR__.'/templates']);
        $container->extension('phil_notifications', ['store' => 'memory']);
        $container->extension('phil_comments', ['store' => 'memory']);
        $container->extension('phil_mentions', ['member_directory' => Directory::class]);

        $services = $container->services()->defaults()->autowire();
        $services->set('logger', NullLogger::class);
        $services->set('clock', MockClock::class)->args(['2026-11-08 10:00:00 UTC'])->public();
        $services->set(Directory::class)->args([['user:3']]);
    }
}
