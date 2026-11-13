<?php

declare(strict_types=1);

namespace Phil\Epub\Tests\Unit;

use Phil\Epub\Chapter;
use Phil\Epub\Ujo\PageMarker;
use Phil\Epub\Ujo\UjoChapters;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Html\BlockRenderer;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Html\Rendering;
use Phil\Ujo\Normalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UjoChapters::class)]
#[CoversClass(PageMarker::class)]
final class UjoChaptersTest extends TestCase
{
    private function document(Block ...$blocks): Document
    {
        return new Document(array_values($blocks));
    }

    private function header(string $id, string $text, int $level = 1): Block
    {
        return new Block($id, 'header', ['text' => $text, 'level' => $level]);
    }

    private function paragraph(string $id, string $text): Block
    {
        return new Block($id, 'paragraph', ['text' => $text]);
    }

    private function pageBreak(string $id): Block
    {
        return new Block($id, 'pageBreak');
    }

    /**
     * @param list<Chapter> $chapters
     *
     * @return list<string>
     */
    private function html(array $chapters): array
    {
        return array_map(static fn (Chapter $chapter): string => $chapter->html, $chapters);
    }

    public function testADocumentIsSplitAtItsTopHeadingsAndItsPagesNumbered(): void
    {
        $document = $this->document(
            new Block('t', 'toc'),
            $this->header('a', 'One'),
            $this->paragraph('p', 'Hello <b>there</b>'),
            $this->pageBreak('b1'),
            $this->header('c', 'Two'),
            $this->pageBreak('b2'),
            $this->paragraph('q', 'End'),
        );

        self::assertSame([
            '<span id="page-1" role="doc-pagebreak" epub:type="pagebreak" aria-label="1"></span><nav class="ujo-toc"><ol><li class="ujo-toc__level-1"><a href="#a">One</a></li><li class="ujo-toc__level-1"><a href="#c">Two</a></li></ol></nav>'."\n",
            '<h1 id="a" class="ujo-header">One</h1>'."\n".'<p class="ujo-paragraph">Hello <b>there</b></p>'."\n",
            '<span id="page-2" role="doc-pagebreak" epub:type="pagebreak" aria-label="2"></span><h1 id="c" class="ujo-header">Two</h1>'."\n".'<span id="page-3" role="doc-pagebreak" epub:type="pagebreak" aria-label="3"></span>'."\n".'<p class="ujo-paragraph">End</p>',
        ], $this->html(new UjoChapters()->chapters($document)));
    }

    public function testSeveralBreaksBeforeAHeadingAllMove(): void
    {
        $document = $this->document($this->pageBreak('b0'), $this->header('a', 'One'), $this->pageBreak('b1'), $this->pageBreak('b2'), $this->header('c', 'Two'), $this->pageBreak('b3'));

        self::assertSame([
            '<span id="page-1" role="doc-pagebreak" epub:type="pagebreak" aria-label="1"></span><span id="page-2" role="doc-pagebreak" epub:type="pagebreak" aria-label="2"></span><h1 id="a" class="ujo-header">One</h1>'."\n",
            '<span id="page-3" role="doc-pagebreak" epub:type="pagebreak" aria-label="3"></span><span id="page-4" role="doc-pagebreak" epub:type="pagebreak" aria-label="4"></span><h1 id="c" class="ujo-header">Two</h1>'."\n".'<span id="page-5" role="doc-pagebreak" epub:type="pagebreak" aria-label="5"></span>',
        ], $this->html(new UjoChapters()->chapters($document)));
    }

    public function testTheFirstPageAndTheSplitLevelAreChosen(): void
    {
        $document = $this->document($this->header('a', 'One'), $this->header('b', 'Sub', 2), $this->pageBreak('x'), $this->paragraph('p', 'p'));

        self::assertSame([
            '<span id="page-10" role="doc-pagebreak" epub:type="pagebreak" aria-label="10"></span><h1 id="a" class="ujo-header">One</h1>'."\n",
            '<h2 id="b" class="ujo-header">Sub</h2>'."\n".'<span id="page-11" role="doc-pagebreak" epub:type="pagebreak" aria-label="11"></span>'."\n".'<p class="ujo-paragraph">p</p>',
        ], $this->html(new UjoChapters(splitLevel: 2, firstPage: 10)->chapters($document)));
    }

    public function testByDefaultOnlyFirstLevelHeadingsSplit(): void
    {
        self::assertCount(1, new UjoChapters()->chapters($this->document($this->header('a', 'One'), $this->header('b', 'Sub', 2))));
    }

    public function testWithoutPagesBreaksLeaveNothing(): void
    {
        $json = '{"blocks":[{"id":"a","type":"header","data":{"text":"One","level":1}},{"id":"x","type":"pageBreak","data":{}},{"id":"b","type":"header","data":{"text":"Two","level":1}}]}';

        self::assertSame(['<h1 id="a" class="ujo-header">One</h1>'."\n", '<h1 id="b" class="ujo-header">Two</h1>'], $this->html(new UjoChapters(firstPage: null)->chapters($json)));
    }

    public function testTheNormalizerAndRendererCanBeGiven(): void
    {
        $renderer = HtmlRenderer::default()->with('paragraph', new class implements BlockRenderer {
            public function render(Block $block, Rendering $rendering): string
            {
                return '<p class="mine">'.$rendering->inline($block->data['text'] ?? '').'</p>';
            }
        });

        self::assertSame(['<span id="page-1" role="doc-pagebreak" epub:type="pagebreak" aria-label="1"></span><p class="mine">a</p>'], $this->html(new UjoChapters(new Normalizer(), $renderer)->chapters($this->document($this->paragraph('p', 'a')))));
    }
}
