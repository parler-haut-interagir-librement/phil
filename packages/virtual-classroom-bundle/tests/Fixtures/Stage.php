<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Tests\Fixtures;

use DateTimeImmutable;
use Phil\VirtualClassroom\Capabilities;
use Phil\VirtualClassroomBundle\Application\Classrooms;
use Phil\VirtualClassroomBundle\Application\Contract\ClassroomStore;
use Phil\VirtualClassroomBundle\Application\Providers;
use Phil\VirtualClassroomBundle\Infrastructure\Memory\InMemoryClassroomStore;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * The service of the rooms on two fake servers — `main`, which can do everything, and `basic`, which can only open
 * rooms and send notifications —, a store in memory, a clock at 1 700 000 000 and the events kept.
 */
final readonly class Stage
{
    public const int NOW = 1_700_000_000;

    public FakeProvider $main;

    public FakeProvider $basic;

    public ClassroomStore $store;

    public MockClock $clock;

    public RecordingDispatcher $events;

    public Providers $providers;

    public Classrooms $classrooms;

    /**
     * @param bool            $dispatching whether the service has a dispatcher for its events
     * @param ?ClassroomStore $store       the store of the service, one in memory when null
     */
    public function __construct(bool $dispatching = true, ?ClassroomStore $store = null)
    {
        $this->main = new FakeProvider();
        $this->basic = new FakeProvider(new Capabilities(false, false, false, true), null);
        $this->store = $store ?? new InMemoryClassroomStore();
        $this->clock = new MockClock(new DateTimeImmutable('@'.self::NOW));
        $this->events = new RecordingDispatcher();
        $main = $this->main;
        $basic = $this->basic;
        $this->providers = new Providers(new ServiceLocator(['main' => static fn (): FakeProvider => $main, 'basic' => static fn (): FakeProvider => $basic]), ['main', 'basic'], 'main');
        $this->classrooms = new Classrooms($this->store, $this->providers, new FixedCallbacks(), $this->clock, $dispatching ? $this->events : null);
    }

    public function at(int $offset): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.(self::NOW + $offset));
    }
}
