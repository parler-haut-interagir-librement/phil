<?php

declare(strict_types=1);

namespace Phil\Typography\Tests\Unit\Fixer;

use Phil\Typography\Fixer\SmartQuotes;
use Phil\Typography\Quote\QuoteStyle;
use Phil\Typography\Text\Characters;
use Phil\Typography\Text\Run;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SmartQuotes::class)]
#[CoversClass(Characters::class)]
final class SmartQuotesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function english(): iterable
    {
        yield 'pair' => ['"Hello"', '“Hello”'];
        yield 'after a space and before punctuation' => ['He said "yes", then "no".', 'He said “yes”, then “no”.'];
        yield 'after opening marks' => ['("a") ["b"] {"c"} -"d" –"e" —"f" /"g"', '(“a”) [“b”] {“c”} -“d” –“e” —“f” /“g”'];
        yield 'after other quotes' => ['«"a"» “"b"” ‘"c"’ „"d"“ ‹"e"›', '«“a”» ““b”” ‘“c”’ „“d”“ ‹“e”›'];
        yield 'after no-break spaces' => ["a\u{A0}\"b\" a\u{202F}\"c\" a\u{2009}\"d\"", "a\u{A0}“b” a\u{202F}“c” a\u{2009}“d”"];
        yield 'inner quotes' => ['"She said \'hi\' to me"', '“She said ‘hi’ to me”'];
        yield 'inner quote before punctuation' => ["'a'. 'b', 'c'; 'd': 'e'! 'f'? ('g') ['h'] {'i'} «'j'» “'k'” ‘'l'’ \"'m'\" 'n'… 'o'– 'p'— 'q'-", '‘a’. ‘b’, ‘c’; ‘d’: ‘e’! ‘f’? (‘g’) [‘h’] {‘i’} «‘j’» “‘k’” ‘‘l’’ “‘m’” ‘n’… ‘o’– ‘p’— ‘q’-'];
        yield 'apostrophes are left' => ["l'été, don't, '90s, rock'n'roll", "l'été, don't, '90s, rock'n'roll"];
        yield 'unpaired opening single quote' => ["'tis the season", "'tis the season"];
        yield 'single quote after a space' => ["the students ' books", "the students ' books"];
        yield 'closing single quote needs a letter before it' => ["'a ' b", "'a ' b"];
        yield 'single inside double, double inside single' => ['"\'a\'" \'"b"\'', '“‘a’” ‘“b”’'];
        yield 'nested singles pair inside out' => ["'a 'b' c'", '‘a ‘b’ c’'];
        yield 'closing quote alone' => ['5" long', '5” long'];
        yield 'quote before a space' => ['" a', '” a'];
        yield 'quote at the end' => ['a "', 'a ”'];
        yield 'after the end of a tag written as text' => ['<b>"a"</b> >"b" >\'c\' d', '<b>“a”</b> >“b” >‘c’ d'];
        yield 'inner quote before the start of a tag written as text' => ["<i>'a'</i>", '<i>‘a’</i>'];
        yield 'emoji' => ['"😀" "👨‍👩‍👧"', '“😀” “👨‍👩‍👧”'];
    }

    #[DataProvider('english')]
    public function testEnglishQuotes(string $text, string $expected): void
    {
        self::assertSame([$expected], new SmartQuotes()->fix(Run::of($text))->texts);
    }

    public function testTheStyleGivesTheMarks(): void
    {
        $fixer = new SmartQuotes(new QuoteStyle('«', '»', '‹', '›'));

        self::assertSame(['«a ‹b› c»'], $fixer->fix(Run::of('"a \'b\' c"'))->texts);
    }

    public function testQuotesArePairedAcrossParts(): void
    {
        self::assertSame(['“', 'word', '”'], new SmartQuotes()->fix(new Run(['"', 'word', '"']))->texts);
        self::assertSame(['say ‘', 'x', '’ now'], new SmartQuotes()->fix(new Run(["say '", 'x', "' now"]))->texts);
        self::assertSame(['“', 'x"y', '”'], new SmartQuotes()->fix(new Run(['"', 'x"y', '"'], [false, true]))->texts);
    }
}
