<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\ConsoleBundle\Domain\TimestampFormat;
use Phil\ConsoleBundle\Infrastructure\Console\LineStamper;
use Phil\ConsoleBundle\Infrastructure\Console\StreamStamps;
use Phil\ConsoleBundle\Infrastructure\Console\TimestampFilter;
use Phil\ConsoleBundle\Tests\Unit\Fixture\BufferedConsoleOutput;
use Phil\ConsoleBundle\Tests\Unit\Fixture\MemoryConsoleOutput;
use Phil\ConsoleBundle\Tests\Unit\Fixture\TickingClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

#[CoversClass(LineStamper::class)]
#[CoversClass(StreamStamps::class)]
#[CoversClass(TimestampFilter::class)]
final class TimestampsTest extends TestCase
{
    public function testEachLineGetsTheTimeAtItsStart(): void
    {
        $clock = new MockClock('2026-03-14 09:26:53.589+00:00');
        $stamper = new LineStamper(TimestampFormat::Relative, $clock, $clock->now(), false);

        self::assertSame('', $stamper->stamp(''));
        self::assertSame('[+0.000s] one ', $stamper->stamp('one '));
        $clock->sleep(1.5);
        self::assertSame("part\n", $stamper->stamp("part\n"));
        self::assertSame("[+1.500s] two\n[+1.500s] three\n", $stamper->stamp("two\nthree\n"));
        self::assertSame("[+1.500s] \n[+1.500s] \n", $stamper->stamp("\n\n"));
        self::assertSame('[+1.500s] four', $stamper->stamp('four'));
    }

    public function testOneWriteGetsOneTime(): void
    {
        $clock = new TickingClock();
        $stamper = new LineStamper(TimestampFormat::Relative, $clock, $clock->now(), false);

        self::assertSame("[+1.000s] a\n[+1.000s] b\n", $stamper->stamp("a\nb\n"));
        self::assertSame("[+2.000s] c\n", $stamper->stamp("c\n"));
    }

    public function testTheErrorStreamOfABufferedConsoleOutputIsStamped(): void
    {
        $clock = new MockClock('2026-03-14 09:26:53.589+00:00');
        $stamps = new StreamStamps($clock);
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $output = new BufferedConsoleOutput(new StreamOutput($stream));

        $filters = $stamps->attach($output, TimestampFormat::Relative, $clock->now());
        $output->writeln('out');
        $output->getErrorOutput()->writeln('err');
        $stamps->detach($filters);

        self::assertCount(1, $filters);
        self::assertSame("out\n", $output->fetch());
        rewind($stream);
        self::assertSame("[+0.000s] err\n", stream_get_contents($stream));
    }

    public function testTheTimeIsGreyOnADecoratedOutput(): void
    {
        $clock = new MockClock('2026-03-14 09:26:53.589+00:00');

        self::assertSame("\033[90m[2026-03-14T09:26:53.589+00:00]\033[39m a\n", new LineStamper(TimestampFormat::Iso, $clock, $clock->now(), true)->stamp("a\n"));
    }

    public function testTheStreamsOfAConsoleOutputAreStampedApart(): void
    {
        $clock = new MockClock('2026-03-14 09:26:53.589+00:00');
        $stamps = new StreamStamps($clock);
        $output = new MemoryConsoleOutput();

        $filters = $stamps->attach($output, TimestampFormat::Relative, $clock->now());
        self::assertCount(2, $filters);
        $output->write('out ');
        $output->getErrorOutput()->writeln('err');
        $output->writeln('<info>more</info>');
        $output->writeln('hidden', OutputInterface::VERBOSITY_VERBOSE);

        $stamps->detach($filters);
        $stamps->detach($filters);

        $output->writeln('after');

        self::assertSame("[+0.000s] out more\nafter\n", $output->display());
        self::assertSame("[+0.000s] err\n", $output->errors());
        self::assertContains(TimestampFilter::NAME, stream_get_filters());
        self::assertSame([], $stamps->attach(new BufferedOutput(), TimestampFormat::Iso, $clock->now()));
    }

    public function testAStreamSharedByBothOutputsIsStampedOnce(): void
    {
        $clock = new MockClock('2026-03-14 09:26:53.589+00:00');
        $stamps = new StreamStamps($clock);
        $output = new MemoryConsoleOutput();
        $output->setErrorOutput(new StreamOutput($output->getStream()));

        $filters = $stamps->attach($output, TimestampFormat::Relative, $clock->now());
        $output->writeln('a');
        $output->getErrorOutput()->writeln('b');
        $stamps->detach($filters);

        self::assertCount(1, $filters);
        self::assertSame("[+0.000s] a\n[+0.000s] b\n", $output->display());
    }

    public function testAPlainStreamOutputIsStamped(): void
    {
        $clock = new MockClock('2026-03-14 09:26:53.589+00:00');
        $stamps = new StreamStamps($clock);
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $output = new StreamOutput($stream);

        $stamps->detach($stamps->attach($output, TimestampFormat::Iso, $clock->now()));
        $filters = $stamps->attach($output, TimestampFormat::Iso, $clock->now());
        self::assertSame(2, fwrite($stream, "a\n"));
        $stamps->detach($filters);

        rewind($stream);
        self::assertSame("[2026-03-14T09:26:53.589+00:00] a\n", stream_get_contents($stream));
    }

    public function testAFilterWithoutStamperPassesTheBytes(): void
    {
        new StreamStamps(new MockClock())->attach(new BufferedOutput(), TimestampFormat::Iso, new DateTimeImmutable());
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $filter = stream_filter_append($stream, TimestampFilter::NAME, \STREAM_FILTER_WRITE);
        self::assertIsResource($filter);
        self::assertSame(2, fwrite($stream, "a\n"));
        stream_filter_remove($filter);

        rewind($stream);
        self::assertSame("a\n", stream_get_contents($stream));
    }
}
