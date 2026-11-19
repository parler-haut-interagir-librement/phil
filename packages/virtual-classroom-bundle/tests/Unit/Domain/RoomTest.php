<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroomBundle\Domain\Exception\ClassroomException;
use Phil\VirtualClassroomBundle\Domain\Exception\InvalidRoom;
use Phil\VirtualClassroomBundle\Domain\Exception\RoomClosed;
use Phil\VirtualClassroomBundle\Domain\Exception\UnknownProvider;
use Phil\VirtualClassroomBundle\Domain\Exception\UnknownRoom;
use Phil\VirtualClassroomBundle\Domain\Model\Room;
use Phil\VirtualClassroomBundle\Domain\Status\RoomStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Room::class)]
#[CoversClass(InvalidRoom::class)]
#[CoversClass(RoomClosed::class)]
#[CoversClass(UnknownProvider::class)]
#[CoversClass(UnknownRoom::class)]
final class RoomTest extends TestCase
{
    public function testARoomIsScheduledThenOpenedThenEnded(): void
    {
        $room = $this->room();

        self::assertSame(RoomStatus::Scheduled, $room->status);
        self::assertFalse($room->hasEnded());
        self::assertTrue($room->open('k1', $this->at(10)));
        self::assertSame(RoomStatus::Open, $room->status);
        self::assertSame('k1', $room->sessionKey);
        self::assertSame(10, $room->openedAt?->getTimestamp());
        self::assertFalse($room->open(null, $this->at(20)), 'An open room opens once.');
        self::assertNull($room->sessionKey, 'The key of the latest session is kept.');
        self::assertSame(10, $room->openedAt->getTimestamp());
        self::assertTrue($room->end($this->at(30)));
        self::assertTrue($room->hasEnded());
        self::assertSame(30, $room->endedAt?->getTimestamp());
        self::assertFalse($room->end($this->at(40)));
        self::assertSame(30, $room->endedAt->getTimestamp());
    }

    public function testAnEndedRoomDoesNotOpenAgain(): void
    {
        $room = $this->room();
        $room->end($this->at(1));

        $this->expectException(InvalidRoom::class);
        $this->expectExceptionMessageIsOrContains('An ended room does not open again.');

        $room->open('k', $this->at(2));
    }

    public function testASessionKeyIsThirtyTwoBytesAtMost(): void
    {
        $room = $this->room();
        self::assertTrue($room->open(str_repeat('k', 32), $this->at(1)));

        $this->expectException(InvalidRoom::class);
        $this->expectExceptionMessageIsOrContains('The session key of a room is at most 32 bytes.');

        $room->open(str_repeat('k', 33), $this->at(2));
    }

    public function testARoomIsRestoredAsItWasKept(): void
    {
        $room = Room::restore(RoomId::fromString('room1'), 'main', 'Algebra', true, 30, 'o', $this->at(0), RoomStatus::Ended, 'k', $this->at(1), $this->at(2));

        self::assertSame(RoomStatus::Ended, $room->status);
        self::assertSame('k', $room->sessionKey);
        self::assertSame(1, $room->openedAt?->getTimestamp());
        self::assertSame(2, $room->endedAt?->getTimestamp());
        self::assertTrue($room->record);
        self::assertSame(30, $room->maxMinutes);
        self::assertSame('o', $room->owner);
    }

    public function testTheBoundsOfARoom(): void
    {
        self::assertSame(str_repeat('a', 64), new Room(RoomId::fromString('room1'), str_repeat('a', 64), 'Algebra', false, null, null, $this->at(0))->provider);
        self::assertSame('a.b_c-1', new Room(RoomId::fromString('room1'), 'a.b_c-1', 'Algebra', false, null, null, $this->at(0))->provider);
        self::assertSame('A'.str_repeat('o', 189), new Room(RoomId::fromString('room1'), 'main', 'Algebra', false, null, 'A'.str_repeat('o', 189), $this->at(0))->owner);
        self::assertSame('course:1/a.b_c-d', new Room(RoomId::fromString('room1'), 'main', 'Algebra', false, null, 'course:1/a.b_c-d', $this->at(0))->owner);
    }

    /**
     * @return iterable<string, array{string, ?string, string, ?int, string}>
     */
    public static function invalid(): iterable
    {
        $provider = 'A provider is named by 1 to 64 lowercase letters, digits, "_", "." or "-".';
        $owner = 'The owner of a room is 1 to 190 letters, digits, "_", ".", ":", "/" or "-".';

        yield 'an empty provider' => ['', null, 'Algebra', null, $provider];
        yield 'an uppercase provider' => ['Main', null, 'Algebra', null, $provider];
        yield 'a provider starting with a dot' => ['.main', null, 'Algebra', null, $provider];
        yield 'a long provider' => [str_repeat('a', 65), null, 'Algebra', null, $provider];
        yield 'a provider and a line' => ["main\n", null, 'Algebra', null, $provider];
        yield 'an empty owner' => ['main', '', 'Algebra', null, $owner];
        yield 'an owner starting with a colon' => ['main', ':a', 'Algebra', null, $owner];
        yield 'an owner with a space' => ['main', 'a b', 'Algebra', null, $owner];
        yield 'a long owner' => ['main', str_repeat('o', 191), 'Algebra', null, $owner];
        yield 'an owner and a line' => ['main', "o\n", 'Algebra', null, $owner];
        yield 'a short title' => ['main', null, 'A', null, 'The title of a room is 2 to 64 characters of valid UTF-8, without control characters.'];
        yield 'a long duration' => ['main', null, 'Algebra', 1441, 'A room runs 1 to 1440 minutes, or as long as the server allows.'];
    }

    #[DataProvider('invalid')]
    public function testAnInvalidRoom(string $provider, ?string $owner, string $title, ?int $minutes, string $message): void
    {
        $this->expectException(InvalidRoom::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Room(RoomId::fromString('room1'), $provider, $title, false, $minutes, $owner, $this->at(0));
    }

    public function testTheExceptionsOfTheBundle(): void
    {
        self::assertSame('No room is named "r".', UnknownRoom::of('r')->getMessage());
        self::assertSame('The room "r" has ended.', RoomClosed::of('r')->getMessage());
        self::assertSame('No provider is named "p".', UnknownProvider::of('p')->getMessage());
        self::assertInstanceOf(ClassroomException::class, InvalidRoom::because('x'));
    }

    private function room(): Room
    {
        return new Room(RoomId::fromString('room1'), 'main', 'Algebra', false, null, null, $this->at(0));
    }

    private function at(int $seconds): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.$seconds);
    }
}
