<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\ReportingBundle\Domain\Exception\InvalidFigures;
use Phil\ReportingBundle\Domain\Exception\ReportingException;
use Phil\ReportingBundle\Domain\ValueObject\Period;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Period::class)]
#[CoversClass(InvalidFigures::class)]
final class PeriodTest extends TestCase
{
    public function testAPeriodEndsAfterItStarts(): void
    {
        $period = new Period(new DateTimeImmutable('2026-11-01'), new DateTimeImmutable('2026-11-01 00:00:01'));
        self::assertSame('2026-11-01 00:00:01', $period->end->format('Y-m-d H:i:s'));

        foreach (['2026-11-01', '2026-10-31'] as $end) {
            try {
                new Period(new DateTimeImmutable('2026-11-01'), new DateTimeImmutable($end));
                self::fail($end);
            } catch (InvalidFigures $invalidFigures) {
                self::assertInstanceOf(ReportingException::class, $invalidFigures);
                self::assertSame('The figures are invalid: a period ends after it starts.', $invalidFigures->getMessage());
            }
        }
    }

    public function testContains(): void
    {
        $period = new Period(new DateTimeImmutable('2026-11-01'), new DateTimeImmutable('2026-11-02'));

        self::assertTrue($period->contains(new DateTimeImmutable('2026-11-01')));
        self::assertTrue($period->contains(new DateTimeImmutable('2026-11-01 23:59:59')));
        self::assertFalse($period->contains(new DateTimeImmutable('2026-11-02')));
        self::assertFalse($period->contains(new DateTimeImmutable('2026-10-31 23:59:59')));
    }

    public function testSplit(): void
    {
        $period = new Period(new DateTimeImmutable('2026-11-01'), new DateTimeImmutable('2026-11-04 12:00'));

        self::assertSame(
            [['2026-11-01 00:00', '2026-11-02 00:00'], ['2026-11-02 00:00', '2026-11-03 00:00'], ['2026-11-03 00:00', '2026-11-04 00:00'], ['2026-11-04 00:00', '2026-11-04 12:00']],
            array_map(static fn (Period $bucket): array => [$bucket->start->format('Y-m-d H:i'), $bucket->end->format('Y-m-d H:i')], $period->split('1 day')),
        );
        self::assertCount(1, $period->split('1 month'));
        self::assertCount(1, new Period(new DateTimeImmutable('2026-11-01'), new DateTimeImmutable('2026-11-02'))->split('1 day'));
    }

    public function testSplitIsBounded(): void
    {
        $period = new Period(new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-01')->modify('+500 days'));
        self::assertCount(500, $period->split('1 day'));

        $this->expectExceptionObject(InvalidFigures::because('more than 500 buckets'));

        new Period(new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-01')->modify('+501 days'))->split('1 day');
    }

    #[DataProvider('steps')]
    public function testASplitMovesForward(string $step): void
    {
        $this->expectExceptionObject(InvalidFigures::because(\sprintf('"%s" is not a step forward in time', $step)));

        new Period(new DateTimeImmutable('2026-11-01'), new DateTimeImmutable('2026-11-02'))->split($step);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function steps(): iterable
    {
        yield ['0 days'];
        yield ['-1 day'];
        yield ['now and then'];
    }
}
