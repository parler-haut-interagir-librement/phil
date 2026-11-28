<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo\Tests\Unit;

use DateTimeImmutable;
use Phil\ParticipantSafetyInfo\AccessReason;
use Phil\ParticipantSafetyInfo\AccessRecord;
use Phil\ParticipantSafetyInfo\Exception\InvalidSafetyInfo;
use Phil\ParticipantSafetyInfo\SealedSafetyInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccessRecord::class)]
#[CoversClass(SealedSafetyInfo::class)]
final class AccessRecordTest extends TestCase
{
    public function testAResponsibleReadingCarriesNoJustification(): void
    {
        $record = new AccessRecord('event', 'participant', 'reader', AccessReason::Responsible, new DateTimeImmutable('2026-12-01'));

        self::assertNull($record->justification);
        self::assertSame('reader', $record->reader);
    }

    public function testABrokenGlassCarriesItsJustification(): void
    {
        $at = new DateTimeImmutable('2026-12-01');

        self::assertSame(str_repeat('é', 10), new AccessRecord('e', 'p', 'r', AccessReason::BreakGlass, $at, str_repeat('é', 10))->justification);
        self::assertSame(500, mb_strlen((string) new AccessRecord('e', 'p', 'r', AccessReason::BreakGlass, $at, str_repeat('é', 500))->justification));
        self::assertSame("Fainted\nin hall B", new AccessRecord('e', 'p', 'r', AccessReason::BreakGlass, $at, "Fainted\nin hall B")->justification);
    }

    /**
     * @return iterable<string, array{AccessReason, ?string, string}>
     */
    public static function refused(): iterable
    {
        $pair = 'A reading carries a justification when, and only when, it breaks the glass.';
        $bounds = 'The reason for breaking the glass holds 10 to 500 characters.';

        yield 'a responsible reading with a justification' => [AccessReason::Responsible, 'A long enough reason', $pair];
        yield 'a broken glass without one' => [AccessReason::BreakGlass, null, $pair];
        yield 'nine characters' => [AccessReason::BreakGlass, str_repeat('é', 9), $bounds];
        yield 'nine characters and spaces' => [AccessReason::BreakGlass, '   123456789   ', $bounds];
        yield '501 characters' => [AccessReason::BreakGlass, str_repeat('é', 501), $bounds];
        yield 'a null byte' => [AccessReason::BreakGlass, "Emergency\0 now", $bounds];
        yield 'a delete' => [AccessReason::BreakGlass, "Emergency\x7F now", $bounds];
        yield 'not UTF-8' => [AccessReason::BreakGlass, "Emergency \xFF now", $bounds];
    }

    #[DataProvider('refused')]
    public function testAJustificationOutOfBoundsIsRefused(AccessReason $reason, ?string $justification, string $message): void
    {
        $this->expectException(InvalidSafetyInfo::class);
        $this->expectExceptionMessageIsOrContains($message);

        new AccessRecord('e', 'p', 'r', $reason, new DateTimeImmutable('2026-12-01'), $justification);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function identifiers(): iterable
    {
        yield 'event' => ['', 'p', 'r', 'An event'];
        yield 'participant' => ['e', '', 'r', 'A participant'];
        yield 'reader' => ['e', 'p', '', 'A reader'];
    }

    #[DataProvider('identifiers')]
    public function testEachIdentifierIsChecked(string $event, string $participant, string $reader, string $what): void
    {
        $this->expectException(InvalidSafetyInfo::class);
        $this->expectExceptionMessageIsOrContains($what.' is named by');

        new AccessRecord($event, $participant, $reader, AccessReason::Responsible, new DateTimeImmutable('2026-12-01'));
    }

    public function testASealedRecordChecksItsIdentifiers(): void
    {
        $at = new DateTimeImmutable('2026-12-01');

        self::assertSame('p', new SealedSafetyInfo('e', 'p', 'sealed', $at, $at)->participant);

        try {
            new SealedSafetyInfo('', 'p', 'sealed', $at, $at);
            self::fail('An empty event passed.');
        } catch (InvalidSafetyInfo $invalidSafetyInfo) {
            self::assertStringStartsWith('An event is named', $invalidSafetyInfo->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('A participant is named');

        new SealedSafetyInfo('e', '', 'sealed', $at, $at);
    }
}
