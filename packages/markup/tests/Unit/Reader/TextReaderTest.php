<?php

declare(strict_types=1);

namespace Phil\Markup\Tests\Unit\Reader;

use Phil\Markup\Adapter\PlainText;
use Phil\Markup\Reader\Text\TextReader;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Json\JsonEncoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TextReader::class)]
final class TextReaderTest extends TestCase
{
    public function testParagraphsLinesAndLinks(): void
    {
        $reading = new TextReader()->read("\n  Bonjour <b>&</b>\r\nwww.example.com.\r\n\n\n\nfin\0\n\n");

        self::assertSame([], $reading->metadata);
        self::assertSame(
            '{"blocks":[{"id":"b1","type":"paragraph","data":{"text":"Bonjour &lt;b&gt;&amp;&lt;/b&gt;<br><a href=\"https://www.example.com\">www.example.com</a>."}},{"id":"b2","type":"paragraph","data":{"text":"fin�"}}]}',
            new JsonEncoder()->encode($reading->document),
        );
    }

    public function testNothingToRead(): void
    {
        self::assertSame([], new TextReader()->read(" \n\n\t\n")->document->blocks);
        self::assertSame([], new TextReader()->read('')->document->blocks);
    }

    public function testTheSameTextAsTheCompiler(): void
    {
        $source = "a https://example.com/x?y=1&z=2\nb\n\nc";

        self::assertSame(
            str_replace("<br>\n", '<br>', PlainText::compile($source)),
            preg_replace('/ class="[^"]*"/', '', HtmlRenderer::default()->render(new TextReader()->read($source)->document)),
        );
    }
}
