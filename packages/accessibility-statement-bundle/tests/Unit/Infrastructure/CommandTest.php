<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\AccessibilityCheck\AccessibilityChecker;
use Phil\AccessibilityStatement\Audit\AuditedPage;
use Phil\AccessibilityStatement\Audit\AuditResult;
use Phil\AccessibilityStatement\Contract\AuditSource;
use Phil\AccessibilityStatement\Exception\InvalidReport;
use Phil\AccessibilityStatement\StatementGenerator;
use Phil\AccessibilityStatement\StatementReader;
use Phil\AccessibilityStatementBundle\Application\StatementRegenerator;
use Phil\AccessibilityStatementBundle\Infrastructure\Console\RegenerateCommand;
use Phil\AccessibilityStatementBundle\Tests\Unit\Fixture\FixedAudits;
use Phil\AccessibilityStatementBundle\Tests\Unit\Fixture\MemoryStatements;
use Phil\AccessibilityStatementBundle\Tests\Unit\Fixture\Statements;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(RegenerateCommand::class)]
final class CommandTest extends TestCase
{
    public function testTheNextVersionIsPublishedAndSummarized(): void
    {
        $statements = new MemoryStatements([StatementReader::fromArray(Statements::complete())]);
        $tester = $this->tester($statements, new FixedAudits($this->audit('<img src="/a.jpg"><p style="color: #aaa">Grey.</p>')));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertStringContainsString('Accessibility statement, version 4', $display);
        self::assertStringContainsString('Target      WCAG 2.2 AA', $display);
        self::assertStringContainsString('Status      partial', $display);
        self::assertStringContainsString('Evaluated   2026-11-07 00:00, 1 pages', $display);
        self::assertMatchesRegularExpression('/Criterion\s+Reason\s+Occurrences\s+Source/', $display);
        self::assertMatchesRegularExpression('/1\.2\.2 Captions \(Prerecorded\)\s+disproportionate-burden\s+-\s+written/', $display);
        self::assertMatchesRegularExpression('/1\.1\.1 Non-text Content\s+non-compliance\s+1\s+audit/', $display);
        self::assertMatchesRegularExpression('/1\.4\.3 Contrast \(Minimum\)\s+non-compliance\s+1\s+audit/', $display);
        self::assertStringContainsString('[OK] Version 4 of the accessibility statement is published.', $display);
        self::assertSame(4, $statements->latest()?->version);
    }

    public function testADryRunPublishesNothing(): void
    {
        $statements = new MemoryStatements([StatementReader::fromArray(Statements::minimal())]);
        $tester = $this->tester($statements, new FixedAudits($this->audit('<p>Fine.</p>')));

        self::assertSame(Command::SUCCESS, $tester->execute(['--dry-run' => true]));
        $display = $tester->getDisplay();
        self::assertStringContainsString('Accessibility statement, version 2', $display);
        self::assertStringContainsString('No success criterion of the target fails.', $display);
        self::assertStringNotContainsString('Criterion', $display);
        self::assertStringNotContainsString('[OK]', $display);
        self::assertStringNotContainsString('[NOTE]', $display);
        self::assertCount(1, $statements->statements);
    }

    public function testAStatementOfTheConfigurationIsShownToBeWrittenThere(): void
    {
        $statements = new MemoryStatements([StatementReader::fromArray(Statements::minimal())], false);
        $tester = $this->tester($statements, new FixedAudits($this->audit('<p>Fine.</p>')));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('[NOTE] The statement comes from the configuration: write this version there,', $tester->getDisplay());
        self::assertStringNotContainsString('[OK]', $tester->getDisplay());

        self::assertSame(Command::SUCCESS, $tester->execute(['--format' => 'json']));
        $data = json_decode($tester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertSame(2, $data['version']);
        self::assertSame('2026-11-08T12:00:00+00:00', $data['published_at']);
        self::assertStringContainsString("{\n    \"version\": 2,", $tester->getDisplay());
    }

    public function testTheJsonKeepsSlashesAndAccents(): void
    {
        $statements = new MemoryStatements([StatementReader::fromArray([...Statements::minimal(), 'entity' => 'Société'])], false);
        $tester = $this->tester($statements, new FixedAudits(new AuditResult(new DateTimeImmutable('2026-11-07'), [], reportUrl: 'https://example.com/a/b')));

        self::assertSame(Command::SUCCESS, $tester->execute(['--format' => 'json']));
        self::assertStringContainsString('"report_url": "https://example.com/a/b"', $tester->getDisplay());
        self::assertStringContainsString('"entity": "Société"', $tester->getDisplay());
    }

    public function testAMissingAuditFailsAndABadReportIsInvalid(): void
    {
        $statements = new MemoryStatements([StatementReader::fromArray(Statements::minimal())]);

        $tester = $this->tester($statements, new FixedAudits());
        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('[WARNING] No audit is there to read', $tester->getDisplay());

        $broken = new class implements AuditSource {
            public function latest(): never
            {
                throw InvalidReport::because('The report is not JSON: Syntax error');
            }
        };
        $tester = $this->tester($statements, $broken);
        self::assertSame(Command::INVALID, $tester->execute([]));
        self::assertStringContainsString('[ERROR] The report is not JSON: Syntax error', $tester->getDisplay());
    }

    public function testAnUnknownFormatIsRefused(): void
    {
        $statements = new MemoryStatements([StatementReader::fromArray(Statements::minimal())]);
        $tester = $this->tester($statements, new FixedAudits($this->audit('<p>Fine.</p>')));

        self::assertSame(Command::INVALID, $tester->execute(['--format' => 'yaml']));
        self::assertStringContainsString('[ERROR] The format is text or json.', $tester->getDisplay());
        self::assertCount(1, $statements->statements);
    }

    private function tester(MemoryStatements $statements, AuditSource $audits): CommandTester
    {
        return new CommandTester(new RegenerateCommand(new StatementRegenerator($statements, $audits, new StatementGenerator(), new MockClock('2026-11-08T12:00:00+00:00')), $statements));
    }

    private function audit(string $html): AuditResult
    {
        return new AuditResult(new DateTimeImmutable('2026-11-07T00:00:00+00:00'), [new AuditedPage('home', new AccessibilityChecker()->checkHtml($html))]);
    }
}
