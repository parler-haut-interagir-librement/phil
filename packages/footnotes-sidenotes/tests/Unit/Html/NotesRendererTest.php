<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes\Tests\Unit\Html;

use Phil\FootnotesSidenotes\Exception\InvalidOptions;
use Phil\FootnotesSidenotes\Html\DroppedBlock;
use Phil\FootnotesSidenotes\Html\NotesRegion;
use Phil\FootnotesSidenotes\Html\NotesRenderer;
use Phil\FootnotesSidenotes\Html\NoteWriter;
use Phil\FootnotesSidenotes\Html\ReferenceFilter;
use Phil\FootnotesSidenotes\Note;
use Phil\FootnotesSidenotes\NoteLabels;
use Phil\FootnotesSidenotes\NoteOptions;
use Phil\FootnotesSidenotes\NoteResolver;
use Phil\FootnotesSidenotes\Numbering;
use Phil\FootnotesSidenotes\Placement;
use Phil\FootnotesSidenotes\Resolution;
use Phil\FootnotesSidenotes\Tests\Unit\DocumentFactory as F;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Html\InlineProfile;
use Phil\Ujo\Html\InlineSanitizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(NotesRenderer::class)]
#[CoversClass(NoteWriter::class)]
#[CoversClass(NotesRegion::class)]
#[CoversClass(ReferenceFilter::class)]
#[CoversClass(DroppedBlock::class)]
final class NotesRendererTest extends TestCase
{
    private const string BACK = "\u{21A9}\u{FE0E}";

    private function render(Document $document, Placement $placement = Placement::EndOfDocument, ?NoteResolver $resolver = null, ?NotesRenderer $renderer = null, int $sectionLevel = 2, int $headingLevel = 2): string
    {
        $resolution = ($resolver ?? new NoteResolver())->resolve($document);

        return ($renderer ?? new NotesRenderer(HtmlRenderer::default()))->render($document, $resolution, $placement, 'doc', $sectionLevel, $headingLevel);
    }

    private function simple(): Document
    {
        return F::document([F::paragraph('p', 'Text'.F::reference('a', '<a href="#footnote-a">9</a>').' end'), F::note('n', 'a', 'The <b>note</b>')]);
    }

    public function testTheReferenceAndItsNoteLinkToEachOther(): void
    {
        $expected = '<p class="ujo-paragraph">Text<sup class="isNoteReference"><a id="doc-ref-a-1" href="#doc-note-a" role="doc-noteref" aria-label="Note 1">1</a></sup> end</p>'."\n"
            .'<section id="doc-notes-0" class="isNotes" role="doc-endnotes" aria-labelledby="doc-notes-0-title">'
            .'<h2 id="doc-notes-0-title" class="isNotesTitle">Notes</h2>'
            .'<ol class="isNotesList" role="list"><li id="doc-note-a" class="isNote"><span class="isNoteMarker">1</span> The <b>note</b> '
            .'<a class="isNoteBacklink" href="#doc-ref-a-1" role="doc-backlink" aria-label="Back to reference 1">'.self::BACK.'</a></li></ol></section>';

        self::assertSame($expected, $this->render($this->simple()));
    }

    public function testANoteReferencedSeveralTimesHasOneBacklinkPerReference(): void
    {
        $html = $this->render(F::document([F::paragraph('p', F::reference('a').' and'.F::reference('a')), F::note('n', 'a', 'A')]));

        self::assertSame(1, substr_count($html, 'id="doc-note-a"'));
        self::assertStringContainsString('id="doc-ref-a-1" href="#doc-note-a"', $html);
        self::assertStringContainsString('id="doc-ref-a-2" href="#doc-note-a"', $html);
        self::assertStringContainsString('<a class="isNoteBacklink" href="#doc-ref-a-1" role="doc-backlink" aria-label="Back to reference 1, occurrence 1">'.self::BACK.'<sup>1</sup></a> <a class="isNoteBacklink" href="#doc-ref-a-2" role="doc-backlink" aria-label="Back to reference 1, occurrence 2">'.self::BACK.'<sup>2</sup></a>', $html);
    }

    public function testTheLabelsAreThoseOfTheLocale(): void
    {
        $html = $this->render($this->simple(), renderer: new NotesRenderer(HtmlRenderer::default(), NoteLabels::french()));

        self::assertStringContainsString('aria-label="Retour au renvoi 1"', $html);
    }

    public function testTheDefaultsAreSectionsAtLevelTwoAndTitlesAtLevelTwo(): void
    {
        $document = F::document([
            F::heading('h1', 2),
            F::paragraph('p1', F::reference('a')),
            F::heading('h2', 3),
            F::paragraph('p2', F::reference('b')),
            F::heading('h3', 2),
            F::paragraph('p3', F::reference('c')),
            F::note('na', 'a', 'A'), F::note('nb', 'b', 'B'), F::note('nc', 'c', 'C'),
        ]);
        $html = new NotesRenderer(HtmlRenderer::default())->render($document, new NoteResolver()->resolve($document), Placement::EndOfSection);

        self::assertSame(2, substr_count($html, '<section'));
        self::assertSame(2, substr_count($html, 'class="isNotesTitle"'));
        self::assertMatchesRegularExpression('~<h2 id="[^"]+-notes-0-title"~', $html);
    }

    public function testTheNotesOfTheDocumentComeAfterItsLastBlockInOneRegion(): void
    {
        $html = $this->render(F::document([
            F::heading('h1', 1),
            F::paragraph('p1', F::reference('a')),
            F::heading('h2', 1),
            F::paragraph('p2', F::reference('b')),
            F::note('na', 'a', 'A'), F::note('nb', 'b', 'B'),
        ]));

        self::assertSame(1, substr_count($html, '<section'));
        self::assertLessThan(strpos($html, '<section'), strpos($html, 'id="doc-ref-b-1"'));
    }

    public function testEachSectionEndsWithItsNotes(): void
    {
        $html = $this->render(F::document([
            F::heading('h1', 2, 'One'),
            F::paragraph('p1', F::reference('a')),
            F::heading('h2', 2, 'Two'),
            F::paragraph('p2', 'none'),
            F::heading('h3', 2, 'Three'.F::reference('b')),
            F::paragraph('p3', F::reference('c')),
            F::note('na', 'a', 'A'), F::note('nb', 'b', 'B'), F::note('nc', 'c', 'C'),
        ]), Placement::EndOfSection);

        self::assertSame(2, substr_count($html, '<section'));
        self::assertMatchesRegularExpression('~id="doc-ref-a-1".*id="doc-notes-0".*id="doc-note-a".*>Two<.*>Three.*id="doc-ref-c-1".*id="doc-notes-1".*id="doc-note-b".*id="doc-note-c"~s', $html);
    }

    public function testTheSectionLevelDecidesWhatEndsASection(): void
    {
        $document = F::document([
            F::heading('h1', 2),
            F::paragraph('p1', F::reference('a')),
            F::heading('h2', 3),
            F::paragraph('p2', F::reference('b')),
            F::note('na', 'a', 'A'), F::note('nb', 'b', 'B'),
        ]);

        self::assertSame(1, substr_count($this->render($document, Placement::EndOfSection), '<section'));
        self::assertSame(2, substr_count($this->render($document, Placement::EndOfSection, sectionLevel: 3), '<section'));
    }

    public function testTheHeadingLevelOfARegionIsTheCallers(): void
    {
        self::assertStringContainsString('<h4 id="doc-notes-0-title"', $this->render($this->simple(), headingLevel: 4));
    }

    public function testNoteBlocksAreNotDrawnWhereTheyStand(): void
    {
        $html = $this->render(F::document([
            ['id' => 'wide', 'type' => 'wide', 'data' => ['blocks' => [F::paragraph('p', F::reference('a')), F::note('n', 'a', 'A')]]],
        ]));

        self::assertStringNotContainsString('<aside', $html);
        self::assertStringNotContainsString('ujo-footnote', $html);
    }

    public function testAMissingReferenceIsLeftOutAndANoteWithoutReferenceIsNotWritten(): void
    {
        $html = $this->render(F::document([F::paragraph('p', 'x'.F::reference('ghost', 'G').'y'), F::note('n', 'alone', 'Alone')]));

        self::assertSame('<p class="ujo-paragraph">xy</p>', $html);
    }

    public function testNoRegionWithoutNotes(): void
    {
        self::assertSame('<p class="ujo-paragraph">x</p>', $this->render(F::document([F::paragraph('p', 'x')])));
    }

    public function testTheTextOfANoteIsSanitizedEvenWhenTheDocumentWasNot(): void
    {
        $document = new Document([
            new Block('p', 'paragraph', ['text' => F::reference('a')]),
            new Block('n', 'footnote', ['id' => 'a', 'text' => 'Safe<script>alert(1)</script> <img src=x onerror=y> <a href="javascript:bad()">link</a>']),
        ]);

        foreach (Placement::cases() as $placement) {
            $html = $this->render($document, $placement);

            self::assertStringNotContainsString('<script', $html);
            self::assertStringNotContainsString('alert(', $html);
            self::assertStringNotContainsString('onerror', $html);
            self::assertStringNotContainsString('javascript:', $html);
            self::assertStringContainsString('Safe', $html);
        }
    }

    public function testAProfileGivenByTheCallerGovernsTheNotes(): void
    {
        $sanitizer = new InlineSanitizer()->withProfile('bare', InlineProfile::plain());
        $html = $this->render($this->simple(), renderer: new NotesRenderer(HtmlRenderer::default(), new NoteLabels(), $sanitizer, 'bare'));

        self::assertStringContainsString('</span> The note <a', $html);
    }

    public function testAReferenceInsideANoteIsLeftOutOfTheNote(): void
    {
        $document = F::document([F::paragraph('p', F::reference('a')), F::note('n', 'a', 'A'.F::reference('b', '2').'!'), F::note('m', 'b', 'B')]);

        self::assertStringContainsString('</span> A! <a', $this->render($document));
        self::assertStringContainsString('</span> A!</small>', $this->render($document, Placement::Margin));
        self::assertStringContainsString('>A!</span>', $this->render($document, Placement::PageFoot));
    }

    public function testAReferenceInALinkHasNoLinkOfItsOwn(): void
    {
        $html = $this->render(F::document([F::paragraph('p', '<a href="https://example.org">text'.F::reference('a').'</a> after'.F::reference('a')), F::note('n', 'a', 'A')]));

        self::assertStringContainsString('text<sup id="doc-ref-a-1" class="isNoteReference" aria-label="Note 1">1</sup></a> after<sup class="isNoteReference"><a id="doc-ref-a-2"', $html);
    }

    public function testANoteWrittenInsideALinkLosesItsOwnLinks(): void
    {
        $document = F::document([
            F::paragraph('p', '<a href="https://example.org">text'.F::reference('a').'</a>'),
            F::note('n', 'a', 'See <a href="https://example.net">there</a>'),
        ]);

        foreach ([Placement::Margin, Placement::PageFoot] as $placement) {
            $html = $this->render($document, $placement);

            self::assertStringNotContainsString('example.net', $html);
            self::assertStringContainsString('See there', $html);
        }
    }

    public function testALinkInANoteOutsideALinkIsKept(): void
    {
        $document = F::document([F::paragraph('p', F::reference('a')), F::note('n', 'a', 'See <a href="https://example.net">there</a>')]);

        foreach (Placement::cases() as $placement) {
            self::assertStringContainsString('<a href="https://example.net">there</a>', $this->render($document, $placement));
        }
    }

    public function testAMarginNoteStandsAtItsReference(): void
    {
        $expected = '<div class="hasSidenotes"><p class="ujo-paragraph">Text<span class="isSidenoteAnchor">'
            .'<input type="checkbox" class="isSidenoteSwitch" id="doc-toggle-a" aria-label="Show note 1">'
            .'<label class="isSidenoteToggle" for="doc-toggle-a"><sup class="isNoteReference">1</sup></label>'
            .'<small id="doc-note-a" class="isSidenote" role="note"><span class="isNoteMarker">1</span> The <b>note</b></small>'
            .'</span> end</p></div>';

        self::assertSame($expected, $this->render($this->simple(), Placement::Margin));
    }

    public function testAMarginNoteIsWrittenOnceAndLaterReferencesLinkToIt(): void
    {
        $html = $this->render(F::document([F::paragraph('p', F::reference('a').F::reference('a')), F::note('n', 'a', 'A')]), Placement::Margin);

        self::assertSame(1, substr_count($html, 'class="isSidenote"'));
        self::assertStringContainsString('<a id="doc-ref-a-2" href="#doc-note-a" role="doc-noteref"', $html);
        self::assertStringNotContainsString('<section', $html);
    }

    public function testAMarginNoteHoldsPhrasingContentOnly(): void
    {
        $html = $this->render($this->simple(), Placement::Margin);

        foreach (['<div><', '<aside', '<details', '<ol', '<li'] as $block) {
            self::assertStringNotContainsString($block, $html);
        }
    }

    public function testNoMarginWrapperAroundOtherPlacements(): void
    {
        foreach ([Placement::EndOfDocument, Placement::EndOfSection, Placement::PageFoot] as $placement) {
            self::assertStringNotContainsString('hasSidenotes', $this->render($this->simple(), $placement));
        }
    }

    public function testAPageNoteReplacesItsReference(): void
    {
        $expected = '<p class="ujo-paragraph">Text<span id="doc-note-a" class="isPageNote" data-note-label="iv">The <b>note</b></span> end</p>';
        $document = F::document([
            F::paragraph('p', 'Text'.F::reference('a').' end'),
            F::note('n', 'a', 'The <b>note</b>'),
        ]);
        $renderer = new NotesRenderer(HtmlRenderer::default());
        $resolution = new NoteResolver(new NoteOptions(numbering: Numbering::lowerRoman()))->resolve($document);
        $note = $resolution->notes[0];
        $relabelled = new Resolution([new Note($note->id, $note->html, $note->blockId, 4, 'iv', 0, $note->references)]);

        self::assertSame($expected, $renderer->render($document, $relabelled, Placement::PageFoot, 'doc'));
    }

    public function testNewLinesInTheSourceOfANoteBecomeSingleSpaces(): void
    {
        $source = "One\ntwo\r\n\t  three <b>four</b>\n\nfive\u{A0}: six\n";
        $document = F::document([F::paragraph('p', F::reference('a')), F::note('n', 'a', $source)]);

        self::assertStringContainsString(">One two three <b>four</b> five\u{A0}: six</span>", $this->render($document, Placement::PageFoot));
        self::assertStringContainsString("</span> One two three <b>four</b> five\u{A0}: six</small>", $this->render($document, Placement::Margin));
        self::assertStringContainsString("</span> One two three <b>four</b> five\u{A0}: six <a", $this->render($document));
    }

    public function testADocumentsPrefixDependsOnItsBlocks(): void
    {
        $one = F::document([F::paragraph('p1', F::reference('a')), F::note('n1', 'a', 'A')]);
        $two = F::document([F::paragraph('p2', F::reference('a')), F::note('n2', 'a', 'A')]);
        $renderer = new NotesRenderer(HtmlRenderer::default());
        $first = $renderer->render($one, new NoteResolver()->resolve($one));
        $second = $renderer->render($two, new NoteResolver()->resolve($two));

        self::assertSame(1, preg_match('/href="#(n[0-9a-f]{8})-note-a"/', $first, $a));
        self::assertSame(1, preg_match('/href="#(n[0-9a-f]{8})-note-a"/', $second, $b));
        self::assertNotSame($a[1], $b[1]);
        self::assertSame($first, $renderer->render($one, new NoteResolver()->resolve($one)));
        self::assertStringContainsString('id="'.$a[1].'-note-a"', $first);
    }

    #[TestWith(['1a'])]
    #[TestWith(['with space'])]
    #[TestWith(["trailing\n"])]
    #[TestWith([''])]
    #[TestWith(['a23456789012345678901234567890123'])]
    public function testAMalformedPrefixIsRefused(string $prefix): void
    {
        $this->expectException(InvalidOptions::class);
        $this->expectExceptionMessageIsOrContains('the prefix of identifiers');

        new NotesRenderer(HtmlRenderer::default())->render(new Document(), new Resolution(), Placement::EndOfDocument, $prefix);
    }

    public function testAPrefixMayFillItsThirtyTwoCharacters(): void
    {
        self::assertSame('', new NotesRenderer(HtmlRenderer::default())->render(new Document(), new Resolution(), Placement::EndOfDocument, 'a2345678901234567890123456789_-Z'));
    }

    #[TestWith([0, 2])]
    #[TestWith([7, 2])]
    #[TestWith([2, 0])]
    #[TestWith([2, 7])]
    public function testALevelOutOfRangeIsRefused(int $section, int $heading): void
    {
        $this->expectException(InvalidOptions::class);
        $this->expectExceptionMessageIsOrContains('a heading level goes from 1 to 6');

        new NotesRenderer(HtmlRenderer::default())->render(new Document(), new Resolution(), Placement::EndOfDocument, 'a', $section, $heading);
    }

    #[TestWith([1, 1])]
    #[TestWith([6, 6])]
    public function testTheEndsOfTheRangeAreAccepted(int $section, int $heading): void
    {
        self::assertSame('', new NotesRenderer(HtmlRenderer::default())->render(new Document(), new Resolution(), Placement::EndOfDocument, 'a', $section, $heading));
    }

    public function testASymbolLabelIsEscaped(): void
    {
        $document = F::document([F::paragraph('p', F::reference('a')), F::note('n', 'a', 'A')]);
        $resolver = new NoteResolver(new NoteOptions(numbering: Numbering::symbols(['<&>"'])));

        foreach (Placement::cases() as $placement) {
            $html = $this->render($document, $placement, $resolver);

            self::assertStringContainsString('&lt;&amp;&gt;&quot;', $html);
            self::assertStringNotContainsString('<&>', $html);
        }
    }

    public function testLiteralReferencesInCodeAreLeftAlone(): void
    {
        $html = $this->render(F::document([['id' => 'c', 'type' => 'code', 'data' => ['code' => F::reference('a')]]]));

        self::assertStringContainsString('&lt;sup data-footnote=&quot;a&quot;&gt;', $html);
    }

    public function testATableOfContentsStillSeesTheWholeDocument(): void
    {
        $html = $this->render(F::document([
            ['id' => 'toc', 'type' => 'toc', 'data' => []],
            F::heading('h1', 2, 'First title'),
            F::paragraph('p', F::reference('a')),
            F::heading('h2', 2, 'Second title'),
            F::note('n', 'a', 'A'),
        ]), Placement::EndOfSection);

        self::assertSame(2, substr_count($html, 'First title'));
        self::assertSame(2, substr_count($html, 'Second title'));
    }
}
