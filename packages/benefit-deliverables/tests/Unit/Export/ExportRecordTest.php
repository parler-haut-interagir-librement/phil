<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Tests\Unit\Export;

use DateTimeImmutable;
use Phil\BenefitDeliverables\Exception\InvalidDeliverable;
use Phil\BenefitDeliverables\Export\ExportRecord;
use Phil\BenefitDeliverables\Period;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExportRecord::class)]
final class ExportRecordTest extends TestCase
{
    private const string ID = '0123456789abcdef0123456789abcdef';

    public function testItIsAvailableUntilItExpiresOrItsFileIsErased(): void
    {
        $record = $this->record();

        self::assertTrue($record->isAvailable(new DateTimeImmutable('2026-11-20T10:59:59+00:00')));
        self::assertFalse($record->isAvailable(new DateTimeImmutable('2026-11-20T11:00:00+00:00')));

        $erased = $record->withFileErasedAt(new DateTimeImmutable('2026-11-20T10:30:00+00:00'));
        self::assertFalse($erased->isAvailable(new DateTimeImmutable('2026-11-20T10:31:00+00:00')));
        self::assertEquals(new DateTimeImmutable('2026-11-20T10:30:00+00:00'), $erased->fileErasedAt);
        self::assertSame($erased, $erased->withFileErasedAt(new DateTimeImmutable('2026-11-21')));
        self::assertSame([self::ID, '2026-11', 'postcard', 'staff-1', 3, 1], [$erased->id, $erased->period->name, $erased->benefit, $erased->requestedBy, $erased->rows, $erased->withoutAddress]);
        self::assertEquals($record->expiresAt, $erased->expiresAt);
        self::assertEquals($record->createdAt, $erased->createdAt);
    }

    public function testItsIdentifierIsHexadecimal(): void
    {
        self::assertTrue(ExportRecord::isId(self::ID));

        foreach (['', strtoupper(self::ID), self::ID.'0', substr(self::ID, 1), self::ID."\n", '../'.substr(self::ID, 3)] as $id) {
            self::assertFalse(ExportRecord::isId($id), $id);
        }
    }

    public function testItIsChecked(): void
    {
        $at = new DateTimeImmutable('2026-11-20T10:00:00+00:00');
        $later = $at->modify('+1 hour');
        self::assertSame(0, new ExportRecord(self::ID, Period::of('p'), 'b', str_repeat('s', 255), $at, $later, 0, 0)->rows);

        foreach ([
            'An export is identified' => static fn (): ExportRecord => new ExportRecord('x', Period::of('p'), 'b', 's', $at, $later, 0, 0),
            'Who asked for an export' => static fn (): ExportRecord => new ExportRecord(self::ID, Period::of('p'), 'b', ' ', $at, $later, 0, 0),
            'Who asked for an export ' => static fn (): ExportRecord => new ExportRecord(self::ID, Period::of('p'), 'b', str_repeat('s', 256), $at, $later, 0, 0),
            'An export counts' => static fn (): ExportRecord => new ExportRecord(self::ID, Period::of('p'), 'b', 's', $at, $later, -1, 0),
            'An export counts ' => static fn (): ExportRecord => new ExportRecord(self::ID, Period::of('p'), 'b', 's', $at, $later, 0, -1),
            'An export expires' => static fn (): ExportRecord => new ExportRecord(self::ID, Period::of('p'), 'b', 's', $at, $at, 0, 0),
        ] as $message => $build) {
            try {
                $build();
                self::fail($message);
            } catch (InvalidDeliverable $invalidDeliverable) {
                self::assertTrue(str_starts_with($invalidDeliverable->getMessage(), trim($message)), $invalidDeliverable->getMessage());
            }
        }
    }

    private function record(): ExportRecord
    {
        return new ExportRecord(self::ID, Period::of('2026-11'), 'postcard', 'staff-1', new DateTimeImmutable('2026-11-20T10:00:00+00:00'), new DateTimeImmutable('2026-11-20T11:00:00+00:00'), 3, 1);
    }
}
