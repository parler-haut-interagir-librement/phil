<?php

declare(strict_types=1);

namespace Phil\SocialCards\Tests\Unit\Preview;

use Phil\SocialCards\Preview\LinkFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LinkFinder::class)]
final class LinkFinderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function texts(): iterable
    {
        yield 'none' => ['No link, only example.org and www.example.org.', []];
        yield 'one' => ['See https://example.org/post', ['https://example.org/post']];
        yield 'in order, each once' => ["http://a.example/1 then https://b.example/2\nand http://a.example/1 again", ['http://a.example/1', 'https://b.example/2']];
        yield 'case of the scheme' => ['HTTPS://Example.org/X', ['HTTPS://Example.org/X']];
        yield 'end of a sentence' => ['Read https://example.org/a. Then https://example.org/b, https://example.org/c; https://example.org/d: https://example.org/e! https://example.org/f?', ['https://example.org/a', 'https://example.org/b', 'https://example.org/c', 'https://example.org/d', 'https://example.org/e', 'https://example.org/f']];
        yield 'guillemets and quotes' => ['« https://example.org/fr » “https://example.org/en” ‹https://example.org/x› ’https://example.org/y’ …https://example.org/z…', ['https://example.org/fr', 'https://example.org/en', 'https://example.org/x', 'https://example.org/y', 'https://example.org/z']];
        yield 'a query kept' => ['https://example.org/search?q=a&b=c#top.', ['https://example.org/search?q=a&b=c#top']];
        yield 'parentheses around' => ['(see https://example.org/a)', ['https://example.org/a']];
        yield 'parentheses it opened' => ['(see https://example.org/wiki/A_(b))', ['https://example.org/wiki/A_(b)']];
        yield 'brackets' => ['[https://example.org/a] and https://example.org/b[1]', ['https://example.org/a', 'https://example.org/b[1]']];
        yield 'several marks' => ['(https://example.org/a).)', ['https://example.org/a']];
        yield 'angle brackets and quotes stop it' => ['<https://example.org/a> "https://example.org/b" `https://example.org/c`', ['https://example.org/a', 'https://example.org/b', 'https://example.org/c']];
        yield 'inside a word' => ['xhttps://example.org/ a/https://example.org/b user@https://example.org/c', []];
        yield 'no host' => ['https:// https:///path https://?q https://.example', []];
        yield 'letters of the host' => ['https://bücher.example/é', ['https://bücher.example/é']];
        yield 'other schemes' => ['ftp://example.org/ javascript:alert(1) mailto:a@example.org', []];
        yield 'control characters stop it' => ["https://example.org/a\x00b", ['https://example.org/a']];
    }

    /**
     * @param list<string> $links
     */
    #[DataProvider('texts')]
    public function testTheLinksOfAText(string $text, array $links): void
    {
        self::assertSame($links, LinkFinder::find($text));
    }

    public function testTheCountIsBounded(): void
    {
        $text = implode(' ', array_map(static fn (int $i): string => 'https://example.org/'.$i, range(1, 20)));

        self::assertCount(10, LinkFinder::find($text));
        self::assertSame(['https://example.org/1', 'https://example.org/2'], LinkFinder::find($text, 2));
        self::assertSame([], LinkFinder::find($text, 0));
        self::assertSame('https://example.org/1', LinkFinder::first($text));
        self::assertNull(LinkFinder::first('nothing'));
        self::assertSame(['https://example.org/2'], LinkFinder::find('https://example.org/2 https://example.org/2 https://example.org/3', 1), 'A link found twice counts once.');
    }

    public function testTheLengthIsBounded(): void
    {
        $longest = 'https://example.org/'.str_repeat('a', 2048 - 20);
        $tooLong = $longest.'a';

        self::assertSame([$longest], LinkFinder::find($longest));
        self::assertSame(['https://example.org/b'], LinkFinder::find($tooLong.' https://example.org/b'));
    }

    public function testOnlyTheBeginningOfAHugeTextIsRead(): void
    {
        $text = str_repeat('é', 32_767).' https://example.org/late';

        self::assertSame([], LinkFinder::find($text), 'The link starts past the bytes read.');
        self::assertSame(['https://example.org/early'], LinkFinder::find('https://example.org/early '.$text));
        self::assertSame(['https://example.org/edge'], LinkFinder::find(str_repeat(' ', 65_536 - 24).'https://example.org/edge'), 'A link that ends at the last byte read is found.');
        self::assertSame(['https://example.org/edg'], LinkFinder::find(str_repeat(' ', 65_536 - 23).'https://example.org/edge'));
    }

    public function testTheCutNeverSplitsACharacter(): void
    {
        // 21 bytes, then characters of two bytes: the last one starts at the byte 65 535 and would end past the cut.
        $text = 'https://example.org/ '.str_repeat('é', 32_758);

        self::assertSame(['https://example.org/'], LinkFinder::find($text));
    }

    public function testMarksAreTrimmedOneAfterTheOtherWhateverTheirLength(): void
    {
        self::assertSame(['https://example.org/a'], LinkFinder::find('https://example.org/a”.'));
        self::assertSame(['https://example.org/a'], LinkFinder::find('https://example.org/a…»!'));
        self::assertSame(['https://example.org/a'], LinkFinder::find('https://example.org/a.’'));
    }

    public function testInvalidUtf8IsRead(): void
    {
        self::assertSame(['https://example.org/a'], LinkFinder::find("\xFF\xFE https://example.org/a \xC3"));
    }

    public function testClosingParenthesesItDidNotOpenAreAllTrimmed(): void
    {
        $text = 'https://example.org/'.str_repeat(')', 2000).' '.str_repeat('https://e.example/'.str_repeat(')', 60).' ', 900);

        self::assertSame(['https://example.org/', 'https://e.example/'], LinkFinder::find($text));
    }
}
