<?php

declare(strict_types=1);

namespace Phil\TextDiffBundle\Tests\Unit;

use InvalidArgumentException;
use Phil\TextDiff\TextDiff;
use Phil\TextDiff\Tokenizer;
use Phil\TextDiffBundle\Infrastructure\Twig\TextDiffExtension;
use Phil\Ujo\Block;
use Phil\Ujo\Exception\InvalidJson;
use Phil\Ujo\Html\BlockRenderer;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Html\Rendering;
use Phil\Ujo\Json\JsonDecoder;
use Phil\Ujo\Ujo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Twig\Markup;

#[CoversClass(TextDiffExtension::class)]
final class TextDiffExtensionTest extends TestCase
{
    private const string OLD = '{"blocks":[{"id":"a","type":"paragraph","data":{"text":"Hello world"}},{"id":"b","type":"paragraph","data":{"text":"Gone"}}]}';

    private const string NEW = '{"blocks":[{"id":"a","type":"paragraph","data":{"text":"Hello big world"}}]}';

    public function testTextsAreComparedByTheConfiguredTokenizerOrTheOneNamed(): void
    {
        $words = new TextDiffExtension(new TextDiff());
        $lines = new TextDiffExtension(new TextDiff(), Tokenizer::Lines);

        self::assertSame('The <del>quick</del><ins>slow</ins> fox', $words->textDiff('The quick fox', 'The slow fox'));
        self::assertSame('<del>The quick fox</del><ins>The slow fox</ins>', $lines->textDiff('The quick fox', 'The slow fox'));
        self::assertSame('The <del>quick</del><ins>slow</ins> fox', $lines->textDiff('The quick fox', 'The slow fox', 'words'));
        self::assertSame("one\n<del>two</del><ins>2</ins>", $words->textDiff("one\ntwo", "one\n2", 'lines'));
    }

    public function testPlainTextIsEscapedAndNullIsEmpty(): void
    {
        $differ = new TextDiffExtension(new TextDiff());

        self::assertSame('&lt;b&gt;a&lt;/b&gt;<ins> &amp; b</ins>', $differ->textDiff('<b>a</b>', new Markup('<b>a</b> & b', 'UTF-8')));
        self::assertSame('<ins>new</ins>', $differ->textDiff(null, 'new'));
        self::assertSame('<del>old</del>', $differ->textDiff('old', null));
        self::assertSame('', $differ->textDiff(null, null));
    }

    public function testHtmlIsMarkupOnlyWhenMarkedSafe(): void
    {
        $differ = new TextDiffExtension(new TextDiff(), Tokenizer::Html);

        self::assertSame('<p>Hello <del>big</del><i><ins>small</ins></i> world</p>', $differ->textDiff(new Markup('<p>Hello <b>big</b> world</p>', 'UTF-8'), new Markup('<p>Hello <i>small</i> world</p>', 'UTF-8')));
        self::assertSame('&lt;p&gt;<del>a</del><ins>b</ins>&lt;/p&gt;', $differ->textDiff('<p>a</p>', '<p>b</p>'));
        // The old text's tags are markup, the new text's are text: a tag never reaches the page from a value not marked safe.
        self::assertSame('<ins>&lt;p&gt;</ins>a<ins>&lt;/p&gt;&lt;script&gt;</ins>', $differ->textDiff(new Markup('<p>a</p>', 'UTF-8'), '<p>a</p><script>'));
        self::assertSame('<ins>&lt;b&gt;</ins>', $differ->textDiff(null, '<b>'));
        self::assertSame('<ins>&quot;&#039;</ins>', $differ->textDiff(null, '"\''));
        self::assertSame("<ins>\u{FFFD}</ins>", $differ->textDiff(null, "\xFF"));
        self::assertSame('&lt;b&gt; &amp;amp;', new TextDiffExtension(new TextDiff())->textDiff('<b> &amp;', '<b> &amp;', 'html'));
    }

    public function testTheSanitizerCleansWhatIsNotMarkedSafe(): void
    {
        $sanitizer = new HtmlSanitizer(new HtmlSanitizerConfig()->allowSafeElements());
        $differ = new TextDiffExtension(new TextDiff(), Tokenizer::Html, sanitizer: $sanitizer);

        self::assertSame('<p>Hello <del>big</del><i><ins>small</ins></i> world</p>', $differ->textDiff('<p>Hello <b>big</b> world</p>', '<p>Hello <i>small</i> world<script>alert(1)</script></p>'));
        self::assertSame('<p><ins>a</ins></p>', $differ->textDiff(null, new Markup('<p>a</p>', 'UTF-8')));
        self::assertSame('one &lt;b&gt;<del>two</del><ins>2</ins>', new TextDiffExtension(new TextDiff(), sanitizer: $sanitizer)->textDiff('one <b>two', 'one <b>2'));
    }

    public function testAnUnknownTokenizerIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The text_diff filter compares by words, lines, paragraphs or html; not by "letters".');

        new TextDiffExtension(new TextDiff())->textDiff('a', 'b', 'letters');
    }

    public function testDocumentsAreComparedBlockByBlock(): void
    {
        $expected = '<div class="text-diff-block text-diff-changed"><p class="ujo-paragraph">Hello <ins>big </ins>world</p></div>'."\n"
            .'<div class="text-diff-block text-diff-removed"><del><p class="ujo-paragraph">Gone</p></del></div>'."\n";
        $ujo = new Ujo();

        self::assertSame($expected, new TextDiffExtension(new TextDiff())->documentDiff(self::OLD, self::NEW));
        self::assertSame($expected, new TextDiffExtension(new TextDiff(), Tokenizer::Lines, $ujo, HtmlRenderer::default())->documentDiff($ujo->decode(self::OLD), self::NEW));
    }

    public function testTheApplicationsUjoReadsAndRendersTheDocuments(): void
    {
        $renderer = HtmlRenderer::default()->with('paragraph', new class implements BlockRenderer {
            public function render(Block $block, Rendering $rendering): string
            {
                return '<p class="mine">'.$rendering->inline($block->data['text']).'</p>';
            }
        });
        $ujo = new Ujo();

        self::assertSame(
            '<div class="text-diff-block text-diff-changed"><p class="mine">Hello <ins>big </ins>world</p></div>'."\n"
            .'<div class="text-diff-block text-diff-removed"><del><p class="mine">Gone</p></del></div>'."\n",
            new TextDiffExtension(new TextDiff(), renderer: $renderer)->documentDiff($ujo->decode(self::OLD), $ujo->decode(self::NEW)),
        );

        $this->expectException(InvalidJson::class);

        new TextDiffExtension(new TextDiff(), ujo: new Ujo(decoder: new JsonDecoder(16)))->documentDiff(self::OLD, self::NEW);
    }
}
