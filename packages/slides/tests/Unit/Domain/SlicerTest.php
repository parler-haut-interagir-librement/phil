<?php

declare(strict_types=1);

namespace Phil\Slides\Tests\Unit\Domain;

use Phil\Slides\Domain\AspectRatio;
use Phil\Slides\Domain\Exception\DeckRefused;
use Phil\Slides\Domain\Exception\InvalidOption;
use Phil\Slides\Domain\Slicer;
use Phil\Slides\Domain\SlideDraft;
use Phil\Slides\Domain\SplitMode;
use Phil\Slides\Domain\ValueObject\Slide;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Slicer::class)]
#[CoversClass(SlideDraft::class)]
final class SlicerTest extends TestCase
{
    public function testSeparatorsEndSlidesAndAreNotShown(): void
    {
        $deck = new Slicer()->slice($this->document(
            $this->heading('One', 1),
            $this->paragraph('First'),
            new Block('d1', 'delimiter'),
            $this->paragraph('Second'),
            new Block('p1', 'pageBreak'),
            $this->paragraph('Third'),
        ));

        self::assertSame(3, $deck->count());
        self::assertSame([['h-One', 'p-First'], ['p-Second'], ['p-Third']], $this->ids($deck->slides));
        self::assertSame([1, 2, 3], array_map(static fn (Slide $slide): int => $slide->number, $deck->slides));
    }

    public function testAutoCutsAtHeadingsWhenTheDocumentHasNoSeparator(): void
    {
        $deck = new Slicer()->slice($this->document(
            $this->heading('Deck', 1),
            $this->paragraph('Intro'),
            $this->heading('Part', 2),
            $this->heading('Detail', 3),
            $this->paragraph('Text'),
            $this->heading('Next', 2),
        ));

        self::assertSame([['h-Deck', 'p-Intro'], ['h-Part', 'h-Detail', 'p-Text'], ['h-Next']], $this->ids($deck->slides));
        self::assertSame(['Deck', 'Part', 'Next'], array_map(static fn (Slide $slide): ?string => $slide->title, $deck->slides));
    }

    public function testAutoCutsAtSeparatorsOnlyWhenTheDocumentHasOne(): void
    {
        $deck = new Slicer()->slice($this->document(
            $this->heading('Deck', 1),
            $this->heading('Part', 2),
            new Block('d1', 'delimiter'),
            $this->heading('Next', 2),
        ));

        self::assertSame([['h-Deck', 'h-Part'], ['h-Next']], $this->ids($deck->slides));
    }

    public function testAHeadingStartsNoSlideBeforeAnythingIsShown(): void
    {
        $deck = new Slicer(SplitMode::Both)->slice($this->document(
            new Block('s1', 'pageStyle', ['classes' => ['lead']]),
            $this->notes('n1', $this->paragraph('Say hello')),
            $this->heading('Title', 1),
            new Block('d1', 'delimiter'),
            $this->heading('Next', 1),
            $this->heading('Again', 2),
        ));

        self::assertSame([['h-Title'], ['h-Next'], ['h-Again']], $this->ids($deck->slides));
        self::assertSame(['lead'], $deck->slides[0]->classes);
        self::assertSame(['p-Say hello'], $this->blockIds($deck->slides[0]->notes));
    }

    public function testTheHeadingLevelAndTheModeChooseTheCuts(): void
    {
        $document = $this->document(
            $this->heading('One', 1),
            $this->heading('Two', 2),
            new Block('d1', 'delimiter'),
            $this->heading('Three', 3),
            $this->heading('Untyped', null),
        );

        self::assertSame([['h-One'], ['h-Two', 'd1'], ['h-Three'], ['h-Untyped']], $this->ids(new Slicer(SplitMode::Headings, 3)->slice($document)->slides));
        self::assertSame([['h-One', 'h-Two', 'd1', 'h-Three', 'h-Untyped']], $this->ids(new Slicer(SplitMode::Headings, 1)->slice($document)->slides));
        self::assertSame([['h-One', 'h-Two'], ['h-Three', 'h-Untyped']], $this->ids(new Slicer(SplitMode::Separators, 6)->slice($document)->slides));
        self::assertSame([['h-One'], ['h-Two'], ['h-Three'], ['h-Untyped']], $this->ids(new Slicer(SplitMode::Both, 3)->slice($document)->slides));
        self::assertSame([['h-One'], ['h-Two'], ['h-Three'], ['h-Untyped']], $this->ids(new Slicer(SplitMode::Both, 2)->slice($document)->slides));
        self::assertSame([['h-One', 'h-Two'], ['h-Three', 'h-Untyped']], $this->ids(new Slicer(SplitMode::Both, 1)->slice($document)->slides));
    }

    public function testALevelThatIsNotAnIntegerStartsNoSlide(): void
    {
        $deck = new Slicer(SplitMode::Headings)->slice($this->document($this->heading('One', 2), new Block('h-Text', 'header', ['text' => 'Text', 'level' => '1'])));

        self::assertSame([['h-One', 'h-Text']], $this->ids($deck->slides));
    }

    public function testInHeadingModeASeparatorIsShown(): void
    {
        $deck = new Slicer(SplitMode::Headings)->slice($this->document($this->heading('One', 2), new Block('d1', 'delimiter'), $this->heading('Two', 2)));

        self::assertSame([['h-One', 'd1'], ['h-Two']], $this->ids($deck->slides));
    }

    public function testHeadingDividerCutsAtSeparatorsAndHeadings(): void
    {
        $document = $this->document($this->heading('One', 1), $this->heading('Two', 2), $this->heading('Three', 3), new Block('d1', 'delimiter'), $this->paragraph('End'));

        $deck = new Slicer(SplitMode::Separators, 6)->slice($document, ['headingDivider' => 2]);

        self::assertSame([['h-One'], ['h-Two', 'h-Three'], ['p-End']], $this->ids($deck->slides));
    }

    public function testEmptySlidesAreDroppedAndAHeadingIsNotATitleWithoutText(): void
    {
        $deck = new Slicer()->slice($this->document(
            new Block('d0', 'delimiter'),
            new Block('d1', 'delimiter'),
            new Block('s1', 'pageStyle', ['classes' => ['lost']]),
            new Block('d2', 'delimiter'),
            new Block('h1', 'header', ['text' => '<b> </b>', 'level' => 1]),
            new Block('d3', 'delimiter'),
            $this->paragraph('Only text'),
            new Block('d4', 'delimiter'),
            $this->notes('n1', $this->paragraph('Only notes')),
        ));

        self::assertSame([['h1'], ['p-Only text'], []], $this->ids($deck->slides));
        self::assertNull($deck->slides[0]->title);
        self::assertNull($deck->slides[1]->title);
        self::assertTrue($deck->slides[2]->hasNotes());
        self::assertSame(3, $deck->slides[2]->number);
    }

    public function testTheTitleIsThePlainTextOfTheFirstHeading(): void
    {
        $deck = new Slicer(SplitMode::Separators)->slice($this->document(
            $this->paragraph('Before'),
            new Block('h1', 'header', ['text' => ' <em>Caf&eacute;</em> &amp; &quot;tea&quot; &apos;n&#039; ', 'level' => 2]),
            new Block('h2', 'header', ['text' => 'Second', 'level' => 3]),
        ));

        self::assertSame('Café & "tea" \'n\'', $deck->slides[0]->title);
    }

    public function testAHeadingWithoutTextIsSkippedForTheTitle(): void
    {
        $deck = new Slicer(SplitMode::Separators)->slice($this->document(new Block('h1', 'header', ['level' => 2]), new Block('h2', 'header', ['text' => 'Real', 'level' => 3])));

        self::assertSame('Real', $deck->slides[0]->title);
    }

    public function testPageStylesAndPageNumbersShapeTheirSlide(): void
    {
        $deck = new Slicer()->slice($this->document(
            new Block('s1', 'pageStyle', ['classes' => ['lead', 'bad class', 7, 'lead']]),
            new Block('s2', 'pageStyle', ['classes' => 'dark']),
            new Block('s3', 'pageStyle', ['classes' => ['invert']]),
            new Block('n1', 'pageNumber', ['mode' => 'skip']),
            new Block('n2', 'pageNumber', ['mode' => 'auto']),
            $this->paragraph('Cover'),
            new Block('d1', 'delimiter'),
            new Block('n3', 'pageNumber', ['mode' => 'auto']),
            $this->paragraph('Body'),
        ));

        self::assertSame(['lead', 'invert'], $deck->slides[0]->classes);
        self::assertFalse($deck->slides[0]->paginated);
        self::assertSame([], $deck->slides[1]->classes);
        self::assertTrue($deck->slides[1]->paginated);
        self::assertSame([['p-Cover'], ['p-Body']], $this->ids($deck->slides));
    }

    public function testNotesAreTakenOutWhereverTheyAreNested(): void
    {
        $nested = $this->notes('n2', $this->paragraph('Deep note'));
        $columns = new Block('c1', 'columns', ['cols' => [['blocks' => [$this->paragraph('Left'), $nested]], ['blocks' => [$this->paragraph('Right')]]]]);
        $toggle = new Block('t1', 'toggle', ['text' => 'More', 'items' => [$this->paragraph('Inside')]]);
        $tuned = new Block('q1', 'quote', ['text' => 'Quoted'], ['note' => ['text' => "  Line one\nLine two  "], 'alignment' => ['alignment' => 'left']]);

        $deck = new Slicer()->slice($this->document(
            $this->notes('n1', $this->paragraph('Top note'), $this->paragraph('Second top note')),
            $columns,
            $toggle,
            $tuned,
            new Block('e1', 'paragraph', ['text' => 'Empty note'], ['note' => ['text' => '   ']]),
            new Block('e2', 'paragraph', ['text' => 'Odd note'], ['note' => 'text']),
            new Block('e3', 'paragraph', ['text' => 'Odd text'], ['note' => ['text' => 4]]),
            new Block('o1', 'container', ['classes' => ['other'], 'blocks' => [$this->paragraph('Shown')]]),
            new Block('o2', 'container', ['classes' => 'notes', 'blocks' => [$this->paragraph('Shown too')]]),
            new Block('o3', 'wide', ['classes' => ['notes'], 'blocks' => [$this->paragraph('Shown wide')]]),
        ));

        $slide = $deck->slides[0];
        self::assertSame(['p-Top note', 'p-Second top note', 'p-Deep note'], $this->blockIds($slide->notes));
        self::assertSame(["Line one\nLine two"], $slide->noteTexts);
        self::assertSame(['c1', 't1', 'q1', 'e1', 'e2', 'e3', 'o1', 'o2', 'o3'], $this->blockIds($slide->content));

        $cols = $slide->content[0]->data['cols'] ?? null;
        self::assertIsArray($cols);
        self::assertSame([0, 1], array_keys($cols));
        self::assertIsArray($cols[0]);
        self::assertIsArray($cols[0]['blocks']);
        self::assertSame([0], array_keys($cols[0]['blocks']));
        self::assertSame(['alignment' => ['alignment' => 'left']], $slide->content[2]->tunes);
        self::assertSame([], $slide->content[3]->tunes);
        self::assertSame(['p-Inside'], $this->blockIds($slide->content[1]->children()));
        self::assertSame('columns', $slide->content[0]->type);
        self::assertSame(['text' => 'Quoted'], $slide->content[2]->data);
    }

    public function testNotesInsideNotesAndTheirTunesAreNotesToo(): void
    {
        $inner = new Block('i1', 'paragraph', ['text' => 'Inner'], ['note' => ['text' => 'Tuned inner']]);
        $notes = new Block('n1', 'container', ['classes' => ['notes'], 'blocks' => [$inner]], ['note' => ['text' => 'On the container']]);

        $deck = new Slicer()->slice($this->document($this->paragraph('Shown'), $notes));

        self::assertSame(['i1'], $this->blockIds($deck->slides[0]->notes));
        self::assertSame([], $deck->slides[0]->notes[0]->tunes);
        self::assertSame(['Tuned inner', 'On the container'], $deck->slides[0]->noteTexts);
    }

    public function testAnotherNotesClassIsRead(): void
    {
        $deck = new Slicer(notesClass: 'speaker')->slice($this->document(
            $this->paragraph('Shown'),
            new Block('n1', 'container', ['classes' => ['speaker'], 'blocks' => [$this->paragraph('Said')]]),
            $this->notes('n2', $this->paragraph('Not notes here')),
        ));

        self::assertSame(['p-Said'], $this->blockIds($deck->slides[0]->notes));
        self::assertSame(['p-Shown', 'n2'], $this->blockIds($deck->slides[0]->content));
    }

    public function testTheDirectivesBecomeTheSettings(): void
    {
        $deck = new Slicer(aspectRatio: AspectRatio::Standard)->slice($this->document($this->paragraph('One')), ['title' => 'Talk', 'theme' => 'gaia']);

        self::assertSame('Talk', $deck->settings->title);
        self::assertSame(AspectRatio::Standard, $deck->settings->aspectRatio);
        self::assertSame(['theme'], $deck->ignoredDirectives);
        self::assertSame(AspectRatio::Wide, new Slicer()->slice($this->document($this->paragraph('One')))->settings->aspectRatio);
    }

    public function testTheIdentifierIsAHashOfTheBlocks(): void
    {
        $one = new Slicer()->slice($this->document($this->paragraph('One')));
        $same = new Slicer(SplitMode::Headings)->slice(new Document([$this->paragraph('One')], 42, '2.31'));
        $other = new Slicer()->slice($this->document($this->paragraph('Two')));

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $one->id);
        self::assertSame($one->id, $same->id);
        self::assertNotSame($one->id, $other->id);
        self::assertSame(hash('xxh128', '[{"id":"p-One","type":"paragraph","data":{"text":"One"}}]'), $one->id);
        self::assertSame(hash('xxh128', '[{"id":"p-é","type":"paragraph","data":{"text":"'."\u{fffd}".'"}}]'), new Slicer()->slice($this->document(new Block('p-é', 'paragraph', ['text' => "\xff"])))->id);
    }

    public function testTheIdentifierIsMadeOfWhatJsonCanWrite(): void
    {
        $deck = new Slicer()->slice($this->document(new Block('p', 'paragraph', ['text' => 'x', 'n' => \INF])));

        self::assertSame(hash('xxh128', '[{"id":"p","type":"paragraph","data":{"text":"x","n":0}}]'), $deck->id);
    }

    public function testAnEmptyDocumentMakesAnEmptyDeck(): void
    {
        self::assertSame(0, new Slicer()->slice(new Document())->count());
    }

    public function testTooManySlidesAreRefused(): void
    {
        $blocks = [];

        foreach (range(1, 3) as $number) {
            $blocks[] = $this->heading('S'.$number, 2);
        }

        self::assertSame(2, new Slicer(maxSlides: 2)->slice($this->document(...\array_slice($blocks, 0, 2)))->count());

        $this->expectException(DeckRefused::class);
        $this->expectExceptionMessageIsOrContains('The document makes more than 2 slides, the most a deck holds.');

        new Slicer(maxSlides: 2)->slice($this->document(...$blocks));
    }

    public function testBlocksNestedTooDeepAreRefused(): void
    {
        self::assertSame(1, new Slicer()->slice($this->document($this->deep(62)))->count());
        self::assertSame(1, new Slicer()->slice($this->document($this->nest(21)))->count());

        $this->expectException(DeckRefused::class);
        $this->expectExceptionMessageIsOrContains('The document nests its blocks and their data more than 64 levels deep.');

        new Slicer()->slice($this->document($this->deep(63)));
    }

    public function testBlocksInsideBlocksCountTheirLevels(): void
    {
        $this->expectException(DeckRefused::class);

        new Slicer()->slice($this->document($this->nest(22)));
    }

    public function testNotesNestedTooDeepAreRefused(): void
    {
        self::assertCount(1, new Slicer()->slice($this->document($this->notes('n0', $this->deep(61))))->slides[0]->notes);

        $this->expectException(DeckRefused::class);

        new Slicer()->slice($this->document($this->notes('n0', $this->deep(62))));
    }

    public function testNotesNestedInBlocksCountTheirLevels(): void
    {
        $inner = new Block('w', 'wide', ['blocks' => [$this->notes('n0', $this->deep(58))]]);
        self::assertCount(1, new Slicer()->slice($this->document($inner))->slides[0]->notes);

        $this->expectException(DeckRefused::class);

        new Slicer()->slice($this->document(new Block('w', 'wide', ['blocks' => [$this->notes('n0', $this->deep(59))]])));
    }

    public function testTheOptionsAreChecked(): void
    {
        foreach ([[0, 1, 'notes'], [7, 1, 'notes'], [2, 0, 'notes'], [2, 1, 'two words'], [2, 1, '']] as [$level, $max, $class]) {
            try {
                new Slicer(SplitMode::Auto, $level, $class, $max);
                self::fail('The options should be refused.');
            } catch (InvalidOption $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }

        foreach ([[1, 1, 'n'], [6, 1, str_repeat('n', 64)]] as [$level, $max, $class]) {
            self::assertSame(0, new Slicer(SplitMode::Auto, $level, $class, $max)->slice(new Document())->count());
        }
    }

    public function testTheMessagesOfTheOptionsSayWhatIsWrong(): void
    {
        $this->expectExceptionMessageIsOrContains('The heading level that starts a slide is 1 to 6, not 7.');
        new Slicer(headingLevel: 7);
    }

    public function testTheMessageOfTheLimitSaysIt(): void
    {
        $this->expectExceptionMessageIsOrContains('A deck holds at least one slide, not 0.');
        new Slicer(maxSlides: 0);
    }

    public function testTheMessageOfTheClassSaysIt(): void
    {
        $this->expectExceptionMessageIsOrContains('The class of the notes is 1 to 64 letters, digits, "_" or "-"; "a b" is not.');
        new Slicer(notesClass: 'a b');
    }

    /**
     * A paragraph whose data holds lists nested to a number of levels.
     */
    private function deep(int $levels): Block
    {
        $value = 'leaf';

        for ($level = 0; $level < $levels; ++$level) {
            $value = [$value];
        }

        return new Block('deep', 'paragraph', ['text' => 'Deep', 'extra' => $value]);
    }

    private function nest(int $depth): Block
    {
        $block = $this->paragraph('Leaf');

        for ($level = 1; $level < $depth; ++$level) {
            $block = new Block('w'.$level, 'wide', ['blocks' => [$block]]);
        }

        return $block;
    }

    private function document(Block ...$blocks): Document
    {
        return new Document(array_values($blocks));
    }

    private function heading(string $text, ?int $level): Block
    {
        return new Block('h-'.$text, 'header', null === $level ? ['text' => $text] : ['text' => $text, 'level' => $level]);
    }

    public function testWhatFollowsANotesContainerInTheSameDataIsKept(): void
    {
        $columns = new Block('c1', 'columns', ['cols' => [['blocks' => [$this->notes('n1', $this->paragraph('Note')), $this->paragraph('After')]]], 'caption' => 'Kept']);
        $slide = new Slicer()->slice($this->document($columns))->slides[0];

        self::assertSame(['p-Note'], $this->blockIds($slide->notes));
        self::assertEquals([['blocks' => [$this->paragraph('After')]]], $slide->content[0]->data['cols']);
        self::assertSame('Kept', $slide->content[0]->data['caption']);
    }

    public function testAListWhoseNotesWereTakenOutIsRenumbered(): void
    {
        $columns = new Block('c1', 'columns', ['cols' => [['blocks' => [$this->paragraph('A'), $this->notes('n1', $this->paragraph('Note')), $this->paragraph('B')]]]]);
        $slide = new Slicer()->slice($this->document($columns))->slides[0];

        $blocks = $slide->content[0]->data['cols'];
        self::assertIsArray($blocks);
        self::assertIsArray($blocks[0]);
        self::assertIsArray($blocks[0]['blocks']);
        self::assertSame([0, 1], array_keys($blocks[0]['blocks']));
    }

    private function paragraph(string $text): Block
    {
        return new Block('p-'.$text, 'paragraph', ['text' => $text]);
    }

    private function notes(string $id, Block ...$blocks): Block
    {
        return new Block($id, 'container', ['classes' => ['notes'], 'blocks' => array_values($blocks)]);
    }

    /**
     * @param list<Slide> $slides
     *
     * @return list<list<string>>
     */
    private function ids(array $slides): array
    {
        return array_map(fn (Slide $slide): array => $this->blockIds($slide->content), $slides);
    }

    /**
     * @param list<Block> $blocks
     *
     * @return list<string>
     */
    private function blockIds(array $blocks): array
    {
        return array_map(static fn (Block $block): string => $block->id, $blocks);
    }
}
