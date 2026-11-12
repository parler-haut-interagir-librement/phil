<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Tests\Unit;

use Phil\AccessibilityCheck\Finding;
use Phil\AccessibilityCheck\Location;
use Phil\AccessibilityCheck\Message;
use Phil\AccessibilityCheck\Report;
use Phil\AccessibilityCheck\RuleResult;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityCheck\Translation\Messages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Report::class)]
#[CoversClass(Finding::class)]
#[CoversClass(RuleResult::class)]
final class ReportTest extends TestCase
{
    private Finding $error;

    private Finding $warning;

    private Finding $notice;

    private Report $report;

    protected function setUp(): void
    {
        $this->error = new Finding('image-alt', SuccessCriterion::NonTextContent, Severity::Error, new Message('image-alt.missing', ['source' => 'a.png']), new Location(2, 'img'));
        $this->warning = new Finding('heading-order', SuccessCriterion::InfoAndRelationships, Severity::Warning, new Message('heading-order.skipped', ['level' => 4, 'previous' => 2]), new Location(5, 'h4'));
        $this->notice = new Finding('image-alt', SuccessCriterion::NonTextContent, Severity::Notice, new Message('lang-change.empty'), new Location());
        $this->report = new Report(
            [new RuleResult('image-alt', SuccessCriterion::NonTextContent, Severity::Error, 2), new RuleResult('heading-order', SuccessCriterion::InfoAndRelationships, Severity::Warning, 1), new RuleResult('link-name', SuccessCriterion::LinkPurposeInContext, Severity::Error, 0)],
            [$this->error, $this->warning, $this->notice],
            Messages::translator(),
            'fr',
        );
    }

    public function testAReportPassesWithoutFindingAtTheSeverity(): void
    {
        self::assertFalse($this->report->passes());
        self::assertFalse($this->report->passes(Severity::Notice));
        self::assertTrue(new Report([], [$this->warning, $this->notice])->passes());
        self::assertFalse(new Report([], [$this->warning, $this->notice])->passes(Severity::Warning));
        self::assertTrue(new Report([], [$this->notice])->passes(Severity::Warning));
        self::assertTrue(new Report()->passes(Severity::Notice));
    }

    public function testTheFindingsAreFilteredBySeverityAndRule(): void
    {
        self::assertSame([$this->error, $this->warning, $this->notice], $this->report->findings());
        self::assertSame([$this->error, $this->warning], $this->report->findings(Severity::Warning));
        self::assertSame([$this->error], $this->report->findings(Severity::Error));
        self::assertSame([$this->warning], $this->report->withSeverity(Severity::Warning));
        self::assertSame([$this->notice], $this->report->withSeverity(Severity::Notice));
        self::assertSame([$this->error, $this->notice], $this->report->ofRule('image-alt'));
        self::assertSame([], $this->report->ofRule('link-name'));
        self::assertCount(3, $this->report);
        self::assertSame([$this->error], new Report([], [$this->notice, $this->error])->findings(Severity::Error));
    }

    public function testTheOutcomeOfEachRuleIsKept(): void
    {
        $result = $this->report->result('heading-order');

        self::assertInstanceOf(RuleResult::class, $result);
        self::assertFalse($result->passed());
        self::assertTrue($this->report->result('link-name')?->passed());
        self::assertNull($this->report->result('svg-name'));
    }

    public function testTheMessagesAreWrittenInTheLocaleOfTheReport(): void
    {
        self::assertSame('Ce titre h4 suit un h2 : un niveau est sauté.', $this->report->message($this->warning));
        self::assertSame('This h4 heading follows an h2: a level is skipped.', $this->report->inLocale('en')->message($this->warning));
        self::assertSame($this->report->findings, $this->report->inLocale('en')->findings);
        self::assertSame($this->report->results, $this->report->inLocale('en')->results);
        self::assertSame('en', new Report()->locale);
        self::assertSame('Ce titre h3 n’a pas de texte.', $this->report->translate(new Message('empty-heading.empty', ['level' => 3])));
    }

    public function testTheTranslatorIsTheApplications(): void
    {
        $translator = Messages::translator();
        $translator->addResource('array', ['lang-change.empty' => 'Vide.'], 'fr', Messages::DOMAIN);

        $report = new Report([], [$this->notice], $translator, 'fr');

        self::assertSame($translator, $report->translator);
        self::assertSame($translator, $report->inLocale('en')->translator);
        self::assertSame('Vide.', $report->message($this->notice));
    }

    public function testAReportIsSerializedToJson(): void
    {
        $expected = [
            'passed' => false,
            'locale' => 'fr',
            'counts' => ['error' => 1, 'warning' => 1, 'notice' => 1],
            'rules' => [
                ['rule' => 'image-alt', 'criterion' => '1.1.1', 'level' => 'A', 'severity' => 'error', 'passed' => false, 'findings' => 2],
                ['rule' => 'heading-order', 'criterion' => '1.3.1', 'level' => 'A', 'severity' => 'warning', 'passed' => false, 'findings' => 1],
                ['rule' => 'link-name', 'criterion' => '2.4.4', 'level' => 'A', 'severity' => 'error', 'passed' => true, 'findings' => 0],
            ],
            'findings' => [
                ['rule' => 'image-alt', 'criterion' => '1.1.1', 'level' => 'A', 'severity' => 'error', 'message' => 'L’image a.png n’a pas d’équivalent textuel : décrivez-la dans un texte alternatif, ou donnez-lui alt="" si elle n’est qu’une décoration.', 'messageKey' => 'image-alt.missing', 'parameters' => ['source' => 'a.png'], 'location' => ['line' => 2, 'element' => 'img']],
                ['rule' => 'heading-order', 'criterion' => '1.3.1', 'level' => 'A', 'severity' => 'warning', 'message' => 'Ce titre h4 suit un h2 : un niveau est sauté.', 'messageKey' => 'heading-order.skipped', 'parameters' => ['level' => 4, 'previous' => 2], 'location' => ['line' => 5, 'element' => 'h4']],
                ['rule' => 'image-alt', 'criterion' => '1.1.1', 'level' => 'A', 'severity' => 'notice', 'message' => 'Ce passage est marqué comme écrit dans une langue inconnue.', 'messageKey' => 'lang-change.empty', 'parameters' => [], 'location' => []],
            ],
        ];

        self::assertSame($expected, $this->report->toArray());
        self::assertSame($expected, $this->report->jsonSerialize());
        self::assertSame(['passed' => true, 'locale' => 'en', 'counts' => ['error' => 0, 'warning' => 0, 'notice' => 0], 'rules' => [], 'findings' => []], new Report()->toArray());
    }
}
