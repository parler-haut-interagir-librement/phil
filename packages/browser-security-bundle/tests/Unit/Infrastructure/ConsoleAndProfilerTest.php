<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\BrowserSecurityBundle\Application\HeaderBuilder;
use Phil\BrowserSecurityBundle\Application\PolicyOverride;
use Phil\BrowserSecurityBundle\Application\RequestState;
use Phil\BrowserSecurityBundle\Domain\Enum\InlineKind;
use Phil\BrowserSecurityBundle\Infrastructure\Console\PurgeReportsCommand;
use Phil\BrowserSecurityBundle\Infrastructure\Profiler\CspDataCollector;
use Phil\BrowserSecurityBundle\Tests\Unit\Fixture\MemoryReports;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(PurgeReportsCommand::class)]
#[CoversClass(CspDataCollector::class)]
final class ConsoleAndProfilerTest extends TestCase
{
    public function testThePurgeUsesTheRetentionByDefault(): void
    {
        $reports = new MemoryReports();
        $tester = new CommandTester(new PurgeReportsCommand($reports, new MockClock('2026-11-08 12:00:00'), 30));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertEquals(new DateTimeImmutable('2026-10-09 12:00:00'), $reports->purgedBefore);
        self::assertSame("4 violation(s) removed.\n", $tester->getDisplay());

        self::assertSame(Command::SUCCESS, $tester->execute(['--older-than' => '0']));
        self::assertEquals(new DateTimeImmutable('2026-11-08 12:00:00'), $reports->purgedBefore);
        self::assertSame(Command::SUCCESS, $tester->execute(['--older-than' => '99999']));
    }

    public function testThePurgeRefusesABadNumberOfDays(): void
    {
        $reports = new MemoryReports();
        $tester = new CommandTester(new PurgeReportsCommand($reports, new MockClock(), 30));

        foreach (['-1', '01', '100000', 'ten', "5\n"] as $days) {
            self::assertSame(Command::INVALID, $tester->execute(['--older-than' => $days]), $days);
            self::assertStringContainsString('The number of days is a whole number from 0 to 99999.', $tester->getDisplay());
        }

        self::assertNull($reports->purgedBefore);
        self::assertSame('phil:csp:purge-reports', new PurgeReportsCommand($reports, new MockClock(), 30)->getName());
    }

    public function testTheCollectorReadsTheStateOfTheRequest(): void
    {
        $request = new Request();
        $state = RequestState::of($request);
        $state->nonce(InlineKind::Style);
        $state->nonce(InlineKind::Script);
        $state->addOverride(new PolicyOverride());
        $state->policyName = 'default';
        $state->headers = [HeaderBuilder::ENFORCE => "default-src 'self'", HeaderBuilder::REPORT_ONLY => "default-src 'none'"];

        $collector = new CspDataCollector();
        $collector->collect($request, new Response());

        self::assertSame('phil_browser_security', $collector->getName());
        self::assertSame('default', $collector->policy());
        self::assertSame([HeaderBuilder::ENFORCE => "default-src 'self'", HeaderBuilder::REPORT_ONLY => "default-src 'none'"], $collector->headers());
        self::assertSame(1, $collector->overrides());
        self::assertSame(['style', 'script'], $collector->nonces());

        $collector->reset();
        self::assertNull($collector->policy());
        self::assertSame([], $collector->headers());
        self::assertSame(0, $collector->overrides());
        self::assertSame([], $collector->nonces());
    }

    public function testTheCollectorOfARequestWithoutState(): void
    {
        $collector = new CspDataCollector();
        $collector->collect(new Request(), new Response());

        self::assertNull($collector->policy());
        self::assertSame([], $collector->headers());
        self::assertSame(0, $collector->overrides());
        self::assertSame([], $collector->nonces());
    }
}
