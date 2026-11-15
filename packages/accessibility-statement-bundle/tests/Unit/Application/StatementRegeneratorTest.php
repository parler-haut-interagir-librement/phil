<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\AccessibilityCheck\AccessibilityChecker;
use Phil\AccessibilityStatement\Audit\AuditedPage;
use Phil\AccessibilityStatement\Audit\AuditResult;
use Phil\AccessibilityStatement\ConformanceStatus;
use Phil\AccessibilityStatement\StatementGenerator;
use Phil\AccessibilityStatement\StatementReader;
use Phil\AccessibilityStatementBundle\Application\Exception\NothingToRegenerate;
use Phil\AccessibilityStatementBundle\Application\StatementRegenerator;
use Phil\AccessibilityStatementBundle\Domain\Exception\StatementNotStored;
use Phil\AccessibilityStatementBundle\Tests\Unit\Fixture\FixedAudits;
use Phil\AccessibilityStatementBundle\Tests\Unit\Fixture\MemoryStatements;
use Phil\AccessibilityStatementBundle\Tests\Unit\Fixture\Statements;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(StatementRegenerator::class)]
#[CoversClass(NothingToRegenerate::class)]
final class StatementRegeneratorTest extends TestCase
{
    public function testTheNextVersionIsShownThenPublished(): void
    {
        $statements = new MemoryStatements([StatementReader::fromArray(Statements::minimal())]);
        $regenerator = $this->regenerator($statements, $this->audit());

        $preview = $regenerator->preview();
        self::assertSame(2, $preview->version);
        self::assertSame(ConformanceStatus::Partial, $preview->status);
        self::assertEquals(new DateTimeImmutable('2026-11-08T12:00:00+00:00'), $preview->publishedAt);
        self::assertSame('+00:00', $preview->publishedAt->format('P'), 'dated in UTC, whatever the time zone of the clock');
        self::assertCount(1, $statements->statements);

        $published = $regenerator->publish();
        self::assertEquals($preview, $published);
        self::assertSame($published, $statements->latest());
    }

    public function testNothingIsRegeneratedWithoutAStatementOrAnAudit(): void
    {
        foreach ([
            [new MemoryStatements(), $this->audit(), 'No statement is published yet: write the first version in the configuration.'],
            [new MemoryStatements([StatementReader::fromArray(Statements::minimal())]), null, 'No audit is there to read: write the reports of phil/accessibility-check to the audit directory.'],
        ] as [$statements, $audit, $message]) {
            try {
                $this->regenerator($statements, $audit)->publish();
                self::fail('A statement was regenerated.');
            } catch (NothingToRegenerate $nothingToRegenerate) {
                self::assertSame($message, $nothingToRegenerate->getMessage());
            }
        }
    }

    public function testAReadOnlySourceRefusesThePublication(): void
    {
        $this->expectException(StatementNotStored::class);

        $this->regenerator(new MemoryStatements([StatementReader::fromArray(Statements::minimal())], false), $this->audit())->publish();
    }

    private function regenerator(MemoryStatements $statements, ?AuditResult $audit): StatementRegenerator
    {
        return new StatementRegenerator($statements, new FixedAudits($audit), new StatementGenerator(), new MockClock('2026-11-08T07:00:00-05:00'));
    }

    private function audit(): AuditResult
    {
        return new AuditResult(new DateTimeImmutable('2026-11-07'), [new AuditedPage('home', new AccessibilityChecker()->checkHtml('<img src="/a.jpg">'))]);
    }
}
