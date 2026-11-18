<?php

declare(strict_types=1);

namespace Phil\Slides\Tests\Unit\Infrastructure;

use Override;
use Phil\PagedMedia\Gotenberg\Pipeline;
use Phil\PagedMedia\Gotenberg\Stage\FlattenForm;
use Phil\PagedMediaBundle\Application\Contract\PrintEngine;
use Phil\PagedMediaBundle\Application\Exception\NotConfigured;
use Phil\PagedMediaBundle\Application\PagedMedia;
use Phil\PagedMediaBundle\Application\PrintableHtml;
use Phil\Slides\Application\DeckReader;
use Phil\Slides\Domain\NotesPageSize;
use Phil\Slides\Domain\Slicer;
use Phil\Slides\Domain\ValueObject\Deck;
use Phil\Slides\Domain\ValueObject\DeckSettings;
use Phil\Slides\Infrastructure\Print\DeckPdf;
use Phil\Slides\Infrastructure\Print\DeckPrint;
use Phil\Slides\Tests\Unit\Support\Twig;
use Phil\Ujo\Ujo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeckPrint::class)]
#[CoversClass(DeckPdf::class)]
final class PrintTest extends TestCase
{
    public function testEachSlideIsAPageOfItsShape(): void
    {
        $print = new DeckPrint(Twig::environment());
        $stylesheets = $print->stylesheets($this->deck());

        self::assertCount(2, $stylesheets);
        self::assertSame('@page { size: 960pt 540pt; margin: 0 }', $stylesheets[0]);
        self::assertStringContainsString('.phil-slides-print__page {', $stylesheets[1]);
        self::assertSame('@page { size: 720pt 540pt; margin: 0 }', $print->stylesheets($this->deck("---\nsize: '4:3'\n---\n\n# A"))[0]);
    }

    public function testWithTheNotesEachPageIsUpright(): void
    {
        self::assertSame('@page { size: 612pt 792pt; margin: 36pt }', new DeckPrint(Twig::environment())->stylesheets($this->deck(), true)[0]);
        self::assertSame('@page { size: 595pt 842pt; margin: 36pt }', new DeckPrint(Twig::environment(), NotesPageSize::A4)->stylesheets($this->deck(), true)[0]);
    }

    public function testTheBodyHoldsTheNotesOnlyWhenAsked(): void
    {
        $print = new DeckPrint(Twig::environment());

        self::assertStringNotContainsString('Secret', $print->body($this->deck()));
        self::assertStringContainsString('Secret', $print->body($this->deck(), true));
    }

    public function testTheDocumentIsWhole(): void
    {
        $print = new DeckPrint(Twig::environment());
        $document = $print->document($this->deck("---\ntitle: A </style> & B's\nlang: fr-CA\n---\n\n# A\n\n::: notes\nSecret\n:::"));

        self::assertStringStartsWith('<!doctype html><html lang="fr-CA"><head><meta charset="utf-8"><title>A &lt;/style&gt; &amp; B&#039;s</title><style>@page { size: 960pt 540pt; margin: 0 }</style><style>', $document);
        self::assertStringContainsString('</style></head><body>', $document);
        self::assertStringEndsWith('</div>'."\n".'</body></html>', $document);
        self::assertStringNotContainsString('Secret', $document);
        self::assertStringContainsString('Secret', $print->document($this->deck("# A\n\n::: notes\nSecret\n:::"), true));
        self::assertStringStartsWith('<!doctype html><html lang="en"><head><meta charset="utf-8"><style>@page { size: 612pt 792pt; margin: 36pt }</style>', $print->document($this->deck(), true));
        self::assertSame('en', $print->language($this->deck()));
        self::assertStringStartsWith('<!doctype html><html lang="x&quot;y">', $print->document(new Deck('d', [], new DeckSettings(language: 'x"y'))));
    }

    public function testThePdfGoesThroughThePrintEngine(): void
    {
        $engine = new class implements PrintEngine {
            public ?PrintableHtml $page = null;

            #[Override]
            public function pdf(PrintableHtml $page): string
            {
                $this->page = $page;

                return '%PDF-1.7';
            }
        };
        $print = new DeckPrint(Twig::environment());
        $pdf = new DeckPdf(new PagedMedia(engine: $engine), $print);
        $deck = $this->deck("---\ntitle: Talk\nlang: fr\n---\n\n# A\n\n::: notes\nSecret\n:::");

        $output = $pdf->render($deck);

        self::assertSame('%PDF-1.7', $output->contents);
        self::assertSame('slides.pdf', $output->filename);
        self::assertNotNull($engine->page);
        self::assertSame($print->body($deck), $engine->page->body);
        self::assertSame($print->stylesheets($deck), $engine->page->stylesheets);
        self::assertSame('fr', $engine->page->language);
        self::assertSame('Talk', $engine->page->title);

        $named = $pdf->render($deck, true, 'talk.pdf');
        self::assertSame('talk.pdf', $named->filename);
        self::assertStringContainsString('Secret', $engine->page->body);
        self::assertSame($print->stylesheets($deck, true), $engine->page->stylesheets);
    }

    public function testThePipelineRunsAfterThePrint(): void
    {
        $engine = new class implements PrintEngine {
            #[Override]
            public function pdf(PrintableHtml $page): string
            {
                return '%PDF';
            }
        };

        $this->expectException(NotConfigured::class);

        new DeckPdf(new PagedMedia(engine: $engine), new DeckPrint(Twig::environment()))->render($this->deck(), false, 'a.pdf', new Pipeline(new FlattenForm()));
    }

    private function deck(string $source = "# Title\n\nText\n\n::: notes\nSecret\n:::"): Deck
    {
        return new DeckReader(new Slicer(), new Ujo())->fromMarkup($source);
    }
}
