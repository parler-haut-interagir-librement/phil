<?php

declare(strict_types=1);

namespace Phil\MarkupBundle\Tests\Unit\Infrastructure;

use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Exception\InvalidSource;
use Phil\Markup\Level;
use Phil\Markup\MarkupFormat;
use Phil\Markup\Reader\BBCode\BBCodeReader;
use Phil\Markup\Reader\Text\TextReader;
use Phil\MarkupBundle\Infrastructure\Conversion\UjoConverter;
use Phil\MarkupBundle\Infrastructure\Format\MarkupFormats;
use Phil\MarkupBundle\Infrastructure\Format\UnavailableFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UjoConverter::class)]
final class UjoConverterTest extends TestCase
{
    public function testEveryEnabledFormatIntoUjo(): void
    {
        $converter = $this->converter();

        self::assertInstanceOf(TextReader::class, $converter->reader());
        self::assertInstanceOf(BBCodeReader::class, $converter->reader('bbcode', 'basic'));
        self::assertSame('{"blocks":[{"id":"b1","type":"paragraph","data":{"text":"a<br>b"}}]}', $converter->toJson("a\nb"));
        self::assertSame('{"blocks":[{"id":"b1","type":"header","data":{"text":"T","level":2},"tunes":{"anchor":{"anchor":"t"}}}]}', $converter->toJson('## T', MarkupFormat::Markdown));
        self::assertSame("{\n    \"blocks\": []\n}", $converter->toJson('', 'markdown', pretty: true));
        self::assertSame(['title' => 'N'], $converter->read("---\ntitle: N\n---\nx", 'markdown')->metadata);
        self::assertSame('{"blocks":[{"id":"x","type":"paragraph","data":{"text":"y"}}]}', $converter->toJson('{"blocks":[{"id":"x","type":"paragraph","data":{"text":"y"}}]}', 'ujo'));
    }

    public function testRawHtmlIsKeptUpToTheCeilingOfItsFormat(): void
    {
        $converter = $this->converter();

        self::assertSame('raw', $converter->read('<div>x</div>', 'markdown')->document->blocks[0]->type);
        self::assertSame('paragraph', $converter->read('<div>x</div>', 'markdown', Level::Forum)->document->blocks[0]->type);
        self::assertSame('paragraph', $converter->read("[html]\n<div>x</div>\n[/html]", 'bbcode', 'all')->document->blocks[0]->type);
    }

    public function testAFormatNotEnabledOrASourceNotValidIsRefused(): void
    {
        try {
            $this->converter()->toJson('x', 'html');
            self::fail('html');
        } catch (UnavailableFormat $unavailableFormat) {
            self::assertStringContainsString('"html"', $unavailableFormat->getMessage());
        }

        $this->expectException(InvalidSource::class);
        $this->converter()->toJson('{', 'ujo');
    }

    private function converter(): UjoConverter
    {
        return new UjoConverter(
            new DefaultCompiler(),
            new MarkupFormats(['text', 'markdown', 'bbcode', 'ujo'], 'text', Level::All, ['bbcode' => Level::Forum]),
        );
    }
}
