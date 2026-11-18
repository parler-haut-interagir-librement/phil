<?php

declare(strict_types=1);

namespace Phil\Slides\Tests\Benchmark;

use Phil\Slides\Application\DeckReader;
use Phil\Slides\Application\SlideRenderer;
use Phil\Slides\Domain\Slicer;
use Phil\Slides\Domain\ValueObject\Deck;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Ujo;

/**
 * What a page pays for a deck on each request: a document of two hundred slides cut, its notes taken out, a Markdown
 * source of the same size read by phil/markup then cut, and every slide rendered. Each must grow with the size of
 * the document, never with its square.
 */
final readonly class SlidesBench
{
    private Document $document;

    private string $source;

    private Deck $deck;

    private SlideRenderer $renderer;

    public function __construct()
    {
        $blocks = [];
        $source = "---\ntitle: Bench\npaginate: true\n---\n";

        foreach (range(1, 200) as $number) {
            $blocks[] = new Block('h'.$number, 'header', ['text' => 'Slide '.$number, 'level' => 2]);
            $blocks[] = new Block('p'.$number, 'paragraph', ['text' => 'Some <strong>text</strong> on slide '.$number.'.']);
            $blocks[] = new Block('f'.$number, 'container', ['classes' => ['fragment'], 'blocks' => [new Block('fp'.$number, 'paragraph', ['text' => 'A point.'])]]);
            $blocks[] = new Block('n'.$number, 'container', ['classes' => ['notes'], 'blocks' => [new Block('np'.$number, 'paragraph', ['text' => 'What to say.'])]]);
            $source .= "\n## Slide {$number}\n\nSome **text** on slide {$number}.\n\n::: fragment\nA point.\n:::\n\n::: notes\nWhat to say.\n:::\n";
        }

        $this->document = new Document($blocks);
        $this->source = $source;
        $ujo = new Ujo();
        $this->deck = new Slicer()->slice($this->document);
        $this->renderer = new SlideRenderer($ujo);
    }

    public function benchCutADocumentOfTwoHundredSlides(): void
    {
        new Slicer()->slice($this->document);
    }

    public function benchReadAndCutAMarkdownSourceOfTwoHundredSlides(): void
    {
        new DeckReader(new Slicer(), new Ujo())->fromMarkup($this->source);
    }

    public function benchRenderTwoHundredSlidesAndTheirNotes(): void
    {
        foreach ($this->deck->slides as $slide) {
            $this->renderer->content($slide);
            $this->renderer->notes($slide);
        }
    }
}
