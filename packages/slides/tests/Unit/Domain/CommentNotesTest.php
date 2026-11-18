<?php

declare(strict_types=1);

namespace Phil\Slides\Tests\Unit\Domain;

use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Slides\Application\DeckReader;
use Phil\Slides\Domain\CommentDirectives;
use Phil\Slides\Domain\Slicer;
use Phil\Slides\Domain\SlideDraft;
use Phil\Slides\Domain\ValueObject\Slide;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Ujo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The `note` tunes a reader leaves where a source had HTML comments: the notes of the slide they close, and the Marp
 * directives of a slide, which are not notes.
 */
#[CoversClass(CommentDirectives::class)]
#[CoversClass(Slicer::class)]
#[CoversClass(SlideDraft::class)]
#[CoversClass(Slide::class)]
final class CommentNotesTest extends TestCase
{
    public function testTheDirectivesOfOneSlideAreReadAndKept(): void
    {
        $directives = CommentDirectives::read("_class: lead  invert\n\n  _paginate: FALSE\r\nclass: 'dark'\npaginate: \"true\"");

        self::assertNotNull($directives);
        self::assertSame(['lead', 'invert'], $directives->spotClasses);
        self::assertSame(['dark'], $directives->carriedClasses);
        self::assertFalse($directives->spotPagination);
        self::assertTrue($directives->carriedPagination);
        self::assertSame([], $directives->ignored);
    }

    public function testTheDirectivesNotKeptAreNoted(): void
    {
        $directives = CommentDirectives::read("theme: gaia\n_backgroundColor: #fff\nbackgroundColor: red\n_header: Hi\nclass: two\tbad!\npaginate: maybe\n_class: ''\ntheme: uncover");

        self::assertNotNull($directives);
        self::assertNull($directives->spotClasses);
        self::assertNull($directives->carriedClasses);
        self::assertNull($directives->spotPagination);
        self::assertNull($directives->carriedPagination);
        self::assertSame(['theme', '_backgroundColor', 'backgroundColor', '_header', 'class', 'paginate', '_class'], $directives->ignored);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notes(): iterable
    {
        yield 'prose' => ['Thank the organisers.'];
        yield 'prose after a directive' => ["_class: lead\nThank the organisers."];
        yield 'a name Marp does not give' => ['Remember: smile'];
        yield 'a deck directive for one slide' => ['_theme: gaia'];
        yield 'blank' => [" \n\t"];
        yield 'empty' => [''];
        yield 'no value separator' => ['class lead'];
        yield 'a name too long' => [str_repeat('a', 33).': b'];
        yield 'too long' => ['class: '.str_repeat('a', CommentDirectives::MAX_BYTES)];
    }

    #[DataProvider('notes')]
    public function testAnyOtherNoteStaysANote(string $note): void
    {
        self::assertNull(CommentDirectives::read($note));
    }

    public function testTheLongestNoteReadAsDirectives(): void
    {
        $note = 'class: lead'.str_repeat(' ', CommentDirectives::MAX_BYTES - 11);

        self::assertSame(['lead'], CommentDirectives::read($note)?->carriedClasses);
        self::assertSame(['a', 'b'], CommentDirectives::read('class: a b a')?->carriedClasses);
        self::assertCount(16, CommentDirectives::read('class: '.implode(' ', range('a', 'p')))->carriedClasses ?? []);
        self::assertNull(CommentDirectives::read('class: '.implode(' ', range('a', 'q')))?->carriedClasses);
        self::assertSame(['class'], CommentDirectives::read('class: x"')?->ignored);
        self::assertSame(['x'], CommentDirectives::read("class: ' x '")?->carriedClasses);
        self::assertNull(CommentDirectives::read('class: "x\'')?->carriedClasses);
        self::assertSame(['class'], CommentDirectives::read('class: "')?->ignored);
    }

    public function testTheNamesNotKeptAreListedOnceInOrder(): void
    {
        self::assertSame(['theme', '_header', 'backgroundColor'], CommentDirectives::read("theme: a\n_header: b\ntheme: c\nbackgroundColor: d")?->ignored);
        self::assertSame(['a', 'b'], CommentDirectives::read("class: a\tb")?->carriedClasses);
        self::assertSame(['lead'], CommentDirectives::read("\t_class:\tlead\t")?->spotClasses);
    }

    public function testTheDirectivesOfSeveralNotesOfOneSlideAddUp(): void
    {
        $deck = new Slicer()->slice(new Document([
            new Block('s1', 'pageStyle', ['classes' => ['lead']]),
            $this->noted($this->paragraph('One'), '_class: lead dark'),
            $this->noted($this->paragraph('Also one'), '_paginate: true'),
            $this->noted($this->paragraph('Still one'), "class: wide\ntheme: gaia"),
            $this->noted($this->paragraph('And one'), 'paginate: false'),
            $this->noted(new Block('d1', 'delimiter'), 'size: 4:3'),
            $this->noted($this->paragraph('Two'), 'theme: uncover'),
        ]), ['theme' => 'default', 'style' => 'x']);

        self::assertSame(['lead', 'dark'], $deck->slides[0]->classes);
        self::assertTrue($deck->slides[0]->pagination);
        self::assertSame(['wide'], $deck->slides[1]->classes);
        self::assertFalse($deck->slides[1]->pagination);
        self::assertSame(['theme', 'style', 'size'], $deck->ignoredDirectives);
    }

    public function testANoteOnASeparatorBelongsToTheSlideItCloses(): void
    {
        $deck = new Slicer()->slice(new Document([
            $this->paragraph('One'),
            $this->noted(new Block('d1', 'delimiter'), 'End of one.'),
            $this->noted($this->paragraph('Two'), 'Start of two.'),
            $this->noted(new Block('d2', 'pageBreak'), 'End of two.'),
            $this->paragraph('Three'),
        ]));

        self::assertSame([['End of one.'], ['Start of two.', 'End of two.'], []], array_map(static fn (Slide $slide): array => $slide->noteTexts, $deck->slides));
    }

    public function testANoteOnAHeadingThatStartsASlideBelongsToTheSlideBefore(): void
    {
        $deck = new Slicer()->slice(new Document([
            $this->noted($this->heading('One'), 'On the first heading.'),
            $this->paragraph('Body'),
            $this->noted($this->heading('Two'), 'End of one.'),
            $this->paragraph('More'),
        ]));

        self::assertSame([['On the first heading.', 'End of one.'], []], array_map(static fn (Slide $slide): array => $slide->noteTexts, $deck->slides));
        self::assertNull($deck->slides[1]->content[0]->tune('note'));
        self::assertSame('h-Two', $deck->slides[1]->content[0]->id);
    }

    public function testTheDirectivesOfANoteShapeTheSlidesInsteadOfBeingNotes(): void
    {
        $deck = new Slicer()->slice(new Document([
            new Block('s1', 'pageStyle', ['classes' => ['wide']]),
            $this->noted($this->paragraph('Cover'), "_class: lead\n_paginate: false"),
            new Block('d1', 'delimiter'),
            $this->noted($this->paragraph('Two'), "class: dark\npaginate: true\ntheme: gaia"),
            new Block('d2', 'delimiter'),
            $this->noted($this->paragraph('Three'), '_class: invert'),
            new Block('d3', 'delimiter'),
            $this->noted($this->paragraph('Four'), "_paginate: false\npaginate: false"),
            new Block('d4', 'delimiter'),
            $this->paragraph('Five'),
        ]));

        self::assertSame([['lead', 'wide'], ['dark'], ['invert'], ['dark'], ['dark']], array_map(static fn (Slide $slide): array => $slide->classes, $deck->slides));
        self::assertSame([false, true, true, false, false], array_map(static fn (Slide $slide): ?bool => $slide->pagination, $deck->slides));
        self::assertSame([[], [], [], [], []], array_map(static fn (Slide $slide): array => $slide->noteTexts, $deck->slides));
        self::assertSame(['theme'], $deck->ignoredDirectives);
    }

    public function testASlideOfDirectivesAloneIsDropped(): void
    {
        $deck = new Slicer()->slice(new Document([
            $this->paragraph('One'),
            new Block('d1', 'delimiter'),
            $this->noted(new Block('d2', 'delimiter'), 'paginate: false'),
            $this->paragraph('Two'),
        ]));

        self::assertSame(2, $deck->count());
        self::assertSame([null, false], array_map(static fn (Slide $slide): ?bool => $slide->pagination, $deck->slides));
    }

    public function testTheNumberOfASlide(): void
    {
        self::assertTrue(new Slide(1, [])->showsNumber(true));
        self::assertFalse(new Slide(1, [])->showsNumber(false));
        self::assertTrue(new Slide(1, [], pagination: true)->showsNumber(false));
        self::assertFalse(new Slide(1, [], pagination: false)->showsNumber(true));
        self::assertFalse(new Slide(1, [], paginated: false, pagination: true)->showsNumber(true));
    }

    public function testTheMarkdownCommentsOfANewerMarkupAreNotesOrDirectives(): void
    {
        $reading = new DefaultCompiler()->reader('markdown')->read("# One\n\nBody\n\n<!-- Thank the organisers. -->\n\n---\n\n<!-- _class: lead -->\n\n## Two\n");

        if (!array_any($reading->document->blocks, static fn (Block $block): bool => null !== $block->tune('note'))) {
            self::markTestSkipped('This version of phil/markup drops the HTML comments of a Markdown source.');
        }

        $deck = new DeckReader(new Slicer(), new Ujo())->fromReading($reading);

        self::assertSame(['Thank the organisers.'], $deck->slides[0]->noteTexts);
        self::assertSame(['lead'], $deck->slides[1]->classes);
        self::assertSame([], $deck->slides[1]->noteTexts);
    }

    private function noted(Block $block, string $note): Block
    {
        return $block->withTunes(['note' => ['text' => $note]]);
    }

    private function heading(string $text): Block
    {
        return new Block('h-'.$text, 'header', ['text' => $text, 'level' => 2]);
    }

    private function paragraph(string $text): Block
    {
        return new Block('p-'.$text, 'paragraph', ['text' => $text]);
    }
}
