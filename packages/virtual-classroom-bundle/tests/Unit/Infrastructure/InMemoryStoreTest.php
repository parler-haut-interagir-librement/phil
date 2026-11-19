<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\VirtualClassroom\ParticipantId;
use Phil\VirtualClassroom\Recording;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroomBundle\Application\Contract\ClassroomStore;
use Phil\VirtualClassroomBundle\Domain\Model\Room;
use Phil\VirtualClassroomBundle\Infrastructure\Memory\InMemoryClassroomStore;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryClassroomStore::class)]
final class InMemoryStoreTest extends StoreTestCase
{
    public function testItIsEmptiedBetweenRequests(): void
    {
        $store = new InMemoryClassroomStore();
        $store->saveRoom(new Room(RoomId::fromString('room1'), 'main', 'Algebra', false, null, null, self::at(0)));
        $store->arrive(RoomId::fromString('room1'), ParticipantId::fromString('ada'), self::at(0));
        $store->saveRecording(new Recording('r', RoomId::fromString('room1'), 'video'));

        $store->reset();

        self::assertNull($store->room(RoomId::fromString('room1')));
        self::assertSame([], $store->stays(RoomId::fromString('room1')));
        self::assertSame([], $store->recordings(RoomId::fromString('room1')));
    }

    public function testDigitsAreAParticipantAndARoom(): void
    {
        $store = new InMemoryClassroomStore();
        $store->arrive(RoomId::fromString('12'), ParticipantId::fromString('7'), self::at(0));

        self::assertSame(['7'], self::names($store->present(RoomId::fromString('12'))));
        self::assertSame('12', $store->staysOf(ParticipantId::fromString('7'))[0]['room']);
        self::assertSame(1, $store->leaveAll(RoomId::fromString('12'), self::at(5)));
    }

    #[Override]
    protected function store(): ClassroomStore
    {
        return new InMemoryClassroomStore();
    }
}
