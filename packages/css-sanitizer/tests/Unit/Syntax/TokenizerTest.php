<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Tests\Unit\Syntax;

use Phil\CssSanitizer\Syntax\Token;
use Phil\CssSanitizer\Syntax\Tokenizer;
use Phil\CssSanitizer\Syntax\TokenType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Tokenizer::class)]
#[UsesClass(Token::class)]
final class TokenizerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function sources(): iterable
    {
        yield 'empty' => ['', []];
        yield 'white space runs' => [" \t\n a", ['ws', 'ident(a)']];
        yield 'punctuation' => ['()[]{},:;', ['(', ')', '[', ']', '{', '}', 'comma', 'colon', 'semicolon']];
        yield 'idents' => ['a -b --c _d é', ['ident(a)', 'ws', 'ident(-b)', 'ws', 'ident(--c)', 'ws', 'ident(_d)', 'ws', 'ident(é)']];
        yield 'ident with digits and hyphens' => ['a1-b2', ['ident(a1-b2)']];
        yield 'functions' => ['rgb( calc(', ['function(rgb)', 'ws', 'function(calc)']];
        yield 'at-keywords' => ['@media @-x @--y @\\41', ['at(media)', 'ws', 'at(-x)', 'ws', 'at(--y)', 'ws', 'at(A)']];
        yield 'at sign alone' => ['@ @1 @-1', ['delim(@)', 'ws', 'delim(@)', 'number(1,int)', 'ws', 'delim(@)', 'number(-1,int)']];
        yield 'hash id' => ['#main #-a #\\31', ['hash(main,id)', 'ws', 'hash(-a,id)', 'ws', 'hash(1,id)']];
        yield 'hash not id' => ['#1a #-1 #--', ['hash(1a)', 'ws', 'hash(-1)', 'ws', 'hash(--,id)']];
        yield 'hash alone' => ['# #!', ['delim(#)', 'ws', 'delim(#)', 'delim(!)']];
        yield 'integers' => ['0 12 +3 -4', ['number(0,int)', 'ws', 'number(12,int)', 'ws', 'number(+3,int)', 'ws', 'number(-4,int)']];
        yield 'decimals' => ['1.5 .5 -.5 +.5', ['number(1.5)', 'ws', 'number(.5)', 'ws', 'number(-.5)', 'ws', 'number(+.5)']];
        yield 'exponents' => ['1e3 1E+3 1e-3 2.5e2', ['number(1e3)', 'ws', 'number(1E+3)', 'ws', 'number(1e-3)', 'ws', 'number(2.5e2)']];
        yield 'not exponents' => ['1e 1e+ 1ex', ['dimension(1,e,int)', 'ws', 'dimension(1,e,int)', 'delim(+)', 'ws', 'dimension(1,ex,int)']];
        yield 'number ends at a second dot' => ['1.2.3', ['number(1.2)', 'number(.3)']];
        yield 'dot without digit' => ['1. .a', ['number(1,int)', 'delim(.)', 'ws', 'delim(.)', 'ident(a)']];
        yield 'dimensions' => ['10px 1.5em -2\\70 x 3--a', ['dimension(10,px,int)', 'ws', 'dimension(1.5,em)', 'ws', 'dimension(-2,px,int)', 'ws', 'dimension(3,--a,int)']];
        yield 'percentages' => ['50% 2.5%', ['percentage(50,int)', 'ws', 'percentage(2.5)']];
        yield 'plus and minus alone' => ['+ - +a -1a', ['delim(+)', 'ws', 'delim(-)', 'ws', 'delim(+)', 'ident(a)', 'ws', 'dimension(-1,a,int)']];
        yield 'minus starting an ident by escape' => ['-\\41', ['ident(-A)']];
        yield 'minus before escaped newline' => ["-\\\n", ['delim(-)', 'delim(\\)', 'ws']];
        yield 'CDO and CDC' => ['<!-- --> <! <!-', ['<!--', 'ws', '-->', 'ws', 'delim(<)', 'delim(!)', 'ws', 'delim(<)', 'delim(!)', 'delim(-)']];
        yield 'comments dropped' => ['a/* x */b /**/ /* open', ['ident(a)', 'ident(b)', 'ws', 'ws']];
        yield 'comments in a row' => ['/**//**/a', ['ident(a)']];
        yield 'double-quoted string' => ['"a\'b"', ["string(a'b)"]];
        yield 'single-quoted string' => ["'a\"b'", ['string(a"b)']];
        yield 'string escapes' => ['"\\41 \\"\\\\x"', ['string(A"\\x)']];
        yield 'string escaped newline' => ["\"a\\\nb\"", ['string(ab)']];
        yield 'string cut by a newline' => ["\"ab\ncd", ['bad-string(ab)', 'ws', 'ident(cd)']];
        yield 'string cut by the end' => ['"ab', ['string(ab)']];
        yield 'string ending on a backslash' => ['"ab\\', ['string(ab)']];
        yield 'url unquoted' => ['url(a.png) URL( b ) url(', ['url(a.png)', 'ws', 'url(b)', 'ws', 'url()']];
        yield 'url quoted is a function' => ['url("a") url(  \'b\') url( "c")', ['function(url)', 'string(a)', ')', 'ws', 'function(url)', 'ws', 'string(b)', ')', 'ws', 'function(url)', 'ws', 'string(c)', ')']];
        yield 'url escapes' => ['url(a\\29 b)', ['url(a)b)']];
        yield 'url cut by the end' => ['url(a b', ['bad-url(a)']];
        yield 'url with space then end' => ['url(a ', ['url(a)']];
        yield 'url with space inside' => ['url(a b) c', ['bad-url(a)', 'ws', 'ident(c)']];
        yield 'url with quote inside' => ['url(a"b) c', ['bad-url(a)', 'ws', 'ident(c)']];
        yield 'url with parenthesis inside' => ['url(a(b) c', ['bad-url(a)', 'ws', 'ident(c)']];
        yield 'url with control character' => ["url(a\x01b) c", ['bad-url(a)', 'ws', 'ident(c)']];
        yield 'url with delete character' => ["url(a\x7Fb) c", ['bad-url(a)', 'ws', 'ident(c)']];
        yield 'url with vertical tab' => ["url(a\x0Bb) c", ['bad-url(a)', 'ws', 'ident(c)']];
        yield 'url with form feed' => ["url(a\x0Cb)", ['bad-url(a)']];
        yield 'url with escaped newline' => ["url(a\\\nb) c", ['bad-url(a)', 'ws', 'ident(c)']];
        yield 'bad url skips escaped parenthesis' => ['url(a b\\)c) d', ['bad-url(a)', 'ws', 'ident(d)']];
        yield 'bad url ending on a backslash' => ['url(a b\\', ['bad-url(a)']];
        yield 'url ending on a backslash' => ['url(a\\', ["url(a\u{FFFD})"]];
        yield 'escapes in idents' => ['\\66 oo  \\000041  \\0  \\110000  \\D800  \\É', ['ident(foo)', 'ws', 'ident(A)', 'ws', "ident(\u{FFFD})", 'ws', "ident(\u{FFFD})", 'ws', "ident(\u{FFFD})", 'ws', 'ident(É)']];
        yield 'escape of six hex digits' => ['\\0000411', ['ident(A1)']];
        yield 'escape of the last code point' => ['\\10FFFF', ["ident(\u{10FFFF})"]];
        yield 'escape before a surrogate' => ['\\D7FF  \\E000', ["ident(\u{D7FF})", 'ws', "ident(\u{E000})"]];
        yield 'escape of a non-hex character' => ['\\g\\-', ['ident(g-)']];
        yield 'escape at the end' => ['a\\', ["ident(a\u{FFFD})"]];
        yield 'backslash before a newline' => ["\\\na", ['delim(\\)', 'ws', 'ident(a)']];
        yield 'escape takes one white space' => ['\\41  b', ['ident(A)', 'ws', 'ident(b)']];
        yield 'escape of four-byte character' => ['\\😀x', ['ident(😀x)']];
        yield 'escape of three-byte character' => ['\\€x', ['ident(€x)']];
        yield 'other delims' => ['* > ~ ! = $ ^ | & ?', ['delim(*)', 'ws', 'delim(>)', 'ws', 'delim(~)', 'ws', 'delim(!)', 'ws', 'delim(=)', 'ws', 'delim($)', 'ws', 'delim(^)', 'ws', 'delim(|)', 'ws', 'delim(&)', 'ws', 'delim(?)']];
        yield 'escape in a hash' => ['#a\\:b', ['hash(a:b,id)']];
        yield 'hash before an escaped newline' => ["#\\\na", ['delim(#)', 'delim(\\)', 'ws', 'ident(a)']];
        yield 'hash of a hyphen before an escaped newline' => ["#-\\\n", ['hash(-)', 'delim(\\)', 'ws']];
        yield 'CDO after text' => ['a<!--b', ['ident(a)', '<!--', 'ident(b)']];
        yield 'at sign and hyphen before an escaped newline' => ["@-\\\n", ['delim(@)', 'delim(-)', 'delim(\\)', 'ws']];
        yield 'comment with a slash after its star' => ['/*/ a */b', ['ident(b)']];
        yield 'exponents then units' => ['1e3px 1e+3px', ['dimension(1e3,px)', 'ws', 'dimension(1e+3,px)']];
        yield 'number then hyphen' => ['1-1 1-a1', ['number(1,int)', 'number(-1,int)', 'ws', 'dimension(1,-a1,int)']];
        yield 'number then hyphen and escaped newline' => ["1-\\\nx", ['number(1,int)', 'delim(-)', 'delim(\\)', 'ws', 'ident(x)']];
        yield 'bad url with escaped parenthesis before a newline' => ["url(a b\\)\n)", ['bad-url(a)']];
        yield 'bad url with escaped parenthesis before another' => ['url(a b\\)) c', ['bad-url(a)', 'ws', 'ident(c)']];
        yield 'escape of the last surrogate' => ['\\DFFF', ["ident(\u{FFFD})"]];
        yield 'ident before an escaped newline' => ["a\\\nb", ['ident(a)', 'delim(\\)', 'ws', 'ident(b)']];
        yield 'ident letters at the ends of ranges' => ['9 z Z A _', ['number(9,int)', 'ws', 'ident(z)', 'ws', 'ident(Z)', 'ws', 'ident(A)', 'ws', 'ident(_)']];
        yield 'character ending on byte 0x80' => ['Ѐ', ['ident(Ѐ)']];
        yield 'url with backspace' => ["url(a\x08b)", ['bad-url(a)']];
        yield 'url with shift out' => ["url(a\x0Eb)", ['bad-url(a)']];
        yield 'url with data link escape' => ["url(a\x10b)", ['bad-url(a)']];
        yield 'url with unit separator' => ["url(a\x1Fb)", ['bad-url(a)']];
        yield 'url with tilde' => ['url(a~b)', ['url(a~b)']];
        yield 'two dots before a digit' => ['..5', ['delim(.)', 'number(.5)']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('sources')]
    public function testItTokenizesAsTheSpecificationSays(string $css, array $expected): void
    {
        $tokens = new Tokenizer()->tokenize($css);
        $eof = array_pop($tokens);

        self::assertNotNull($eof);
        self::assertSame(TokenType::Eof, $eof->type);
        self::assertSame($expected, array_map($this->describe(...), $tokens));
    }

    public function testItPreprocessesNewlinesNullsAndInvalidBytes(): void
    {
        mb_substitute_character(0x3F);
        $substitute = mb_substitute_character();

        self::assertSame("a\nb\nc\nd\u{FFFD}e\u{FFFD}?", Tokenizer::preprocess("a\r\nb\rc\fd\0e\xFF?"));
        self::assertSame($substitute, mb_substitute_character());
    }

    public function testItCountsLines(): void
    {
        $tokens = new Tokenizer()->tokenize("a\r\nb /* x\n\n */ c\n\n\"d\"");

        self::assertSame([1, 1, 2, 2, 4, 4, 4, 6, 6], array_map(static fn (Token $token): int => $token->line(), $tokens));
    }

    public function testItCanBeReused(): void
    {
        $tokenizer = new Tokenizer();
        $tokenizer->tokenize("a\n\nb");

        self::assertSame([1, 1], array_map(static fn (Token $token): int => $token->sourceLine, $tokenizer->tokenize('c')));
    }

    private function describe(Token $token): string
    {
        return match ($token->type) {
            TokenType::Whitespace => 'ws',
            TokenType::Ident => 'ident('.$token->value.')',
            TokenType::Function => 'function('.$token->value.')',
            TokenType::AtKeyword => 'at('.$token->value.')',
            TokenType::Hash => 'hash('.$token->value.($token->isId ? ',id' : '').')',
            TokenType::Number => 'number('.$token->value.($token->isInteger ? ',int' : '').')',
            TokenType::Percentage => 'percentage('.$token->value.($token->isInteger ? ',int' : '').')',
            TokenType::Dimension => 'dimension('.$token->value.','.$token->unit.($token->isInteger ? ',int' : '').')',
            TokenType::String, TokenType::BadString, TokenType::Url, TokenType::BadUrl, TokenType::Delim => $token->type->value.'('.$token->value.')',
            TokenType::Comma, TokenType::Colon, TokenType::Semicolon => $token->type->value,
            default => $token->value,
        };
    }
}
