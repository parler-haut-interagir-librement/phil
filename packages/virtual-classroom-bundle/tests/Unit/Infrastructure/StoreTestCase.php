<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\VirtualClassroom\Attendance\Interval;
use Phil\VirtualClassroom\ParticipantId;
use Phil\VirtualClassroom\Recording;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroomBundle\Application\Contract\ClassroomStore;
use Phil\VirtualClassroomBundle\Domain\Model\Room;
use Phil\VirtualClassroomBundle\Domain\Status\RoomStatus;
use PHPUnit\Framework\TestCase;

/**
 * What every store promises: a room read back whole, the latest room of an owner, the open rooms oldest first, one
 * open stay per participant, an arrival reported twice kept once, a departure closing the latest open stay, every
 * stay of a participant found and forgotten, a recording kept once per identifier and format, the identifiers
 * compared byte for byte.
 */
abstract class StoreTestCase extends TestCase
{
    abstract protected function store(): ClassroomStore;

    public function testARoomIsReadBackWhole(): void
    {
        $store = $this->store();
        $bystander = new Room(RoomId::fromString('room4'), 'basic', 'Bystander', false, null, 'course:7', self::at(5));
        $store->saveRoom($bystander);
        $room = new Room(RoomId::fromString('room1'), 'main', 'Algebra', true, 90, 'course:42', self::at(0));
        $store->saveRoom($room);

        $read = $store->room(RoomId::fromString('room1'));
        self::assertNotNull($read);
        self::assertSame('room1', $read->id->value);
        self::assertSame('main', $read->provider);
        self::assertSame('Algebra', $read->title);
        self::assertTrue($read->record);
        self::assertSame(90, $read->maxMinutes);
        self::assertSame('course:42', $read->owner);
        self::assertSame(self::START, $read->createdAt->getTimestamp());
        self::assertSame(RoomStatus::Scheduled, $read->status);
        self::assertNull($read->sessionKey);
        self::assertNull($read->openedAt);
        self::assertNull($read->endedAt);
        self::assertNull($store->room(RoomId::fromString('room2')));

        $room->open('1700000000123', self::at(60));
        $room->end(self::at(3600));

        $store->saveRoom($room);
        $store->saveRoom($room);

        $saved = $store->room(RoomId::fromString('room1'));
        self::assertNotNull($saved);
        self::assertSame(RoomStatus::Ended, $saved->status);
        self::assertSame('1700000000123', $saved->sessionKey);
        self::assertSame(self::START + 60, $saved->openedAt?->getTimestamp());
        self::assertSame(self::START + 3600, $saved->endedAt?->getTimestamp());

        $left = $store->room(RoomId::fromString('room4'));
        self::assertNotNull($left);
        self::assertSame(['basic', 'Bystander', RoomStatus::Scheduled, 'course:7'], [$left->provider, $left->title, $left->status, $left->owner]);

        $plain = new Room(RoomId::fromString('room3'), 'main', 'Plain', false, null, null, self::at(0));
        $store->saveRoom($plain);
        $readPlain = $store->room(RoomId::fromString('room3'));
        self::assertNotNull($readPlain);
        self::assertFalse($readPlain->record);
        self::assertNull($readPlain->maxMinutes);
        self::assertNull($readPlain->owner);
    }

    public function testTheLatestRoomOfAnOwner(): void
    {
        $store = $this->store();
        $store->saveRoom(new Room(RoomId::fromString('older'), 'main', 'Older', false, null, 'course:42', self::at(0)));
        $store->saveRoom(new Room(RoomId::fromString('newer'), 'main', 'Newer', false, null, 'course:42', self::at(100)));
        $store->saveRoom(new Room(RoomId::fromString('other'), 'main', 'Other', false, null, 'Course:42', self::at(200)));

        self::assertSame('newer', $store->roomOfOwner('course:42')?->id->value);
        self::assertSame('other', $store->roomOfOwner('Course:42')?->id->value);
        self::assertNull($store->roomOfOwner('course:43'));
        self::assertNull($store->roomOfOwner('COURSE:42'));
    }

    public function testTwoRoomsOfAnOwnerMadeAtTheSameMomentGiveTheLastSaved(): void
    {
        $store = $this->store();
        $store->saveRoom(new Room(RoomId::fromString('tie-a'), 'main', 'First', false, null, 'course:42', self::at(0)));
        $store->saveRoom(new Room(RoomId::fromString('tie-b'), 'main', 'Second', false, null, 'course:42', self::at(0)));

        self::assertSame('tie-b', $store->roomOfOwner('course:42')?->id->value);
    }

    public function testTheOpenRoomsOldestFirst(): void
    {
        $store = $this->store();

        foreach ([['c', 300, true], ['a', 100, true], ['b', 200, false], ['d', 400, true]] as [$name, $offset, $open]) {
            $room = new Room(RoomId::fromString('room-'.$name), 'main', 'Room '.$name, false, null, null, self::at($offset));

            if ($open) {
                $room->open(null, self::at($offset));
            }

            $store->saveRoom($room);
        }

        $ended = new Room(RoomId::fromString('room-e'), 'main', 'Ended', false, null, null, self::at(50));
        $ended->open(null, self::at(50));
        $ended->end(self::at(60));

        $store->saveRoom($ended);

        self::assertList($store->openRooms(10));
        self::assertSame(['room-a', 'room-c', 'room-d'], array_map(static fn (Room $room): string => $room->id->value, $store->openRooms(10)));
        self::assertSame(['room-a', 'room-c'], array_map(static fn (Room $room): string => $room->id->value, $store->openRooms(2)));
    }

    public function testStaysAreOpenedOnceAndClosedLatestFirst(): void
    {
        $store = $this->store();
        $room = RoomId::fromString('room1');
        $ada = ParticipantId::fromString('ada');

        self::assertTrue($store->arrive($room, $ada, self::at(0)));
        self::assertList($store->present($room));
        self::assertList($store->stays($room));
        self::assertFalse($store->arrive($room, $ada, self::at(10)), 'A participant in the room does not arrive again.');
        self::assertSame(['ada'], self::names($store->present($room)));
        self::assertFalse($store->leave($room, $ada, self::at(-1)), 'A departure before the arrival closes nothing.');
        self::assertTrue($store->leave($room, $ada, self::at(100)));
        self::assertFalse($store->leave($room, $ada, self::at(110)), 'A participant who left does not leave again.');
        self::assertSame([], $store->present($room));
        self::assertFalse($store->arrive($room, $ada, self::at(0)), 'An arrival reported twice is kept once.');
        self::assertFalse($store->arrive($room, $ada, self::at(50)), 'An arrival within a known stay begins none.');
        self::assertFalse($store->arrive($room, $ada, self::at(100)), 'An arrival at the end of a known stay begins none.');
        self::assertTrue($store->arrive($room, $ada, self::at(101)));
        self::assertTrue($store->leave($room, $ada, self::at(200)));

        self::assertSame([['ada', [[0, 100], [101, 200]]]], self::flat($store->stays($room)));
    }

    public function testAParticipantLeavesAtTheMomentOfTheirArrival(): void
    {
        $store = $this->store();
        $room = RoomId::fromString('room1');
        $ada = ParticipantId::fromString('ada');
        $store->arrive($room, $ada, self::at(0));

        self::assertTrue($store->leave($room, $ada, self::at(0)));
        self::assertSame([['ada', [[0, 0]]]], self::flat($store->stays($room)));
    }

    public function testADepartureBeforeTheArrivalClosesNothingEvenAtTheEpoch(): void
    {
        $store = $this->store();
        $room = RoomId::fromString('room1');
        $ada = ParticipantId::fromString('ada');
        $epoch = new DateTimeImmutable('@0');
        $store->arrive($room, $ada, $epoch);

        self::assertFalse($store->leave($room, $ada, new DateTimeImmutable('@-5')));
        self::assertSame(['ada'], self::names($store->present($room)));
    }

    public function testAStayReportedLaterIsKeptBesideTheOthers(): void
    {
        $store = $this->store();
        $room = RoomId::fromString('room1');
        $ada = ParticipantId::fromString('ada');
        $store->arrive($room, $ada, self::at(500));
        $store->leave($room, $ada, self::at(900));

        self::assertTrue($store->arrive($room, $ada, self::at(100)));
        self::assertTrue($store->leave($room, $ada, self::at(300)));
        self::assertSame([['ada', [[100, 300], [500, 900]]]], self::flat($store->stays($room)));
    }

    public function testEveryOpenStayOfARoomIsClosed(): void
    {
        $store = $this->store();
        $room = RoomId::fromString('room1');
        $store->arrive($room, ParticipantId::fromString('ada'), self::at(0));
        $store->arrive($room, ParticipantId::fromString('bob'), self::at(10));
        $store->leave($room, ParticipantId::fromString('bob'), self::at(20));
        $store->arrive($room, ParticipantId::fromString('Ada'), self::at(30));
        $store->arrive(RoomId::fromString('room2'), ParticipantId::fromString('ada'), self::at(0));

        self::assertSame(['Ada', 'ada'], self::names($store->present($room)));
        self::assertSame(2, $store->leaveAll($room, self::at(60)));
        self::assertSame(0, $store->leaveAll($room, self::at(70)));
        self::assertSame([], $store->present($room));
        self::assertSame([['Ada', [[30, 60]]], ['ada', [[0, 60]]], ['bob', [[10, 20]]]], self::flat($store->stays($room)));
        self::assertSame(['ada'], self::names($store->present(RoomId::fromString('room2'))));
        self::assertSame([], $store->stays(RoomId::fromString('room3')));
    }

    public function testTheStaysOfAParticipantAreFoundAndForgotten(): void
    {
        $store = $this->store();
        $ada = ParticipantId::fromString('ada');
        $store->arrive(RoomId::fromString('room2'), $ada, self::at(100));
        $store->arrive(RoomId::fromString('room1'), $ada, self::at(0));
        $store->leave(RoomId::fromString('room1'), $ada, self::at(50));
        $store->arrive(RoomId::fromString('room1'), ParticipantId::fromString('bob'), self::at(0));

        $stays = $store->staysOf($ada);
        self::assertSame([['room1', 0, 50], ['room2', 100, null]], array_map(
            static fn (array $stay): array => [$stay['room'], $stay['interval']->joinedAt->getTimestamp() - self::START, null === $stay['interval']->leftAt ? null : $stay['interval']->leftAt->getTimestamp() - self::START],
            $stays,
        ));
        self::assertSame([], $store->staysOf(ParticipantId::fromString('Ada')));
        self::assertList($stays);

        self::assertSame(2, $store->forget($ada));
        self::assertSame(0, $store->forget($ada));
        self::assertSame([], $store->staysOf($ada));
        self::assertSame([['bob', [[0, null]]]], self::flat($store->stays(RoomId::fromString('room1'))));
    }

    public function testTheStaysOfAParticipantBeginningTogetherAreOrderedByRoom(): void
    {
        foreach ([['room2', 'room1', 'room3'], ['room1', 'room2', 'room3'], ['room3', 'room2', 'room1']] as $order) {
            $store = $this->store();
            $ada = ParticipantId::fromString('ada');

            foreach ($order as $room) {
                $store->arrive(RoomId::fromString($room), $ada, self::at(0));
            }

            self::assertSame(['room1', 'room2', 'room3'], array_map(static fn (array $stay): string => $stay['room'], $store->staysOf($ada)));
        }
    }

    public function testTwoRecordingsOfDifferentNamesStayTwoWhateverTheirJunction(): void
    {
        $store = $this->store();
        $room = RoomId::fromString('room1');

        self::assertTrue($store->saveRecording(new Recording('a', $room, 'bc')));
        self::assertTrue($store->saveRecording(new Recording('ab', $room, 'c')));
        self::assertTrue($store->saveRecording(new Recording('r1', $room, 'video')));
        self::assertTrue($store->saveRecording(new Recording('r2', $room, 'video')));
        self::assertCount(4, $store->recordings($room));
        self::assertList($store->recordings($room));
    }

    public function testARecordingIsKeptOncePerFormat(): void
    {
        $store = $this->store();
        $room = RoomId::fromString('room1');
        $video = new Recording('rec-1', $room, 'video', 'https://example.com/v', false, self::at(0), self::at(3600), self::at(86_400));

        self::assertTrue($store->saveRecording($video));
        self::assertTrue($store->saveRecording(new Recording('rec-1', $room, 'presentation')));
        self::assertTrue($store->saveRecording(new Recording('rec-1', RoomId::fromString('room2'), 'video')));
        self::assertFalse($store->saveRecording(new Recording('rec-1', $room, 'video', 'https://example.com/w', true, self::at(0))));

        $recordings = $store->recordings($room);
        self::assertCount(2, $recordings);
        $byFormat = [];

        foreach ($recordings as $recording) {
            $byFormat[$recording->format] = $recording;
        }

        self::assertSame('rec-1', $byFormat['video']->id);
        self::assertSame('room1', $byFormat['video']->room->value);
        self::assertSame('https://example.com/w', $byFormat['video']->url);
        self::assertTrue($byFormat['video']->published);
        self::assertSame(self::START, $byFormat['video']->startedAt?->getTimestamp());
        self::assertNull($byFormat['video']->endedAt);
        self::assertNull($byFormat['video']->expiresAt);
        self::assertNull($byFormat['presentation']->url);
        self::assertTrue($byFormat['presentation']->published);
        self::assertSame([], $store->recordings(RoomId::fromString('room3')));
    }

    public function testARecordingKeepsEveryMoment(): void
    {
        $store = $this->store();
        $store->saveRecording(new Recording('rec-1', RoomId::fromString('room1'), 'video', null, false, self::at(0), self::at(3600), self::at(86_400)));

        $recording = $store->recordings(RoomId::fromString('room1'))[0];
        self::assertFalse($recording->published);
        self::assertSame(self::START, $recording->startedAt?->getTimestamp());
        self::assertSame(self::START + 3600, $recording->endedAt?->getTimestamp());
        self::assertSame(self::START + 86_400, $recording->expiresAt?->getTimestamp());
    }

    protected const int START = 1_700_000_000;

    protected static function at(int $offset): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.(self::START + $offset));
    }

    /**
     * @param array<mixed> $values
     */
    protected static function assertList(array $values): void
    {
        self::assertSame([] === $values ? [] : range(0, \count($values) - 1), array_keys($values));
    }

    /**
     * @param list<ParticipantId> $participants
     *
     * @return list<string>
     */
    protected static function names(array $participants): array
    {
        $names = array_map(static fn (ParticipantId $participant): string => $participant->value, $participants);
        sort($names, \SORT_STRING);

        return $names;
    }

    /**
     * @param list<array{participant: ParticipantId, stays: list<Interval>}> $stays
     *
     * @return list<array{string, list<array{int, ?int}>}>
     */
    protected static function flat(array $stays): array
    {
        $flat = array_map(static fn (array $entry): array => [
            $entry['participant']->value,
            array_map(static fn (Interval $interval): array => [$interval->joinedAt->getTimestamp() - self::START, $interval->leftAt instanceof DateTimeImmutable ? $interval->leftAt->getTimestamp() - self::START : null], $entry['stays']),
        ], $stays);
        usort($flat, static fn (array $a, array $b): int => strcmp($a[0], $b[0]));

        return $flat;
    }
}
