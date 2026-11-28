<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Tests\Functional\App;

use Override;
use Phil\WaitlistBundle\Domain\Event\EntryJoined;
use Phil\WaitlistBundle\Domain\Event\OfferAccepted;
use Phil\WaitlistBundle\Domain\Event\OfferLapsed;
use Phil\WaitlistBundle\Domain\Event\PlaceOffered;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * An application as small as the bundle needs: FrameworkBundle, DoctrineBundle on SQLite, the notifications bundle
 * with its notifications in memory and the two types the waiting lists send, a clock the test moves, and a listener of
 * the waiting lists.
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
        return sys_get_temp_dir().'/phil-waitlist-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-waitlist-bundle-test/'.hash('xxh128', __DIR__).'/log';
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
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:'], 'orm' => []]);
        $container->extension('phil_notifications', [
            'store' => 'memory',
            'preferences' => 'config',
            'types' => ['waitlist.offered' => [], 'waitlist.lapsed' => []],
        ]);
        $container->extension('phil_waitlist', ['offer_window' => 600, 'max_places' => 4]);

        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set('clock', MockClock::class)->args(['2026-11-08 10:00:00 UTC'])->public();
        $listener = $services->set(RecordedEvents::class)->public();

        foreach ([EntryJoined::class, PlaceOffered::class, OfferAccepted::class, OfferLapsed::class] as $event) {
            $listener->tag('kernel.event_listener', ['event' => $event]);
        }

        // An application requires FrameworkBundle, and with it the event dispatcher, in production; installed on its
        // own, the bundle has them in development only, which the container takes as absent.
        $services->set('event_dispatcher', EventDispatcher::class)->public()->tag('event_dispatcher.dispatcher', ['name' => 'event_dispatcher']);
        $services->alias(EventDispatcherInterface::class, 'event_dispatcher');
        $services->alias(PsrEventDispatcherInterface::class, 'event_dispatcher');
    }
}
