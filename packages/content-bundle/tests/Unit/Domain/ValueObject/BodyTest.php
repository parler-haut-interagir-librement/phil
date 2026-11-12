<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Domain\ValueObject;

use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\ValueObject\Body;
use Phil\Markup\MarkupFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Body::class)]
final class BodyTest extends TestCase
{
    public function testABodyKeepsItsSourceItsFormatAndItsHtml(): void
    {
        $body = Body::of('# Title', MarkupFormat::Markdown, '<h1>Title</h1>');

        self::assertSame('# Title', $body->source);
        self::assertSame('markdown', $body->format);
        self::assertSame('<h1>Title</h1>', $body->html);
        self::assertFalse($body->rawHtml, 'an author holds the raw HTML permission only when said');
        self::assertTrue(Body::of('# Title', MarkupFormat::Markdown, '<h1>Title</h1>', true)->rawHtml);
    }

    /**
     * Four leading spaces make a code block in Markdown: the source is never trimmed.
     */
    public function testTheSourceIsKeptAsWritten(): void
    {
        self::assertSame("    code\n", Body::of("    code\n", MarkupFormat::Markdown, '<pre><code>code</code></pre>')->source);
    }

    public function testTheHtmlMayBeEmpty(): void
    {
        self::assertSame('', Body::of('<!-- nothing -->', MarkupFormat::Html, '')->html);
    }

    #[DataProvider('blankSources')]
    public function testABlankSourceIsRefused(string $source): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('other than whitespace');

        Body::of($source, MarkupFormat::Markdown, '');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blankSources(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces' => ['   '];
        yield 'line breaks' => ["\n\n"];
    }

    #[DataProvider('invalidEncodings')]
    public function testASourceOrHtmlThatIsNotUtf8IsRefused(string $source, string $html): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('valid UTF-8');

        Body::of($source, MarkupFormat::Html, $html);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidEncodings(): iterable
    {
        yield 'the source' => ["caf\xC3", '<p>café</p>'];
        yield 'the HTML' => ['café', "<p>caf\xC3</p>"];
    }
}
