<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\ConsoleBundle\Domain\Elapsed;
use Phil\ConsoleBundle\Domain\SummaryMode;
use Phil\ConsoleBundle\Domain\TimestampFormat;
use Phil\ConsoleBundle\Domain\ValueObject\ConsoleSettings;
use Phil\ConsoleBundle\Domain\ValueObject\RunSummary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TimestampFormat::class)]
#[CoversClass(Elapsed::class)]
#[CoversClass(SummaryMode::class)]
#[CoversClass(RunSummary::class)]
#[CoversClass(ConsoleSettings::class)]
final class OutputTest extends TestCase
{
    public function testTheFormats(): void
    {
        $start = new DateTimeImmutable('2026-03-14 09:26:50.500000+00:00');
        $now = new DateTimeImmutable('2026-03-14 09:27:03.012345-05:00');

        self::assertSame('2026-03-14T09:27:03.012-05:00', TimestampFormat::Iso->render($now, $start));
        self::assertSame('+18012.512s', TimestampFormat::Relative->render($now, $start));
        self::assertSame('+0.000s', TimestampFormat::Relative->render($start, $now));
    }

    public function testElapsedSeconds(): void
    {
        self::assertSame(1.75, Elapsed::seconds(new DateTimeImmutable('@10.5'), new DateTimeImmutable('@12.25')));
        self::assertSame(0.25, Elapsed::seconds(new DateTimeImmutable('@10.75'), new DateTimeImmutable('@11.0')));
        self::assertSame(0.0, Elapsed::seconds(new DateTimeImmutable('@12'), new DateTimeImmutable('@10')));
    }

    public function testTheSummaryModes(): void
    {
        self::assertTrue(SummaryMode::Always->shows(false));
        self::assertTrue(SummaryMode::Verbose->shows(true));
        self::assertFalse(SummaryMode::Verbose->shows(false));
        self::assertFalse(SummaryMode::Never->shows(true));
    }

    public function testTheSummary(): void
    {
        self::assertSame('app:import: exit code 0 in 1.235 s, memory +1.5 MiB, peak 12.0 MiB.', new RunSummary('app:import', 1.2346, 1572864, 12582912, 0)->text());
        self::assertSame('a: exit code 3 in 0.000 s, memory -512 B, peak 1023 B.', new RunSummary('a', 0.0, -512, 1023, 3)->text());
        self::assertSame('a: exit code 1 in 2.000 s, memory +1.0 KiB, peak 2.0 GiB.', new RunSummary('a', 2.0, 1024, 2 * 1024 ** 3, 1)->text());
        self::assertSame('a: exit code 1 in 2.000 s, memory +0 B, peak 2048.0 TiB.', new RunSummary('a', 2.0, 0, 2 * 1024 ** 5, 1)->text());
    }

    public function testTheSettings(): void
    {
        $defaults = new ConsoleSettings();
        self::assertFalse($defaults->timestamps);
        self::assertSame(TimestampFormat::Iso, $defaults->format);
        self::assertSame(SummaryMode::Verbose, $defaults->summary);
        self::assertSame([], $defaults->debugChannels);
    }
}
