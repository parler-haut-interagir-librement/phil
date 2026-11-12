<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Tests\Unit;

use Override;
use Phil\AccessibilityCheck\AccessibilityChecker;
use Phil\AccessibilityCheck\Contract\HtmlRule;
use Phil\AccessibilityCheck\Contract\Rule;
use Phil\AccessibilityCheck\Contract\UjoRule;
use Phil\AccessibilityCheck\Exception\InvalidPolicy;
use Phil\AccessibilityCheck\Finding;
use Phil\AccessibilityCheck\Html\HtmlTarget;
use Phil\AccessibilityCheck\Location;
use Phil\AccessibilityCheck\Message;
use Phil\AccessibilityCheck\Policy;
use Phil\AccessibilityCheck\Rule\DefaultRules;
use Phil\AccessibilityCheck\RuleResult;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityCheck\Translation\Messages;
use Phil\AccessibilityCheck\Ujo\PlainFields;
use Phil\AccessibilityCheck\Ujo\UjoTarget;
use Phil\AccessibilityCheck\Violation;
use Phil\Ujo\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccessibilityChecker::class)]
#[CoversClass(DefaultRules::class)]
#[CoversClass(Violation::class)]
final class AccessibilityCheckerTest extends TestCase
{
    public function testTheDefaultRulesRunInTheirOrder(): void
    {
        $ids = array_map(static fn (Rule $rule): string => $rule->id(), new AccessibilityChecker()->rules());

        self::assertSame(['image-alt', 'heading-order', 'heading-h1', 'empty-heading', 'link-name', 'button-name', 'link-text', 'color-contrast', 'table-headers', 'html-lang', 'lang-change', 'frame-title', 'video-captions', 'duplicate-id', 'svg-name'], $ids);
        self::assertEquals(DefaultRules::all(), new AccessibilityChecker()->rules());
    }

    public function testTwoRulesOfOneIdentifierAreRefused(): void
    {
        $this->expectExceptionObject(InvalidPolicy::duplicateRule('probe'));

        new AccessibilityChecker(rules: [$this->probe(), $this->probe()]);
    }

    public function testEachRuleThatReadsTheFormatRunsAndReports(): void
    {
        $checker = new AccessibilityChecker(rules: [$this->probe(), $this->htmlOnly()]);
        $report = $checker->checkHtml('<p>x</p>', 'fr');

        self::assertSame(['probe', 'html-only'], array_map(static fn (RuleResult $result): string => $result->rule, $report->results));
        self::assertSame([2, 0], array_map(static fn (RuleResult $result): int => $result->findings, $report->results));
        self::assertSame(['html fr', 'html fr'], array_map(static fn (Finding $finding): string => $finding->message->key, $report->findings));
        self::assertSame([Severity::Warning, Severity::Notice], array_map(static fn (Finding $finding): Severity => $finding->severity, $report->findings));
        self::assertSame([SuccessCriterion::NonTextContent, SuccessCriterion::ContrastEnhanced], array_map(static fn (Finding $finding): SuccessCriterion => $finding->criterion, $report->findings));
        self::assertSame('probe', $report->findings[0]->rule);
        self::assertSame(7, $report->findings[1]->location->line);

        self::assertSame(['probe'], array_map(static fn (RuleResult $result): string => $result->rule, new AccessibilityChecker(rules: [$this->htmlOnly(), $this->probe()])->checkUjo(new Document())->results));
        self::assertSame(['probe'], array_map(static fn (RuleResult $result): string => $result->rule, new AccessibilityChecker(new Policy(disabledRules: ['html-only']), [$this->htmlOnly(), $this->probe()])->checkHtml('<p>x</p>')->results));

        $ujo = $checker->checkUjo(new Document(), 'de');

        self::assertSame(['probe'], array_map(static fn (RuleResult $result): string => $result->rule, $ujo->results));
        self::assertSame(['ujo de'], array_map(static fn (Finding $finding): string => $finding->message->key, $ujo->findings));
    }

    public function testTheLanguageOfThePolicyStandsForAMissingOne(): void
    {
        $checker = new AccessibilityChecker(new Policy(language: 'it'), [$this->probe()]);

        self::assertSame('html it', $checker->checkHtml('<p>x</p>')->findings[0]->message->key);
        self::assertSame('html pt', $checker->checkHtml('<p>x</p>', 'pt')->findings[0]->message->key);
        self::assertSame('ujo it', $checker->checkUjo(new Document())->findings[0]->message->key);
        self::assertSame('ujo pt', $checker->checkUjo(new Document(), 'pt')->findings[0]->message->key);
    }

    public function testThePolicyTurnsRulesOffAndChangesTheirSeverity(): void
    {
        $checker = new AccessibilityChecker(rules: [$this->probe(), $this->htmlOnly()]);
        $policy = new Policy(locale: 'fr')->withoutRules('html-only')->withSeverity('probe', Severity::Error);
        $report = $checker->withPolicy($policy)->checkHtml('<p>x</p>');

        self::assertSame($policy, $checker->withPolicy($policy)->policy());
        self::assertNotSame($policy, $checker->policy());
        self::assertSame(['probe'], array_map(static fn (RuleResult $result): string => $result->rule, $report->results));
        self::assertSame(Severity::Error, $report->results[0]->severity);
        self::assertSame([Severity::Error, Severity::Error], array_map(static fn (Finding $finding): Severity => $finding->severity, $report->findings));
        self::assertSame('fr', $report->locale);
        self::assertSame(Severity::Notice, $checker->checkHtml('<p>x</p>')->results[0]->severity);
    }

    public function testTheTranslatorAndThePlainFieldsAreTheApplications(): void
    {
        $translator = Messages::translator();
        $translator->addResource('array', ['html en' => 'Probe'], 'en', Messages::DOMAIN);

        $checker = new AccessibilityChecker(new Policy(language: 'en'), [$this->probe()], $translator);
        $report = $checker->checkHtml('<p>x</p>');

        self::assertSame('Probe', $report->message($report->findings[0]));

        $document = Document::fromArray(['blocks' => [['id' => 'c', 'type' => 'code', 'data' => ['code' => '<img src=x>']]]]);

        self::assertSame([], new AccessibilityChecker()->checkUjo($document)->findings);
        self::assertCount(1, new AccessibilityChecker(plainFields: new PlainFields(['code' => []]))->checkUjo($document)->ofRule('image-alt'));
    }

    private function probe(): HtmlRule&UjoRule
    {
        return new class implements HtmlRule, UjoRule {
            #[Override]
            public function id(): string
            {
                return 'probe';
            }

            #[Override]
            public function criterion(): SuccessCriterion
            {
                return SuccessCriterion::NonTextContent;
            }

            #[Override]
            public function severity(): Severity
            {
                return Severity::Notice;
            }

            #[Override]
            public function checkHtml(HtmlTarget $target): iterable
            {
                yield new Violation(new Location(), new Message('html '.$target->language), Severity::Warning);
                yield new Violation(new Location(7), new Message('html '.$target->language), null, SuccessCriterion::ContrastEnhanced);
            }

            #[Override]
            public function checkUjo(UjoTarget $target): iterable
            {
                yield new Violation(new Location(), new Message('ujo '.$target->language));
            }
        };
    }

    private function htmlOnly(): HtmlRule
    {
        return new class implements HtmlRule {
            #[Override]
            public function id(): string
            {
                return 'html-only';
            }

            #[Override]
            public function criterion(): SuccessCriterion
            {
                return SuccessCriterion::NonTextContent;
            }

            #[Override]
            public function severity(): Severity
            {
                return Severity::Error;
            }

            #[Override]
            public function checkHtml(HtmlTarget $target): iterable
            {
                return [];
            }
        };
    }
}
