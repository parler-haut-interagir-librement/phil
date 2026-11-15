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
use Phil\AccessibilityStatementBundle\Application\Exception\PublicationRefused;
use Phil\AccessibilityStatementBundle\Application\StatementPublisher;
use Phil\AccessibilityStatementBundle\Application\StatementRegenerator;
use Phil\AccessibilityStatementBundle\Tests\Unit\Fixture\FixedAudits;
use Phil\AccessibilityStatementBundle\Tests\Unit\Fixture\MemoryStatements;
use Phil\AccessibilityStatementBundle\Tests\Unit\Fixture\Statements;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(StatementPublisher::class)]
#[CoversClass(PublicationRefused::class)]
final class StatementPublisherTest extends TestCase
{
    public function testTheDraftIsTheVersionTheLatestAuditGives(): void
    {
        $statements = new MemoryStatements([StatementReader::fromArray(Statements::minimal())]);
        $draft = $this->publisher($statements, $this->audit())->draft();

        self::assertIsString($draft);
        $statement = StatementReader::fromJson($draft);
        self::assertSame([2, ConformanceStatus::Partial, '2026-11-08'], [$statement->version, $statement->status, $statement->publishedAt->format('Y-m-d')]);
        self::assertStringContainsString("\n    \"version\": 2,", $draft, 'pretty, for a person to read');
        self::assertStringContainsString('"published_at": "2026-11-08T12:00:00+00:00"', $draft);
        self::assertCount(1, $statements->statements, 'a draft publishes nothing');
    }

    public function testWithoutAnAuditTheDraftIsTheVersionInForceRenumberedAndDatedToday(): void
    {
        $statements = new MemoryStatements([StatementReader::fromArray(Statements::complete())]);

        foreach ([$this->publisher($statements, null), $this->publisher($statements, null, false)] as $publisher) {
            $draft = $publisher->draft();
            self::assertIsString($draft);
            $statement = StatementReader::fromJson($draft);
            self::assertSame([4, '2026-11-08T12:00:00+00:00', ConformanceStatus::Partial, 4], [$statement->version, $statement->publishedAt->format(\DATE_ATOM), $statement->status, \count($statement->nonConformities)]);
            self::assertStringContainsString('"entity": "Example Org"', $draft);
            self::assertStringContainsString('"fr": "le site example.com"', $draft);
            self::assertStringContainsString('Montréal', $draft, 'unicode kept as written');
            self::assertStringContainsString('"url": "/feedback"', $draft, 'slashes kept as written');
        }

        self::assertNull($this->publisher(new MemoryStatements(), $this->audit())->draft());
    }

    public function testTheNextVersionIsPublished(): void
    {
        $statements = new MemoryStatements([StatementReader::fromArray(Statements::minimal())]);
        $published = $this->publisher($statements, null)->publish((string) json_encode(Statements::minimal(2)));

        self::assertSame(2, $published->version);
        self::assertSame($published, $statements->latest());
        self::assertSame(1, $this->publisher(new MemoryStatements(), null)->publish((string) json_encode(Statements::minimal()))->version, 'the first version when none is published');
    }

    public function testADocumentThatIsNotTheNextVersionIsRefusedWithItsReason(): void
    {
        $statements = new MemoryStatements([StatementReader::fromArray(Statements::minimal(2))]);

        foreach ([
            [$statements, (string) json_encode(Statements::minimal(2)), 'The version to publish is 3, not 2.'],
            [$statements, (string) json_encode(Statements::minimal(4)), 'The version to publish is 3, not 4.'],
            [$statements, '{"version": 3', 'The document does not read as a statement: The statement is not JSON: Syntax error'],
            [$statements, (string) json_encode(['version' => 3]), 'The document does not read as a statement: '],
            [new MemoryStatements([StatementReader::fromArray(Statements::minimal())], false), (string) json_encode(Statements::minimal(2)), 'The statement comes from the configuration: a new version is written there.'],
        ] as [$store, $document, $message]) {
            try {
                $this->publisher($store, null)->publish($document);
                self::fail($message);
            } catch (PublicationRefused $publicationRefused) {
                self::assertStringStartsWith($message, $publicationRefused->getMessage());
            }
        }

        self::assertCount(1, $statements->statements);
    }

    public function testTheReasonKeepsWhatCausedIt(): void
    {
        try {
            $this->publisher(new MemoryStatements([], false), null)->publish((string) json_encode(Statements::minimal()));
            self::fail('published');
        } catch (PublicationRefused $publicationRefused) {
            self::assertNotNull($publicationRefused->getPrevious());
        }

        try {
            $this->publisher(new MemoryStatements(), null)->publish('[');
            self::fail('published');
        } catch (PublicationRefused $publicationRefused) {
            self::assertNotNull($publicationRefused->getPrevious());
        }
    }

    private function publisher(MemoryStatements $statements, ?AuditResult $audit, bool $regenerates = true): StatementPublisher
    {
        // Noon in UTC, read on a clock of another time zone: the draft is dated in UTC.
        $clock = new MockClock('2026-11-08T07:00:00-05:00');

        return new StatementPublisher($statements, $clock, $regenerates ? new StatementRegenerator($statements, new FixedAudits($audit), new StatementGenerator(), $clock) : null);
    }

    private function audit(): AuditResult
    {
        return new AuditResult(new DateTimeImmutable('2026-11-07'), [new AuditedPage('home', new AccessibilityChecker()->checkHtml('<img src="/a.jpg">'))]);
    }
}
