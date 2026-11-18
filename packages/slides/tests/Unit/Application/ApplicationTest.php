<?php

declare(strict_types=1);

namespace Phil\Slides\Tests\Unit\Application;

use Override;
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Exception\UnknownFormat;
use Phil\Markup\Level;
use Phil\Markup\Reader\Contract\ReaderProvider;
use Phil\Markup\Reader\MarkupReader;
use Phil\Markup\Reader\Reading;
use Phil\Slides\Application\DeckReader;
use Phil\Slides\Application\SlideRenderer;
use Phil\Slides\Domain\Exception\DeckRefused;
use Phil\Slides\Domain\Exception\InvalidOption;
use Phil\Slides\Domain\Slicer;
use Phil\Slides\Domain\ValueObject\Slide;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\InvalidJson;
use Phil\Ujo\Exception\UnknownBlockType;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Ujo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SlideRenderer::class)]
#[CoversClass(DeckReader::class)]
final class ApplicationTest extends TestCase
{
    private const string SOURCE = <<<'MD'
        ---
        title: The talk
        paginate: true
        ---

        # Welcome

        Hello.

        ::: notes
        Thank the **organisers**.
        :::

        ---

        ## Second

        ::: fragment
        One point.
        :::

        <!-- _class: lead -->
        MD;

    public function testTheContentIsRenderedWithoutTheNotes(): void
    {
        $slide = new Slide(1, [new Block('h', 'header', ['text' => 'Title', 'level' => 1]), new Block('p', 'paragraph', ['text' => '<script>x</script>Text'])], [new Block('n', 'paragraph', ['text' => 'Secret'])], ['Hidden']);
        $renderer = new SlideRenderer(new Ujo());

        $html = $renderer->content($slide);

        self::assertSame("<h1 id=\"h\" class=\"ujo-header\">Title</h1>\n<p class=\"ujo-paragraph\">Text</p>", $html);
        self::assertStringNotContainsString('Secret', $html);
        self::assertStringNotContainsString('Hidden', $html);
    }

    public function testTheNotesAreTheirBlocksThenTheirTexts(): void
    {
        $slide = new Slide(1, [], [new Block('n', 'paragraph', ['text' => 'Say <em>it</em>'])], ["First <b>line</b>\nSecond & last", 'Another']);

        self::assertSame(
            "<p class=\"ujo-paragraph\">Say <em>it</em></p>\n<p class=\"ujo-paragraph\">First &lt;b&gt;line&lt;/b&gt;<br>\nSecond &amp; last</p>\n<p class=\"ujo-paragraph\">Another</p>",
            new SlideRenderer(new Ujo())->notes($slide),
        );
        self::assertSame('', new SlideRenderer(new Ujo())->notes(new Slide(1, [new Block('p', 'paragraph', ['text' => 'x'])])));
    }

    public function testTheRendererOfTheApplicationDecides(): void
    {
        $this->expectException(UnknownBlockType::class);

        new SlideRenderer(new Ujo())->content(new Slide(1, [new Block('r', 'raw', ['html' => '<b>raw</b>'])]));
    }

    public function testADeckIsReadFromADocumentOrItsJson(): void
    {
        $reader = new DeckReader(new Slicer(), new Ujo());
        $document = new Document([new Block('a', 'paragraph', ['text' => 'One']), new Block('d', 'delimiter'), new Block('b', 'paragraph', ['text' => 'Two'])]);

        self::assertSame(2, $reader->fromDocument($document, ['title' => 'Doc'])->count());
        self::assertSame('Doc', $reader->fromDocument($document, ['title' => 'Doc'])->settings->title);
        self::assertSame(2, $reader->fromDocument('{"blocks":[{"id":"a","type":"paragraph","data":{"text":"One"}},{"id":"d","type":"delimiter","data":{}},{"id":"b","type":"paragraph","data":{"text":"Two"}}]}')->count());

        $this->expectException(InvalidJson::class);
        $reader->fromDocument('{not json');
    }

    public function testADeckIsReadFromWhatMarkupRead(): void
    {
        $reading = new Reading(new Document([new Block('a', 'paragraph', ['text' => 'One'])]), ['paginate' => true]);

        $deck = new DeckReader(new Slicer(), new Ujo())->fromReading($reading);

        self::assertSame(1, $deck->count());
        self::assertTrue($deck->settings->paginate);
    }

    public function testADeckIsReadFromMarkdownByPhilMarkup(): void
    {
        $deck = new DeckReader(new Slicer(), new Ujo())->fromMarkup(self::SOURCE);

        self::assertSame('The talk', $deck->settings->title);
        self::assertTrue($deck->settings->paginate);
        self::assertSame(2, $deck->count());
        self::assertSame('Welcome', $deck->slides[0]->title);
        self::assertTrue($deck->slides[0]->hasNotes());
        self::assertSame('Second', $deck->slides[1]->title);
        self::assertStringNotContainsString('_class', new SlideRenderer(new Ujo())->content($deck->slides[1]));
    }

    public function testTheLevelAndTheCompilerAreTheConfiguredOnes(): void
    {
        $forum = new DeckReader(new Slicer(), new Ujo(), new DefaultCompiler(), Level::Forum);
        $deck = $forum->fromMarkup("# One\n\n<div class=\"box\">Raw</div>\n");

        // At the forum level, raw HTML is shown as text; at the default level it would become a raw block.
        self::assertStringContainsString('&lt;div class=&quot;box&quot;&gt;Raw&lt;/div&gt;', new SlideRenderer(new Ujo())->content($deck->slides[0]));
        // An HTML comment is a note at every level since phil/markup 0.3: it never reaches the audience.
        self::assertStringNotContainsString('_class', new SlideRenderer(new Ujo())->content($forum->fromMarkup(self::SOURCE)->slides[1]));
        self::assertSame(1, new DeckReader(new Slicer(), new Ujo())->fromMarkup('[b]Bold[/b]', 'bbcode')->count());

        $this->expectException(UnknownFormat::class);
        $forum->fromMarkup('text', 'nothing');
    }

    public function testTheCompilerGivenIsTheOneThatReads(): void
    {
        $provider = new class implements ReaderProvider {
            #[Override]
            public function reader(Level $level, Normalizer $normalizer): MarkupReader
            {
                return new class implements MarkupReader {
                    #[Override]
                    public function read(string $source): Reading
                    {
                        return new Reading(new Document([new Block('a', 'paragraph', ['text' => $source])]));
                    }
                };
            }
        };
        $reader = new DeckReader(new Slicer(), new Ujo(), new DefaultCompiler(readers: ['shout' => $provider]));

        self::assertSame(1, $reader->fromMarkup('Hello', 'shout')->count());
    }

    public function testASourceTooLargeIsRefused(): void
    {
        self::assertSame(1, new DeckReader(new Slicer(), new Ujo(), maxSourceBytes: 4)->fromMarkup('abcd')->count());

        $this->expectException(DeckRefused::class);
        $this->expectExceptionMessageIsOrContains('The source is 6 bytes long, over the 4 bytes a deck is read from.');

        new DeckReader(new Slicer(), new Ujo(), maxSourceBytes: 4)->fromMarkup('abcdé');
    }

    public function testTheLimitOfTheSourceIsChecked(): void
    {
        self::assertSame(1, new DeckReader(new Slicer(), new Ujo(), maxSourceBytes: 1)->fromMarkup('a')->count());

        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessageIsOrContains('A source is read up to at least one byte, not 0.');

        new DeckReader(new Slicer(), new Ujo(), maxSourceBytes: 0);
    }
}
