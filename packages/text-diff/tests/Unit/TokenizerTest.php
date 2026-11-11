<?php

declare(strict_types=1);

namespace Phil\TextDiff\Tests\Unit;

use Phil\TextDiff\Exception\InvalidText;
use Phil\TextDiff\Exception\TextDiffException;
use Phil\TextDiff\Tokenizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Tokenizer::class)]
#[CoversClass(InvalidText::class)]
final class TokenizerTest extends TestCase
{
    /**
     * @return iterable<string, array{Tokenizer, string, list<string>}>
     */
    public static function texts(): iterable
    {
        yield 'words, spaces and punctuation' => [Tokenizer::Words, "L'été, déjà_2  fini!", ['L', "'", 'été', ',', ' ', 'déjà_2', '  ', 'fini', '!']];
        yield 'words keep marks and graphemes' => [Tokenizer::Words, "Cafe\u{301} 👍🏽.", ["Cafe\u{301}", ' ', '👍🏽', '.']];
        yield 'words cut Han character by character' => [Tokenizer::Words, '漢字abc中文 12', ['漢', '字', 'abc', '中', '文', ' ', '12']];
        yield 'words cut kana with its prolonged sound mark' => [Tokenizer::Words, 'ひらがなカタカナコーヒー', ['ひ', 'ら', 'が', 'な', 'カ', 'タ', 'カ', 'ナ', 'コ', 'ー', 'ヒ', 'ー']];
        yield 'words cut halfwidth katakana' => [Tokenizer::Words, 'ｶﾀ', ['ｶ', 'ﾀ']];
        yield 'words cut Thai keeping its marks' => [Tokenizer::Words, 'น้ำดี', ['น้ำ', 'ดี']];
        yield 'words cut Lao character by character' => [Tokenizer::Words, 'ພາສາ', ['ພ', 'າ', 'ສ', 'າ']];
        yield 'words cut Khmer keeping its marks' => [Tokenizer::Words, 'ខ្មែរ', ['ខ្', 'មែ', 'រ']];
        yield 'words cut Myanmar keeping its marks' => [Tokenizer::Words, 'မြန်မာ', ['မြ', 'န်', 'မ', 'ာ']];
        yield 'words keep Hangul and Latin whole' => [Tokenizer::Words, '한국어 word', ['한국어', ' ', 'word']];
        yield 'words keep line breaks as space' => [Tokenizer::Words, "a\n\tb", ['a', "\n\t", 'b']];
        yield 'words see tags as characters' => [Tokenizer::Words, '<b>x</b>', ['<', 'b', '>', 'x', '<', '/', 'b', '>']];
        yield 'lines and each kind of break' => [Tokenizer::Lines, "one\r\ntwo\rthree\n\nfour", ['one', "\r\n", 'two', "\r", 'three', "\n", "\n", 'four']];
        yield 'lines keep spaces' => [Tokenizer::Lines, " a b \n", [' a b ', "\n"]];
        yield 'paragraphs and blank lines' => [Tokenizer::Paragraphs, "First\nstill first\n\nSecond\n \t\n\nThird\n", ["First\nstill first", "\n\n", 'Second', "\n \t\n\n", "Third\n"]];
        yield 'paragraphs with Windows breaks' => [Tokenizer::Paragraphs, "a\r\n\r\nb", ['a', "\r\n\r\n", 'b']];
        yield 'html tags, comments and references whole' => [Tokenizer::Html, '<p class="x">A&amp;B &#233;<!-- a > b --></p>', ['<p class="x">', 'A', '&amp;', 'B', ' ', '&#233;', '<!-- a > b -->', '</p>']];
        yield 'html comment over lines' => [Tokenizer::Html, "<!--\n-->x", ["<!--\n-->", 'x']];
        yield 'html tag over lines' => [Tokenizer::Html, "<a\nhref=\"y\">z", ["<a\nhref=\"y\">", 'z']];
        yield 'html cuts Han between tags' => [Tokenizer::Html, '<b>中文</b>', ['<b>', '中', '文', '</b>']];
        yield 'html lone brackets' => [Tokenizer::Html, 'a < b', ['a', ' ', '<', ' ', 'b']];
        yield 'html brackets in attribute values' => [Tokenizer::Html, '<abbr title="a>b" lang=\'x>y\' data-n=1 hidden>t</abbr><br/><hr >', ['<abbr title="a>b" lang=\'x>y\' data-n=1 hidden>', 't', '</abbr>', '<br/>', '<hr >']];
        yield 'html declaration' => [Tokenizer::Html, '<!doctype html>', ['<!doctype html>']];
        yield 'html tag left open is text' => [Tokenizer::Html, '<b title="x>', ['<', 'b', ' ', 'title', '=', '"', 'x', '>']];
        yield 'html attribute of no known form is text' => [Tokenizer::Html, '<b a"b=c>', ['<', 'b', ' ', 'a', '"', 'b', '=', 'c', '>']];
        yield 'empty' => [Tokenizer::Html, '', []];
    }

    /**
     * @param list<string> $tokens
     */
    #[DataProvider('texts')]
    public function testCutsTheTextAndLosesNothing(Tokenizer $tokenizer, string $text, array $tokens): void
    {
        self::assertSame($tokens, $tokenizer->tokens($text));
        self::assertSame($text, implode('', $tokenizer->tokens($text)));
    }

    public function testOnlyHtmlIsMarkup(): void
    {
        self::assertSame([false, false, false, true], array_map(static fn (Tokenizer $tokenizer): bool => $tokenizer->isMarkup(), Tokenizer::cases()));
        self::assertSame(['words', 'lines', 'paragraphs', 'html'], array_column(Tokenizer::cases(), 'value'));
    }

    #[DataProvider('tokenizers')]
    public function testRefusesInvalidUtf8(Tokenizer $tokenizer): void
    {
        try {
            $tokenizer->tokens("ok \xC3\x28");
            self::fail('Invalid UTF-8 was read.');
        } catch (InvalidText $invalidText) {
            self::assertInstanceOf(TextDiffException::class, $invalidText);
            self::assertSame('The text cannot be compared: it is not valid UTF-8.', $invalidText->getMessage());
        }
    }

    /**
     * @return iterable<string, array{Tokenizer}>
     */
    public static function tokenizers(): iterable
    {
        foreach (Tokenizer::cases() as $tokenizer) {
            yield $tokenizer->value => [$tokenizer];
        }
    }
}
