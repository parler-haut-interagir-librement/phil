<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Tests\Unit;

use Phil\SeoCheck\CheckedPage;
use Phil\SeoCheck\Contract\SeoRule;
use Phil\SeoCheck\Finding;
use Phil\SeoCheck\Message;
use Phil\SeoCheck\Rule\DefaultRules;
use Phil\SeoCheck\Rule\DocumentTitle;
use Phil\SeoCheck\Rule\SingleH1;
use Phil\SeoCheck\SeoChecker;
use Phil\SeoCheck\Severity;
use Phil\SeoCheck\Tests\Support\MemoryLinkStatuses;
use Phil\SeoCheck\Tests\Support\MemoryRenderedPages;
use Phil\SeoCheck\Tests\Support\Pages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Translation\IdentityTranslator;

#[CoversClass(SeoChecker::class)]
final class SeoCheckerTest extends TestCase
{
    public function testACompliantPageHasNothingToCorrect(): void
    {
        $pages = new MemoryRenderedPages(others: [
            Pages::rendered(Pages::html('<link rel="alternate" hreflang="fr" href="https://example.org/fr/news/opening">'), 'https://example.org/en/news/opening'),
        ]);
        $checker = new SeoChecker(DefaultRules::create($pages, new MemoryLinkStatuses(['https://example.org/fr/horaires' => 200])));
        $page = Pages::checked(Pages::html(), others: [Pages::rendered(Pages::html(), 'https://example.org/fr/')]);

        self::assertSame([], $checker->check($page)->findings);
    }

    public function testAFaultyPageListsWhatToCorrectRuleAfterRule(): void
    {
        $html = '<!doctype html><html lang="en"><head><title>Hi</title>'
            .'<link rel="canonical" href="https://example.org/fr/news/other">'
            .'<link rel="alternate" hreflang="fr" href="https://example.org/fr/news/opening"><link rel="alternate" hreflang="en" href="https://example.org/en/news/opening">'
            .'<meta name="robots" content="noindex"></head>'
            .'<body><h1>Un</h1><h1>Deux</h1><a href="javascript:go()">ici</a><a href="https://partner.example.com/gone">Le partenaire</a><img src="/a.png"></body></html>';
        $pages = new MemoryRenderedPages(others: [Pages::rendered(Pages::html(''), 'https://example.org/en/news/opening')]);
        $checker = new SeoChecker(DefaultRules::create($pages, new MemoryLinkStatuses(['https://partner.example.com/gone' => 404])));
        $page = Pages::checked($html, others: [Pages::rendered(Pages::html('<link rel="canonical" href="https://example.org/">'), 'https://example.org/fr/')]);

        $report = $checker->check($page, 'fr');

        self::assertSame([
            'document-title warning document-title.short',
            'meta-description warning meta-description.missing',
            'document-language error document-language.mismatch',
            'single-h1 warning single-h1.multiple',
            'canonical error canonical.mismatch',
            'canonical error canonical.variant-mismatch',
            'indexing error indexing.blocked',
            'hreflang warning hreflang.not-reciprocal',
            'crawlable-links warning crawlable-links.javascript',
            'link-text warning link-text.generic',
            'image-alt warning image-alt.missing',
            'broken-links error broken-links.broken',
        ], array_map(static fn (Finding $finding): string => $finding->rule.' '.$finding->severity->value.' '.$finding->message->key, $report->findings));
        self::assertSame('fr', $report->locale);
        self::assertSame('La page a 2 titres de premier niveau : gardez-en un et faites des autres des h2.', $report->message($report->findings[3]));
    }

    public function testTheDefaultRulesRunWhenNoneAreGiven(): void
    {
        $report = new SeoChecker()->check(Pages::checked(Pages::html('')));

        self::assertSame(['document-title.missing', 'meta-description.missing', 'canonical.missing'], array_map(static fn (Finding $finding): string => $finding->message->key, $report->findings));
        self::assertSame('en', $report->locale);
    }

    public function testARuleThatFailsIsReportedAndTheOthersStillRun(): void
    {
        $failing = new class implements SeoRule {
            public function name(): string
            {
                return 'failing';
            }

            public function check(CheckedPage $page): iterable
            {
                yield new Finding('failing', Severity::Info, new Message('first'));

                throw new RuntimeException('The link checker is down.');
            }
        };
        $after = new class implements SeoRule {
            public function name(): string
            {
                return 'after';
            }

            public function check(CheckedPage $page): iterable
            {
                return [new Finding('after', Severity::Info, new Message('after'))];
            }
        };

        $report = new SeoChecker([$failing, $after], new IdentityTranslator())->check(Pages::checked(Pages::html()));

        self::assertSame(['first', 'check.rule-failed', 'after'], array_map(static fn (Finding $finding): string => $finding->message->key, $report->findings));
        self::assertSame(Severity::Warning, $report->findings[1]->severity);
        self::assertSame('failing', $report->findings[1]->rule);
        self::assertSame(['rule' => 'failing', 'error' => RuntimeException::class], $report->findings[1]->message->parameters);
        self::assertInstanceOf(IdentityTranslator::class, $report->translator);
    }

    public function testRulesComeFromAnyIterable(): void
    {
        $rules = static function (): iterable {
            yield 'a' => new SingleH1();
            yield 'b' => new DocumentTitle();
        };

        $report = new SeoChecker($rules())->check(Pages::checked(Pages::html('', '')));

        self::assertSame(['single-h1', 'document-title'], array_map(static fn (Finding $finding): string => $finding->rule, $report->findings));
    }

    public function testRulesGivenUnderTheSameKeyAllRun(): void
    {
        $rules = (static function (): iterable {
            yield 0 => new DocumentTitle();
            yield 0 => new SingleH1();
        })();
        $page = Pages::checked(Pages::html('', '<p>Nothing</p>'));

        self::assertSame(['document-title', 'single-h1'], array_map(static fn (Finding $finding): string => $finding->rule, new SeoChecker($rules)->check($page)->findings));
    }
}
