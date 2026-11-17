<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Tests\Functional\App;

use Override;
use Phil\Outbox\RecordedEvent;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle with its lock, DoctrineBundle with the ORM on SQLite,
 * the outbox in its table, a generic event type and a listener of it, dated by a frozen clock.
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
        return sys_get_temp_dir().'/phil-outbox-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-outbox-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'lock' => 'flock',
        ]);
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///%kernel.cache_dir%/outbox.sqlite'],
            'orm' => [],
        ]);
        $container->extension('phil_outbox', [
            'generic_types' => ['quiz.completed'],
            'relay' => ['batch_size' => 2, 'max_attempts' => 2, 'first_delay' => 10],
        ]);

        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set('clock', MockClock::class)->args(['2026-11-17 10:00:00', 'UTC'])->public();
        $services->set(QuizListener::class)->public()->tag('kernel.event_listener', ['event' => RecordedEvent::class]);

        // Installed on its own, the bundle has symfony/event-dispatcher in development only, which Symfony then counts
        // as missing; an application has it through HttpKernel.
        $services->set('event_dispatcher', EventDispatcher::class)->public()->tag('event_dispatcher.dispatcher', ['name' => 'event_dispatcher']);
    }
}
