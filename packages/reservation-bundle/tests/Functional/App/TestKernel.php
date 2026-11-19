<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Tests\Functional\App;

use Override;
use Phil\ReservationBundle\Domain\Event\ReservationConfirmed;
use Phil\ReservationBundle\Domain\Event\ReservationHeld;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * An application as small as the bundle needs: FrameworkBundle with forms, the translator in `fr`, Messenger on a
 * synchronous bus and the event dispatcher with a listener of the reservations; DoctrineBundle on SQLite;
 * PhilRecurrenceBundle and PhilCalendarBundle in Toronto.
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
        return sys_get_temp_dir().'/phil-reservation-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-reservation-bundle-test/'.hash('xxh128', __DIR__).'/log';
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
            'form' => ['csrf_protection' => false],
            'translator' => ['fallbacks' => ['fr']],
            'messenger' => ['default_bus' => 'messenger.bus.default', 'buses' => ['messenger.bus.default' => []]],
        ]);

        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:'], 'orm' => []]);
        $container->extension('phil_calendar', ['default_timezone' => 'America/Toronto']);
        $container->extension('phil_reservation', ['batch' => 50]);

        // The form factory, the reminders handled and the events heard, for the tests to read.
        $services = $container->services();
        $services->alias('test.form.factory', 'form.factory')->public();
        $services->set(ReminderHandler::class)->tag('messenger.message_handler')->public();
        $services->set(RecordedEvents::class)->public()
            ->tag('kernel.event_listener', ['event' => ReservationHeld::class, 'method' => 'held'])
            ->tag('kernel.event_listener', ['event' => ReservationConfirmed::class, 'method' => 'confirmed']);

        // An application requires FrameworkBundle, and with it the event dispatcher, in production; installed on its
        // own, the bundle has them in development only, which the container takes as absent.
        $services->set('event_dispatcher', EventDispatcher::class)->public()->tag('event_dispatcher.dispatcher', ['name' => 'event_dispatcher']);
        $services->alias(EventDispatcherInterface::class, 'event_dispatcher');
        $services->alias(PsrEventDispatcherInterface::class, 'event_dispatcher');
    }
}
