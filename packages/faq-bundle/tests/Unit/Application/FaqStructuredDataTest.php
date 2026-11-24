<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\FaqBundle\Application\FaqMarkdown;
use Phil\FaqBundle\Application\FaqStructuredData;
use Phil\FaqBundle\Application\View\FaqPage;
use Phil\FaqBundle\Application\View\QuestionView;
use Phil\FaqBundle\Application\View\SectionView;
use Phil\StructuredData\JsonLd;
use Phil\StructuredData\Validation\Issue;
use Phil\StructuredData\Validation\Severity;
use Phil\StructuredData\Validation\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FaqStructuredData::class)]
#[CoversClass(FaqMarkdown::class)]
final class FaqStructuredDataTest extends TestCase
{
    public function testThePageIsAValidFaqPageInItsLanguage(): void
    {
        $graph = new FaqStructuredData()->graph($this->page(), 'https://example.org/faq', 'Questions fréquentes');
        $report = new Validator()->validate($graph);

        // The site the page is part of is the application's to describe.
        self::assertSame(['isPartOf'], array_map(static fn (Issue $issue): ?string => $issue->property, $report->issues));
        self::assertSame(Severity::Warning, $report->issues[0]->severity);
        self::assertSame([
            '@context' => 'https://schema.org',
            '@graph' => [[
                '@type' => 'FAQPage',
                'inLanguage' => 'fr-CA',
                'name' => 'Questions fréquentes',
                'url' => 'https://example.org/faq',
                'mainEntity' => [
                    ['@type' => 'Question', 'name' => 'Comment payer ?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => '<p>Par <strong>carte</strong>.</p>']],
                    ['@type' => 'Question', 'name' => 'Un [crochet] ?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => '<p>Oui</p><p>et non</p>']],
                ],
            ]],
        ], $graph->toArray());
        self::assertStringNotContainsString('</script><', substr(JsonLd::script($graph), 0, -9));
    }

    public function testAPageWithoutQuestionWritesNothing(): void
    {
        $graph = new FaqStructuredData()->graph(new FaqPage('en', [], 'x', 'x', 3));

        self::assertTrue($graph->isEmpty());
        self::assertSame('', JsonLd::script($graph));
    }

    public function testThePageIsMarkdownForALanguageModel(): void
    {
        $markdown = new FaqMarkdown()->export($this->page(), "Questions\nfréquentes", 'https://example.org/faq');

        self::assertSame(<<<'MD'
            # Questions fréquentes

            ## Paiements

            ### [Comment payer ?](https://example.org/faq#comment-payer)

            Par carte.

            ### [Un \[crochet\] ?](https://example.org/faq#crochet)

            Oui et non

            MD, $markdown);
    }

    private function page(): FaqPage
    {
        $at = new DateTimeImmutable('2026-11-21');

        return new FaqPage('fr_CA', [new SectionView('payments', " Paiements\n", [
            new QuestionView('1', 'comment-payer', 'fr_CA', 'Comment payer ?', '<p>Par <strong>carte</strong>.</p>', 'comment payer par carte', $at, 0, 0),
            new QuestionView('2', 'crochet', 'fr', 'Un [crochet] ?', '<p>Oui</p><p>et non</p>', 'un crochet oui et non', $at, 0, 0),
        ])], '', '', 2);
    }
}
