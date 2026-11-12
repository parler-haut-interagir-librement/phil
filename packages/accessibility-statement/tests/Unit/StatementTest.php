<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement\Tests\Unit;

use DateTimeImmutable;
use Phil\AccessibilityCheck\ConformanceLevel;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityStatement\ConformanceStatus;
use Phil\AccessibilityStatement\ConformanceTarget;
use Phil\AccessibilityStatement\Enforcement;
use Phil\AccessibilityStatement\Evaluation;
use Phil\AccessibilityStatement\EvaluationMethod;
use Phil\AccessibilityStatement\Exception\InvalidStatement;
use Phil\AccessibilityStatement\FeedbackContact;
use Phil\AccessibilityStatement\LocalizedText;
use Phil\AccessibilityStatement\NonConformity;
use Phil\AccessibilityStatement\NonConformityReason;
use Phil\AccessibilityStatement\Standard;
use Phil\AccessibilityStatement\Statement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Statement::class)]
#[CoversClass(InvalidStatement::class)]
final class StatementTest extends TestCase
{
    public function testAStatementListsWhatBlocksAndWhatLiesOutsideItsScope(): void
    {
        $contrast = new NonConformity(SuccessCriterion::ContrastMinimum, description: LocalizedText::of('Grey text.'), occurrences: 3, fromAudit: true);
        $archive = new NonConformity(SuccessCriterion::CaptionsPrerecorded, NonConformityReason::OutOfScope);
        $maps = new NonConformity(SuccessCriterion::Keyboard, NonConformityReason::DisproportionateBurden);
        $statement = new Statement(3, new DateTimeImmutable('2026-11-08T12:00:00+00:00'), new ConformanceTarget(), ConformanceStatus::Partial, new FeedbackContact(email: 'a11y@example.com'), [$contrast, $archive, $maps], evaluation: new Evaluation(new DateTimeImmutable('2026-11-08T12:00:00+00:00')));

        self::assertSame([$contrast, $maps], $statement->blocking());
        self::assertSame([$archive], $statement->withReason(NonConformityReason::OutOfScope));
        self::assertSame([$maps], $statement->withReason(NonConformityReason::DisproportionateBurden));
        self::assertEquals(new DateTimeImmutable('2026-11-08T12:00:00+00:00'), $statement->lastEvaluatedAt());
        self::assertNull($this->full()->lastEvaluatedAt());
    }

    public function testAFullStatementListsOnlyContentOutsideItsScope(): void
    {
        $statement = $this->full([new NonConformity(SuccessCriterion::CaptionsPrerecorded, NonConformityReason::OutOfScope)]);

        self::assertSame([], $statement->blocking());
        self::assertSame(1, $statement->version);
    }

    public function testTheSmallestStatementIsWrittenWithItsRequiredKeysOnly(): void
    {
        self::assertSame([
            'version' => 1,
            'published_at' => '2026-11-08T00:00:00+00:00',
            'standard' => 'wcag-2.2',
            'level' => 'AA',
            'status' => 'full',
            'feedback' => ['email' => 'a11y@example.com'],
        ], $this->full()->toArray());
    }

    public function testEveryPartIsWritten(): void
    {
        $statement = new Statement(
            2,
            new DateTimeImmutable('2026-11-08T10:00:00-05:00'),
            new ConformanceTarget(Standard::Wcag21, ConformanceLevel::A),
            ConformanceStatus::NonConformant,
            new FeedbackContact('a11y@example.com', '+1 514 555 0100', '/contact', LocalizedText::of('1 Main St.'), 5),
            [new NonConformity(SuccessCriterion::NonTextContent, NonConformityReason::DisproportionateBurden, LocalizedText::of('Old maps.'), LocalizedText::of('Call us.'), new DateTimeImmutable('2027-01-31T00:00:00+00:00'), 4, true)],
            [LocalizedText::of('A printed copy.')],
            LocalizedText::of('The site.'),
            new Enforcement(LocalizedText::of('Complain.'), LocalizedText::of('The ombudsman'), 'https://example.org/'),
            new Evaluation(new DateTimeImmutable('2026-11-01T00:00:00+00:00'), EvaluationMethod::Automated, 12, '/report'),
            'Example Org',
        );

        self::assertSame([
            'version' => 2,
            'published_at' => '2026-11-08T10:00:00-05:00',
            'standard' => 'wcag-2.1',
            'level' => 'A',
            'status' => 'non-conformant',
            'entity' => 'Example Org',
            'scope' => ['en' => 'The site.'],
            'feedback' => ['email' => 'a11y@example.com', 'phone' => '+1 514 555 0100', 'url' => '/contact', 'postal_address' => ['en' => '1 Main St.'], 'response_days' => 5],
            'non_conformities' => [[
                'criterion' => '1.1.1',
                'reason' => 'disproportionate-burden',
                'description' => ['en' => 'Old maps.'],
                'alternative' => ['en' => 'Call us.'],
                'fix_by' => '2027-01-31T00:00:00+00:00',
                'occurrences' => 4,
                'from_audit' => true,
            ]],
            'alternatives' => [['en' => 'A printed copy.']],
            'enforcement' => ['procedure' => ['en' => 'Complain.'], 'authority' => ['en' => 'The ombudsman'], 'url' => 'https://example.org/'],
            'evaluation' => ['evaluated_at' => '2026-11-01T00:00:00+00:00', 'method' => 'automated', 'pages' => 12, 'report_url' => '/report'],
        ], $statement->toArray());
    }

    public function testThePartsLeftOutAreNotWritten(): void
    {
        $statement = new Statement(
            1,
            new DateTimeImmutable('2026-11-08T00:00:00+00:00'),
            new ConformanceTarget(),
            ConformanceStatus::Partial,
            new FeedbackContact(phone: '5555'),
            [new NonConformity(SuccessCriterion::ContrastMinimum)],
            enforcement: new Enforcement(LocalizedText::of('Complain.')),
            evaluation: new Evaluation(new DateTimeImmutable('2026-11-01T00:00:00+00:00')),
        );

        self::assertSame([
            'version' => 1,
            'published_at' => '2026-11-08T00:00:00+00:00',
            'standard' => 'wcag-2.2',
            'level' => 'AA',
            'status' => 'partial',
            'feedback' => ['phone' => '5555'],
            'non_conformities' => [['criterion' => '1.4.3', 'reason' => 'non-compliance']],
            'enforcement' => ['procedure' => ['en' => 'Complain.']],
            'evaluation' => ['evaluated_at' => '2026-11-01T00:00:00+00:00', 'method' => 'self-assessment'],
        ], $statement->toArray());
    }

    /**
     * @param array{version?: int, status?: ConformanceStatus, nonConformities?: list<NonConformity>, alternatives?: list<LocalizedText>, evaluation?: Evaluation, entity?: string} $arguments
     */
    #[DataProvider('invalidStatements')]
    public function testAnIncoherentStatementIsRefused(array $arguments, string $reason, string $message): void
    {
        try {
            new Statement(...[
                'version' => 1,
                'publishedAt' => new DateTimeImmutable('2026-11-08'),
                'target' => new ConformanceTarget(),
                'status' => ConformanceStatus::Full,
                'feedback' => new FeedbackContact(email: 'a11y@example.com'),
                ...$arguments,
            ]);
            self::fail('The statement was accepted.');
        } catch (InvalidStatement $invalidStatement) {
            self::assertSame($reason, $invalidStatement->reason);
            self::assertSame($message, $invalidStatement->getMessage());
        }
    }

    /**
     * @return iterable<string, array{array{version?: int, status?: ConformanceStatus, nonConformities?: list<NonConformity>, alternatives?: list<LocalizedText>, evaluation?: Evaluation, entity?: string}, string, string}>
     */
    public static function invalidStatements(): iterable
    {
        $contrast = new NonConformity(SuccessCriterion::ContrastMinimum);

        yield 'version 0' => [['version' => 0], 'version', 'A version is numbered from 1; got 0.'];
        yield 'too many non-conformities' => [['status' => ConformanceStatus::Partial, 'nonConformities' => array_fill(0, Statement::MAX_NON_CONFORMITIES + 1, $contrast)], 'too_many', 'A statement lists at most 1000 non-conformities and 50 alternatives.'];
        yield 'too many alternatives' => [['alternatives' => array_fill(0, Statement::MAX_ALTERNATIVES + 1, LocalizedText::of('x'))], 'too_many', 'A statement lists at most 1000 non-conformities and 50 alternatives.'];
        yield 'a criterion outside the target' => [['status' => ConformanceStatus::Partial, 'nonConformities' => [new NonConformity(SuccessCriterion::ContrastEnhanced)]], 'criterion_outside_target', 'Success criterion 1.4.6 is not part of WCAG 2.2 AA.'];
        yield 'full with a failure' => [['nonConformities' => [$contrast]], 'status', 'A statement that lists a criterion not met does not claim full conformance.'];
        yield 'partial without one' => [['status' => ConformanceStatus::Partial], 'status', 'A statement that lists no criterion not met claims full conformance.'];
        yield 'non-conformant without one' => [['status' => ConformanceStatus::NonConformant, 'nonConformities' => [new NonConformity(SuccessCriterion::Keyboard, NonConformityReason::OutOfScope)]], 'status', 'A statement that lists no criterion not met claims full conformance.'];
        yield 'evaluated after its publication' => [['evaluation' => new Evaluation(new DateTimeImmutable('2026-11-08T00:00:01'))], 'evaluation_date', 'The last evaluation comes before the publication of the statement.'];
        yield 'a blank entity' => [['entity' => '  '], 'entity', 'The organisation is named in 1 to 200 characters.'];
        yield 'too long an entity' => [['entity' => str_repeat('é', Statement::MAX_ENTITY_LENGTH + 1)], 'entity', 'The organisation is named in 1 to 200 characters.'];
    }

    public function testAStatementAtItsLimitsIsKept(): void
    {
        $contrast = new NonConformity(SuccessCriterion::ContrastMinimum);
        $statement = new Statement(
            1,
            new DateTimeImmutable('2026-11-08'),
            new ConformanceTarget(),
            ConformanceStatus::Partial,
            new FeedbackContact(email: 'a11y@example.com'),
            array_fill(0, Statement::MAX_NON_CONFORMITIES, $contrast),
            array_fill(0, Statement::MAX_ALTERNATIVES, LocalizedText::of('x')),
            evaluation: new Evaluation(new DateTimeImmutable('2026-11-08')),
            entity: str_repeat('é', Statement::MAX_ENTITY_LENGTH),
        );

        self::assertCount(Statement::MAX_NON_CONFORMITIES, $statement->nonConformities);
    }

    /**
     * @param list<NonConformity> $nonConformities
     */
    private function full(array $nonConformities = []): Statement
    {
        return new Statement(1, new DateTimeImmutable('2026-11-08T00:00:00+00:00'), new ConformanceTarget(), ConformanceStatus::Full, new FeedbackContact(email: 'a11y@example.com'), $nonConformities);
    }
}
