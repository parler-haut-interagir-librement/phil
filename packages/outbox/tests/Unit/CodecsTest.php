<?php

declare(strict_types=1);

namespace Phil\Outbox\Tests\Unit;

use ArrayIterator;
use DateTimeImmutable;
use Phil\Outbox\Codecs;
use Phil\Outbox\Exception\InvalidSetting;
use Phil\Outbox\Exception\UnknownEventType;
use Phil\Outbox\OutboxRecord;
use Phil\Outbox\RecordedEvent;
use Phil\Outbox\RecordedEventCodec;
use Phil\Outbox\RecordId;
use Phil\Outbox\RecordState;
use Phil\Outbox\Tests\Unit\Fixture\CompletionCodec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Codecs::class)]
#[CoversClass(RecordedEventCodec::class)]
#[CoversClass(RecordedEvent::class)]
#[CoversClass(InvalidSetting::class)]
#[CoversClass(UnknownEventType::class)]
final class CodecsTest extends TestCase
{
    public function testTheCodecsAreFoundByTheirTypes(): void
    {
        $completion = new CompletionCodec();
        $recorded = new RecordedEventCodec(['quiz.completed', 'quiz.perfect']);
        $codecs = new Codecs(new ArrayIterator([$completion, $recorded]));

        self::assertSame(['set.completed', 'quiz.completed', 'quiz.perfect'], $codecs->types());
        self::assertTrue($codecs->has('quiz.perfect'));
        self::assertFalse($codecs->has('quiz.missing'));
        self::assertSame($completion, $codecs->get('set.completed'));
        self::assertSame($recorded, $codecs->get('quiz.completed'));
    }

    public function testAnUnknownTypeIsRefused(): void
    {
        $this->expectException(UnknownEventType::class);
        $this->expectExceptionMessageIsOrContains('No codec rebuilds the event type "quiz.missing": register one before recording it.');

        new Codecs([])->get('quiz.missing');
    }

    public function testALongUnknownTypeIsCutInTheMessage(): void
    {
        $this->expectExceptionMessageIsOrContains('No codec rebuilds the event type "'.str_repeat('a', 119).'…"');

        new Codecs([])->get(str_repeat('a', 130));
    }

    public function testTwoCodecsOfOneTypeAreRefused(): void
    {
        $this->expectException(InvalidSetting::class);
        $this->expectExceptionMessageIsOrContains('Two codecs rebuild the event type "set.completed": '.CompletionCodec::class.' and '.RecordedEventCodec::class.'.');

        new Codecs([new CompletionCodec(), new RecordedEventCodec(['set.completed'])]);
    }

    public function testATypeOfAnotherShapeIsRefused(): void
    {
        $this->expectException(InvalidSetting::class);
        $this->expectExceptionMessageIsOrContains('"Quiz" is not an event type: 1 to 120 lowercase letters and digits, in words joined by ".", "_" or "-".');

        new Codecs([new RecordedEventCodec(['quiz.ok', 'Quiz'])]);
    }

    public function testAVeryLongTypeIsCutInTheMessage(): void
    {
        $this->expectExceptionMessageIsOrContains('"'.str_repeat('a', 129).'…" is not an event type');

        new Codecs([new RecordedEventCodec([str_repeat('a', 140)])]);
    }

    public function testTheRecordedEventIsTheRecordAsItIs(): void
    {
        $id = RecordId::generate();
        $at = new DateTimeImmutable('2026-11-17 10:00:00 UTC');
        $record = new OutboxRecord($id, 'quiz.completed', '{"quiz":"q1"}', 'quiz:q1', 'k', $at, $at, RecordState::Pending, 2);
        $payload = $record->payload();

        $event = new RecordedEventCodec(['quiz.completed'])->decode($record, $payload);

        self::assertSame($id, $event->id);
        self::assertSame('quiz.completed', $event->type);
        self::assertSame($payload, $event->payload);
        self::assertSame('quiz:q1', $event->orderingKey);
        self::assertSame($at, $event->occurredAt);
        self::assertSame(3, $event->attempt);
    }
}
