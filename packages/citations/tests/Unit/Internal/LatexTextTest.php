<?php

declare(strict_types=1);

namespace Phil\Citations\Tests\Unit\Internal;

use Phil\Citations\Internal\LatexText;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LatexText::class)]
final class LatexTextTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function texts(): iterable
    {
        yield 'acute' => ["Caf\\'e", 'Café'];
        yield 'grave in braces' => ['\\`{a} la', 'à la'];
        yield 'circumflex' => ['h\\^otel', 'hôtel'];
        yield 'tilde' => ['Espa\\~na', 'España'];
        yield 'macron' => ['\\={a}', 'ā'];
        yield 'breve' => ['\\u{a}', 'ă'];
        yield 'dot' => ['\\.{z}', 'ż'];
        yield 'umlaut in a group' => ['G{\\"o}del', 'Gödel'];
        yield 'ring' => ['\\r{u}', 'ů'];
        yield 'long umlaut' => ['Erd\\H{o}s', 'Erdős'];
        yield 'caron with a space' => ['\\v s', 'š'];
        yield 'dot under' => ['\\d{a}', 'ạ'];
        yield 'cedilla' => ['\\c{c}a', 'ça'];
        yield 'ogonek' => ['\\k{a}', 'ą'];
        yield 'bar under' => ['\\b{b}', 'ḇ'];
        yield 'dotless i accented' => ['Na\\"{\\i}ve', 'Naïve'];
        yield 'dotless i without braces' => ["\\'\\i", 'í'];
        yield 'dotless j accented' => ['\\v{\\j}', 'ǰ'];
        yield 'capital in a group' => ["{\\'E}cole", 'École'];
        yield 'ring letter' => ['{\\aa}ngstr\\"om', 'ångström'];
        yield 'sharp s eats a space' => ['Stra\\ss e', 'Straße'];
        yield 'letters' => ['\\o \\O \\ae \\AE \\oe \\OE \\AA \\l \\L', 'øØæÆœŒÅłŁ'];
        yield 'more letters' => ['\\dh\\DH\\th\\TH\\ng\\NG\\S\\P', 'ðÐþÞŋŊ§¶'];
        yield 'symbols' => ['\\copyright\\textregistered\\texttrademark\\euro\\pounds\\textdegree', '©®™€£°'];
        yield 'quotes and dots' => ['\\textquoteleft a\\textquoteright \\textquotedblleft b\\textquotedblright\\ldots\\dots\\textellipsis', '‘a’“b”………'];
        yield 'guillemets' => ['\\guillemotleft x\\guillemotright', '«x»'];
        yield 'dashes' => ['pp. 12--15 --- end', 'pp. 12–15 — end'];
        yield 'dash commands' => ['a\\textendash b\\textemdash c', 'a–b—c'];
        yield 'quotes' => ["``quoted''", '“quoted”'];
        yield 'tie' => ['A~B', "A\u{00A0}B"];
        yield 'escapes' => ['\\& \\% \\$ \\# \\_ \\{ \\}', '& % $ # _ { }'];
        yield 'a line break' => ['a\\\\b', 'a b'];
        yield 'protective braces' => ['{T}he {LaTeX}', 'The LaTeX'];
        yield 'names of TeX' => ['\\TeX{} and \\LaTeX', 'TeX and LaTeX'];
        yield 'an unknown command keeps its argument' => ['\\emph{Bold} text', 'Bold text'];
        yield 'a control symbol' => ['a\\ b\\,c', 'a b,c'];
        yield 'white space' => ["  a \t\n b  ", 'a b'];
        yield 'a lone backslash' => ['\\', ''];
        yield 'an accent at the end' => ["a\\'", 'á'];
        yield 'an accent on a group at the end' => ['\\"{', "\u{0308}"];
        yield 'an accent on a multibyte letter' => ["\\'ø", 'ǿ'];
        yield 'an unclosed accent group' => ['\\"{o', 'ö'];
    }

    #[DataProvider('texts')]
    public function testItMakesLatexPlain(string $latex, string $expected): void
    {
        self::assertSame($expected, LatexText::plain($latex));
    }
}
