<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement\Tests\Unit;

use DateTimeImmutable;
use Phil\AccessibilityCheck\Finding;
use Phil\AccessibilityCheck\Location;
use Phil\AccessibilityCheck\Message;
use Phil\AccessibilityCheck\Report;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityStatement\Audit\AuditedPage;
use Phil\AccessibilityStatement\Audit\AuditResult;
use Phil\AccessibilityStatement\ConformanceStatus;
use Phil\AccessibilityStatement\ConformanceTarget;
use Phil\AccessibilityStatement\Enforcement;
use Phil\AccessibilityStatement\EvaluationMethod;
use Phil\AccessibilityStatement\FeedbackContact;
use Phil\AccessibilityStatement\LocalizedText;
use Phil\AccessibilityStatement\NonConformity;
use Phil\AccessibilityStatement\NonConformityReason;
use Phil\AccessibilityStatement\Statement;
use Phil\AccessibilityStatement\StatementGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StatementGenerator::class)]
final class StatementGeneratorTest extends TestCase
{
    public function testTheNextVersionTakesTheFindingsOfTheAudit(): void
    {
        $manual = new NonConformity(SuccessCriterion::CaptionsPrerecorded, NonConformityReason::DisproportionateBurden, LocalizedText::of('Old videos.'));
        $keptByHand = new NonConformity(SuccessCriterion::LinkPurposeInContext, description: LocalizedText::of('Written by hand.'));
        $known = new NonConformity(SuccessCriterion::ContrastMinimum, NonConformityReason::DisproportionateBurden, LocalizedText::of('Faint text.'), LocalizedText::of('Ask for a copy.'), new DateTimeImmutable('2027-01-31'), 9, true);
        $lifted = new NonConformity(SuccessCriterion::HeadingsAndLabels, occurrences: 2, fromAudit: true);
        $current = new Statement(
            3,
            new DateTimeImmutable('2026-10-01'),
            new ConformanceTarget(),
            ConformanceStatus::Partial,
            new FeedbackContact(email: 'a11y@example.com'),
            [$known, $manual, $lifted, $keptByHand],
            [LocalizedText::of('Call us.')],
            LocalizedText::of('The site.'),
            new Enforcement(LocalizedText::of('Complain.')),
            entity: 'Example Org',
        );
        $audit = new AuditResult(new DateTimeImmutable('2026-11-07'), [
            new AuditedPage('/', $this->report([
                [SuccessCriterion::ContrastMinimum, Severity::Error],
                [SuccessCriterion::NonTextContent, Severity::Error],
                [SuccessCriterion::ContrastEnhanced, Severity::Error],
                [SuccessCriterion::LinkPurposeInContext, Severity::Error],
                [SuccessCriterion::HeadingsAndLabels, Severity::Warning],
                [SuccessCriterion::LanguageOfPage, Severity::Error],
            ])),
            new AuditedPage('/about', $this->report([[SuccessCriterion::ContrastMinimum, Severity::Error]])),
        ], EvaluationMethod::Automated, '/audit');

        $next = new StatementGenerator()->generate($current, $audit, new DateTimeImmutable('2026-11-08'));

        self::assertSame(4, $next->version);
        self::assertEquals(new DateTimeImmutable('2026-11-08'), $next->publishedAt);
        self::assertSame(ConformanceStatus::Partial, $next->status);
        self::assertSame($current->target, $next->target);
        self::assertSame($current->feedback, $next->feedback);
        self::assertSame($current->alternatives, $next->alternatives);
        self::assertSame($current->scope, $next->scope);
        self::assertSame($current->enforcement, $next->enforcement);
        self::assertSame('Example Org', $next->entity);
        self::assertCount(5, $next->nonConformities);
        self::assertSame([$manual, $keptByHand], \array_slice($next->nonConformities, 0, 2));

        [, , $images, $contrast, $language] = $next->nonConformities;
        self::assertEquals(new NonConformity(SuccessCriterion::LanguageOfPage, occurrences: 1, fromAudit: true), $language);
        self::assertEquals(new NonConformity(SuccessCriterion::NonTextContent, occurrences: 1, fromAudit: true), $images);
        self::assertEquals(new NonConformity(SuccessCriterion::ContrastMinimum, NonConformityReason::DisproportionateBurden, LocalizedText::of('Faint text.'), LocalizedText::of('Ask for a copy.'), new DateTimeImmutable('2027-01-31'), 2, true), $contrast);

        self::assertNotNull($next->evaluation);
        self::assertEquals(new DateTimeImmutable('2026-11-07'), $next->evaluation->evaluatedAt);
        self::assertSame(EvaluationMethod::Automated, $next->evaluation->method);
        self::assertSame(2, $next->evaluation->pages);
        self::assertSame('/audit', $next->evaluation->reportUrl);
    }

    public function testWarningsCountFromTheMinimumGiven(): void
    {
        $current = new Statement(1, new DateTimeImmutable('2026-10-01'), new ConformanceTarget(), ConformanceStatus::Full, new FeedbackContact(email: 'a11y@example.com'));
        $audit = new AuditResult(new DateTimeImmutable('2026-11-07'), [new AuditedPage('/', $this->report([[SuccessCriterion::HeadingsAndLabels, Severity::Warning], [SuccessCriterion::NonTextContent, Severity::Notice]]))]);

        $strict = new StatementGenerator(Severity::Warning)->generate($current, $audit, new DateTimeImmutable('2026-11-08'), 7);

        self::assertSame(7, $strict->version);
        self::assertSame(ConformanceStatus::Partial, $strict->status);
        self::assertSame([SuccessCriterion::HeadingsAndLabels], array_map(static fn (NonConformity $nonConformity): SuccessCriterion => $nonConformity->criterion, $strict->nonConformities));

        $clean = new StatementGenerator()->generate($current, $audit, new DateTimeImmutable('2026-11-08'));
        self::assertSame(ConformanceStatus::Full, $clean->status);
        self::assertSame([], $clean->nonConformities);
        self::assertSame(2, $clean->version);
    }

    /**
     * @param list<array{SuccessCriterion, Severity}> $findings
     */
    private function report(array $findings): Report
    {
        return new Report(findings: array_map(
            static fn (array $finding): Finding => new Finding('rule', $finding[0], $finding[1], new Message('key'), new Location()),
            $findings,
        ));
    }
}
