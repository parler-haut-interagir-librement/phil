<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\Tests\Unit;

use DateTimeImmutable;
use Phil\Url\Url;
use Phil\VirtualClassroom\Attendance\Interval;
use Phil\VirtualClassroom\Capabilities;
use Phil\VirtualClassroom\Event\ParticipantJoined;
use Phil\VirtualClassroom\Event\ParticipantLeft;
use Phil\VirtualClassroom\Event\RecordingReady;
use Phil\VirtualClassroom\Event\RoomEnded;
use Phil\VirtualClassroom\Exception\InvalidResponse;
use Phil\VirtualClassroom\Exception\InvalidValue;
use Phil\VirtualClassroom\Exception\InvalidWebhook;
use Phil\VirtualClassroom\Exception\ProviderRefused;
use Phil\VirtualClassroom\Exception\ProviderUnavailable;
use Phil\VirtualClassroom\Exception\Unsupported;
use Phil\VirtualClassroom\Exception\VirtualClassroomException;
use Phil\VirtualClassroom\Http\HttpBudget;
use Phil\VirtualClassroom\OpenedRoom;
use Phil\VirtualClassroom\Participant;
use Phil\VirtualClassroom\ParticipantId;
use Phil\VirtualClassroom\Presences;
use Phil\VirtualClassroom\Recording;
use Phil\VirtualClassroom\Role;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroom\RoomSpec;
use Phil\VirtualClassroom\RoomState;
use Phil\VirtualClassroom\Webhook;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RoomId::class)]
#[CoversClass(ParticipantId::class)]
#[CoversClass(Participant::class)]
#[CoversClass(RoomSpec::class)]
#[CoversClass(OpenedRoom::class)]
#[CoversClass(Recording::class)]
#[CoversClass(Presences::class)]
#[CoversClass(Capabilities::class)]
#[CoversClass(HttpBudget::class)]
#[CoversClass(Webhook::class)]
#[CoversClass(Interval::class)]
#[CoversClass(ParticipantJoined::class)]
#[CoversClass(ParticipantLeft::class)]
#[CoversClass(RoomEnded::class)]
#[CoversClass(RecordingReady::class)]
#[CoversClass(InvalidValue::class)]
#[CoversClass(InvalidResponse::class)]
#[CoversClass(InvalidWebhook::class)]
#[CoversClass(ProviderRefused::class)]
#[CoversClass(ProviderUnavailable::class)]
#[CoversClass(Unsupported::class)]
final class ValueObjectsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function roomIds(): iterable
    {
        yield 'two characters' => ['a1'];
        yield 'sixty-four characters' => [str_repeat('a', 64)];
        yield 'dashes and underscores' => ['room-1_b'];
        yield 'a digit first' => ['9room'];
    }

    #[DataProvider('roomIds')]
    public function testARoomIdentifier(string $value): void
    {
        $room = RoomId::fromString($value);

        self::assertSame($value, $room->value);
        self::assertTrue($room->equals(RoomId::fromString($value)));
        self::assertFalse($room->equals(RoomId::fromString('other')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notRoomIds(): iterable
    {
        yield 'one character' => ['a'];
        yield 'sixty-five characters' => [str_repeat('a', 65)];
        yield 'an uppercase letter' => ['Room'];
        yield 'a dash first' => ['-room'];
        yield 'an underscore first' => ['_room'];
        yield 'a space' => ['my room'];
        yield 'a line break after' => ["room\n"];
        yield 'a slash' => ['a/b'];
    }

    #[DataProvider('notRoomIds')]
    public function testNotARoomIdentifier(string $value): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A room is 2 to 64 lowercase letters, digits, "-" or "_", starting with a letter or a digit.');

        RoomId::fromString($value);
    }

    public function testADrawnRoomIdentifierIsThirtyTwoHexadecimalDigits(): void
    {
        $room = RoomId::generate();

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $room->value);
        self::assertNotSame($room->value, RoomId::generate()->value);
    }

    public function testAParticipantIdentifier(): void
    {
        $participant = ParticipantId::fromString('user:42.a_b-C');

        self::assertSame('user:42.a_b-C', $participant->value);
        self::assertTrue($participant->equals(ParticipantId::fromString('user:42.a_b-C')));
        self::assertFalse($participant->equals(ParticipantId::fromString('user:43')));
        self::assertSame(str_repeat('x', 190), ParticipantId::fromString(str_repeat('x', 190))->value);
        self::assertSame('7', ParticipantId::tryFrom('7')?->value);
        self::assertNull(ParticipantId::tryFrom(7));
        self::assertNull(ParticipantId::tryFrom('ada@example.com'));
        self::assertNull(ParticipantId::tryFrom(''));
        self::assertNull(ParticipantId::tryFrom(str_repeat('x', 191)));
        self::assertNull(ParticipantId::tryFrom("7\n"));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notParticipants(): iterable
    {
        yield 'empty' => [''];
        yield 'an e-mail address' => ['ada@example.com'];
        yield 'too long' => [str_repeat('x', 191)];
        yield 'a space' => ['a b'];
        yield 'a line break after' => ["a\n"];
    }

    #[DataProvider('notParticipants')]
    public function testNotAParticipantIdentifier(string $value): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A participant is 1 to 190 letters, digits, ".", "_", ":" or "-", never an e-mail address.');

        ParticipantId::fromString($value);
    }

    public function testAParticipantHasATrimmedName(): void
    {
        $participant = new Participant(ParticipantId::fromString('7'), '  Ada Lovelace ');

        self::assertSame('Ada Lovelace', $participant->displayName);
        self::assertSame('7', $participant->id->value);
        self::assertSame('Āb', new Participant(ParticipantId::fromString('7'), 'Āb')->displayName);
        self::assertSame(str_repeat('é', 128), new Participant(ParticipantId::fromString('7'), str_repeat('é', 128))->displayName);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notNames(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'too long' => [str_repeat('é', 129)];
        yield 'a control character' => ["Ada\u{7}"];
        yield 'a line break inside' => ["Ada\nLovelace"];
        yield 'not UTF-8' => ["Ada\xFF"];
    }

    #[DataProvider('notNames')]
    public function testNotAName(string $name): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The name of a participant is 1 to 128 characters of valid UTF-8, without control characters.');

        new Participant(ParticipantId::fromString('7'), $name);
    }

    public function testARoomSpecification(): void
    {
        $ended = Url::fromString('https://example.com/ended');
        $events = Url::fromString('https://example.com/events');
        $spec = new RoomSpec(RoomId::fromString('room1'), '  Algebra  ', true, 90, $ended, $events);

        self::assertSame('Algebra', $spec->title);
        self::assertTrue($spec->record);
        self::assertSame(90, $spec->maxMinutes);
        self::assertSame($ended, $spec->endedUrl);
        self::assertSame($events, $spec->eventsUrl);

        $plain = new RoomSpec(RoomId::fromString('room1'), 'ab');
        self::assertFalse($plain->record);
        self::assertNull($plain->maxMinutes);
        self::assertNull($plain->endedUrl);
        self::assertNull($plain->eventsUrl);
        self::assertSame(1, new RoomSpec(RoomId::fromString('room1'), 'ab', maxMinutes: 1)->maxMinutes);
        self::assertSame(1440, new RoomSpec(RoomId::fromString('room1'), 'ab', maxMinutes: 1440)->maxMinutes);
        self::assertSame('Āb', new RoomSpec(RoomId::fromString('room1'), 'Āb')->title);
        self::assertSame(str_repeat('é', 64), new RoomSpec(RoomId::fromString('room1'), str_repeat('é', 64))->title);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notTitles(): iterable
    {
        yield 'one character' => ['a'];
        yield 'one character padded' => [' a '];
        yield 'sixty-five characters' => [str_repeat('é', 65)];
        yield 'a control character' => ["Al\u{0}gebra"];
        yield 'not UTF-8' => ["Al\xFFgebra"];
    }

    #[DataProvider('notTitles')]
    public function testNotATitle(string $title): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The title of a room is 2 to 64 characters of valid UTF-8, without control characters.');

        new RoomSpec(RoomId::fromString('room1'), $title);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function notDurations(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-5];
        yield 'over a day' => [1441];
    }

    #[DataProvider('notDurations')]
    public function testNotADuration(int $minutes): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A room runs 1 to 1440 minutes, or as long as the server allows.');

        new RoomSpec(RoomId::fromString('room1'), 'Algebra', maxMinutes: $minutes);
    }

    public function testAnOpenedRoom(): void
    {
        $room = new OpenedRoom(RoomId::fromString('room1'), true, '1700000000000');

        self::assertSame('room1', $room->id->value);
        self::assertTrue($room->record);
        self::assertSame('1700000000000', $room->sessionKey);

        $plain = new OpenedRoom(RoomId::fromString('room1'));
        self::assertFalse($plain->record);
        self::assertNull($plain->sessionKey);
    }

    public function testARecording(): void
    {
        $started = new DateTimeImmutable('@1700000000');
        $recording = new Recording('rec-1:a.b_C', RoomId::fromString('room1'), 'video', 'https://example.com/v', false, $started, $started, $started);

        self::assertSame('rec-1:a.b_C', $recording->id);
        self::assertSame('video', $recording->format);
        self::assertSame('https://example.com/v', $recording->url);
        self::assertFalse($recording->published);
        self::assertSame($started, $recording->startedAt);
        self::assertSame($started, $recording->endedAt);
        self::assertSame($started, $recording->expiresAt);

        $plain = new Recording(str_repeat('r', 190), RoomId::fromString('room1'), str_repeat('f', 32));
        self::assertNull($plain->url);
        self::assertTrue($plain->published);
        self::assertNull($plain->startedAt);
        self::assertNull($plain->endedAt);
        self::assertNull($plain->expiresAt);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function notRecordings(): iterable
    {
        $id = 'A recording is named by 1 to 190 letters, digits, ".", "_", ":" or "-".';
        $format = 'The format of a recording is 1 to 32 lowercase letters, digits, "-" or "_".';

        yield 'an empty identifier' => ['', 'video', $id];
        yield 'a long identifier' => [str_repeat('r', 191), 'video', $id];
        yield 'a slash in the identifier' => ['a/b', 'video', $id];
        yield 'a line break after the identifier' => ["a\n", 'video', $id];
        yield 'an empty format' => ['r', '', $format];
        yield 'a long format' => ['r', str_repeat('f', 33), $format];
        yield 'an uppercase format' => ['r', 'Video', $format];
        yield 'a line break after the format' => ['r', "video\n", $format];
    }

    #[DataProvider('notRecordings')]
    public function testNotARecording(string $id, string $format, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Recording($id, RoomId::fromString('room1'), $format);
    }

    public function testPresencesAndCapabilities(): void
    {
        $gone = Presences::gone();
        self::assertSame(RoomState::Gone, $gone->state);
        self::assertSame([], $gone->participants);

        $capabilities = new Capabilities(true, false, true, false);
        self::assertTrue($capabilities->end);
        self::assertFalse($capabilities->presences);
        self::assertTrue($capabilities->recordings);
        self::assertFalse($capabilities->webhooks);
        self::assertSame('moderator', Role::Moderator->value);
        self::assertSame('attendee', Role::Attendee->value);
    }

    public function testABudget(): void
    {
        $budget = new HttpBudget();
        self::assertSame(5.0, $budget->timeout);
        self::assertSame(10.0, $budget->maxDuration);
        self::assertSame(1_048_576, $budget->maxBytes);

        $edge = new HttpBudget(120.0, 120.0, 1024);
        self::assertSame(120.0, $edge->maxDuration);
        self::assertSame(1024, $edge->maxBytes);
        self::assertSame(16_777_216, new HttpBudget(maxBytes: 16_777_216)->maxBytes);
        self::assertSame(0.5, new HttpBudget(0.5, 0.5)->timeout);
    }

    /**
     * @return iterable<string, array{float, float, int, string}>
     */
    public static function notBudgets(): iterable
    {
        $time = 'A call waits more than 0 seconds for a byte, and as long or longer, up to 120 seconds, in all.';
        $size = 'An answer is read up to 1 KiB to 16 MiB.';

        yield 'no silence' => [0.0, 10.0, 1024, $time];
        yield 'less in all than the silence' => [5.0, 4.9, 1024, $time];
        yield 'over two minutes' => [5.0, 120.5, 1024, $time];
        yield 'less than a kibibyte' => [5.0, 10.0, 1023, $size];
        yield 'over sixteen mebibytes' => [5.0, 10.0, 16_777_217, $size];
    }

    #[DataProvider('notBudgets')]
    public function testNotABudget(float $timeout, float $maxDuration, int $maxBytes, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        new HttpBudget($timeout, $maxDuration, $maxBytes);
    }

    public function testAWebhookReadsItsHeadersWithoutCase(): void
    {
        $webhook = new Webhook('POST', ['X-Jaas-Signature' => 't=1'], ['a' => 'b'], '{}', Webhook::ACCOUNT);

        self::assertSame('t=1', $webhook->header('x-jaas-signature'));
        self::assertSame('t=1', $webhook->header('X-JAAS-SIGNATURE'));
        self::assertNull($webhook->header('authorization'));
        self::assertSame(['x-jaas-signature' => 't=1'], $webhook->headers);
        self::assertSame(['a' => 'b'], $webhook->query);
        self::assertSame('{}', $webhook->body);
        self::assertSame('account', $webhook->channel);
        self::assertSame('events', new Webhook('GET')->channel);
        self::assertSame('ended', Webhook::ENDED);
    }

    public function testTheEventsAndTheIntervals(): void
    {
        $room = RoomId::fromString('room1');
        $participant = ParticipantId::fromString('7');
        $at = new DateTimeImmutable('@1700000000');

        $joined = new ParticipantJoined($room, $participant, $at);
        $left = new ParticipantLeft($room, $participant, $at);
        $ended = new RoomEnded($room, $at);
        $ready = new RecordingReady($room, 'r1', $at);

        self::assertSame([$room, $participant, $at], [$joined->room, $joined->participant, $joined->at]);
        self::assertSame([$room, $participant, $at], [$left->room, $left->participant, $left->at]);
        self::assertSame([$room, $at], [$ended->room, $ended->at]);
        self::assertSame(['r1', null], [$ready->recordingId, $ready->recording]);

        $interval = new Interval($at);
        self::assertSame($at, $interval->joinedAt);
        self::assertNull($interval->leftAt);
    }

    public function testEveryExceptionIsOneOfTheLibrary(): void
    {
        foreach ([InvalidValue::because('a'), InvalidResponse::because('a'), InvalidWebhook::because('a'), ProviderRefused::because('a'), ProviderUnavailable::because('a'), Unsupported::because('a')] as $exception) {
            self::assertInstanceOf(VirtualClassroomException::class, $exception);
            self::assertSame('a', $exception->getMessage());
        }
    }
}
