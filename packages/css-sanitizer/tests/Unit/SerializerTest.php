<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Tests\Unit;

use Phil\CssSanitizer\Serializer;
use Phil\CssSanitizer\Sheet\GroupRule;
use Phil\CssSanitizer\Sheet\ImportRule;
use Phil\CssSanitizer\Sheet\StyleRule;
use Phil\CssSanitizer\Sheet\StyleSheet;
use Phil\CssSanitizer\Syntax\Declaration;
use Phil\CssSanitizer\Syntax\FunctionValue;
use Phil\CssSanitizer\Syntax\Parser;
use Phil\CssSanitizer\Syntax\SimpleBlock;
use Phil\CssSanitizer\Syntax\Token;
use Phil\CssSanitizer\Syntax\Tokenizer;
use Phil\CssSanitizer\Syntax\TokenType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Serializer::class)]
#[CoversClass(StyleSheet::class)]
#[CoversClass(StyleRule::class)]
#[CoversClass(GroupRule::class)]
#[CoversClass(ImportRule::class)]
#[UsesClass(Parser::class)]
#[UsesClass(Tokenizer::class)]
#[UsesClass(Token::class)]
#[UsesClass(FunctionValue::class)]
#[UsesClass(SimpleBlock::class)]
#[UsesClass(Declaration::class)]
final class SerializerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function values(): iterable
    {
        yield 'idents and white space' => ["a \n\t b", 'a b'];
        yield 'escaped ident' => ['\\31 a \\-1 \\- a\\.b \\é', '\\31 a -\\31  \\- a\\.b é'];
        yield 'ident starting with two hyphens' => ['--a --1', '--a --1'];
        yield 'function' => ['RGB( 1 , 2 )', 'RGB( 1 , 2 )'];
        yield 'blocks' => ['[a] (b) {c}', '[a] (b) {c}'];
        yield 'at-keyword' => ['@\\31 x', '@\\31 x'];
        yield 'hash id and not id' => ['#a\\.b #1a #-\\.', '#a\\.b #1a #-\\.'];
        yield 'strings' => ["'a\"b\\\\c'", '"a\\"b\\\\c"'];
        yield 'string with control character' => ['"a\\1 b"', '"a\\1 b"'];
        yield 'url' => ['url(a\\29 b) url( "c" )', 'url("a)b") url( "c" )'];
        yield 'bad url and bad string' => ["url(a b) \"x\n", 'url("") "x" '];
        yield 'numbers kept as written' => ['+1.50 1e3 .5 50% 2.5E-1%', '+1.50 1e3 .5 50% 2.5E-1%'];
        yield 'dimensions' => ['10px 1\\65 x 1\\65 3 2\\45 -1 3\\65 +1 4e 5em', '10px 1ex 1\\65 3 2\\45 -1 3e/**/+1 4e 5em'];
        yield 'dimension with escaped unit' => ['1\\.5', '1\\.5'];
        yield 'dimension with an e later in its unit' => ['1ae3', '1ae3'];
        yield 'delims' => ['a > b + c ~ d * e / f', 'a > b + c ~ d * e / f'];
        yield 'backslash delim' => ["\\\n", "\\\n "];
        yield 'punctuation' => ['a:b;c,d', 'a:b;c,d'];
        yield 'CDO and CDC' => ['<!-- -->', '<!-- -->'];
        yield 'ident then number' => ['a/**/1', 'a/**/1'];
        yield 'ident then ident' => ['a/**/b', 'a/**/b'];
        yield 'ident then function' => ['a/**/b()', 'a/**/b()'];
        yield 'ident then url' => ['a/**/url(x)', 'a/**/url("x")'];
        yield 'ident then minus' => ['a/**/-', 'a/**/-'];
        yield 'ident then parenthesis' => ['a/**/(b)', 'a/**/(b)'];
        yield 'ident then CDC' => ['a/**/-->', 'a/**/-->'];
        yield 'ident then bracket' => ['a/**/[b]', 'a[b]'];
        yield 'number then ident' => ['1/**/a', '1/**/a'];
        yield 'number then number' => ['1/**/2', '1/**/2'];
        yield 'number then percent' => ['1/**/%', '1/**/%'];
        yield 'number then minus' => ['1/**/-', '1-'];
        yield 'dimension then number' => ['1px/**/2', '1px/**/2'];
        yield 'hash then ident' => ['#a/**/b', '#a/**/b'];
        yield 'at-keyword then dimension' => ['@a/**/1px', '@a/**/1px'];
        yield 'at-keyword then parenthesis' => ['@a/**/(b)', '@a(b)'];
        yield 'hash delim then ident' => ['#/**/a', '#/**/a'];
        yield 'hash delim then CDC' => ['#/**/-->', '#/**/-->'];
        yield 'hash delim then dot' => ['#/**/.', '#.'];
        yield 'minus then ident' => ['-/**/a', '-/**/a'];
        yield 'minus then number' => ['-/**/1', '-/**/1'];
        yield 'minus then minus' => ['-/**/-', '-/**/-'];
        yield 'minus then CDC' => ['-/**/-->', '-/**/-->'];
        yield 'minus then dot' => ['-/**/.', '-.'];
        yield 'at delim then ident' => ['@/**/a', '@/**/a'];
        yield 'at delim then minus' => ['@/**/-', '@/**/-'];
        yield 'at delim then number' => ['@/**/1', '@1'];
        yield 'dot then number' => ['./**/1', './**/1'];
        yield 'dot then ident' => ['./**/a', '.a'];
        yield 'plus then number' => ['+/**/1', '+/**/1'];
        yield 'plus then ident' => ['+/**/a', '+a'];
        yield 'unicode range' => ['U+0000-00FF u+4?? U+a5', 'U+0000-00FF u+4?? U+a5'];
        yield 'ident then signed numbers' => ['a/**/+1 a/**/-1 a/**/+1% a/**/-1px', 'a+1 a/**/-1 a+1% a/**/-1px'];
        yield 'number then signed numbers' => ['1/**/+2 1/**/-2 1/**/-2px 1/**/+2%', '1+2 1-2 1-2px 1+2%'];
        yield 'dimension then signed numbers' => ['1px/**/+2 1px/**/-2 1e/**/+2 1E/**/+2px 1ex/**/+2', '1px+2 1px/**/-2 1e/**/+2 1E/**/+2px 1ex+2'];
        yield 'hash then signed number' => ['#a/**/-1 #a/**/+1', '#a/**/-1 #a+1'];
        yield 'slash then star' => ['//**/*', '//**/*'];
        yield 'slash then slash' => ['//**//', '//'];
        yield 'less-than then slash' => ['</style>', '</**//style>'];
        yield 'less-than then other delims' => ['<= <* <!', '<= <* <!'];
        yield 'less-than in names and strings' => ['\\3c/style \\</a #a\\< #1\\< "</style>" url("</a>")', '\\3c /style \\3c /a #a\\3c  #1\\3c  "\\3c /style>" url("\\3c /a>")'];
        yield 'block then ident' => ['(a)/**/b', '(a)b'];
        yield 'function then ident' => ['f()/**/b', 'f()b'];
        yield 'url then ident' => ['url(a)/**/b', 'url("a")b'];
    }

    #[DataProvider('values')]
    public function testItWritesComponentValuesThatReadBackTheSame(string $css, string $expected): void
    {
        $serializer = new Serializer();
        $written = $serializer->componentValues(new Parser()->parseComponentValues($css));

        self::assertSame($expected, $written);
        self::assertSame($written, $serializer->componentValues(new Parser()->parseComponentValues($written)));
    }

    public function testItEscapesIdentifiers(): void
    {
        self::assertSame('a', Serializer::identifier('a'));
        self::assertSame('\\31 ', Serializer::identifier('1'));
        self::assertSame('-\\31 ', Serializer::identifier('-1'));
        self::assertSame('a1', Serializer::identifier('a1'));
        self::assertSame('--1', Serializer::identifier('--1'));
        self::assertSame('\\-', Serializer::identifier('-'));
        self::assertSame('--', Serializer::identifier('--'));
        self::assertSame('-a', Serializer::identifier('-a'));
        self::assertSame('\\30 a', Serializer::identifier('0a'));
        self::assertSame('\\39 a', Serializer::identifier('9a'));
        self::assertSame('a09', Serializer::identifier('a09'));
        self::assertSame('Ѐ', Serializer::identifier('Ѐ'));
        self::assertSame('-Ѐ', Serializer::identifier('-Ѐ'));
        self::assertSame('', Serializer::identifier(''));
        self::assertSame("\u{FFFD}\\1 \\1f \\7f ", Serializer::identifier("\0\x01\x1F\x7F"));
        self::assertSame('a\\ b\\(\\"\\\\é_-Z9', Serializer::identifier('a b("\\é_-Z9'));
    }

    public function testItEscapesStrings(): void
    {
        self::assertSame('"a\\"b\\\\c\'d"', Serializer::string('a"b\\c\'d'));
        self::assertSame("\"\u{FFFD}\\1 \\a \\1f \\7f é\"", Serializer::string("\0\x01\n\x1F\x7Fé"));
        self::assertSame('""', Serializer::string(''));
        self::assertSame('"Ѐ😀"', Serializer::string('Ѐ😀'));
    }

    public function testItEscapesHashNamesThatAreNotIdentifiers(): void
    {
        $serializer = new Serializer();

        self::assertSame("#1\\ a\\1 \u{FFFD}", $serializer->componentValues([new Token(TokenType::Hash, "1 a\x01\0")]));
        self::assertSame('', $serializer->componentValues([new Token(TokenType::Eof)]));
    }

    public function testItWritesDeclarations(): void
    {
        $serializer = new Serializer();
        $parser = new Parser();
        $declarations = array_values(array_filter($parser->parseDeclarationList('color: red; margin : 0  auto !important; --x: 1; \\31 a: b'), static fn (object $item): bool => $item instanceof Declaration));

        self::assertSame('color: red; margin: 0 auto !important; --x: 1; \\31 a: b', $serializer->declarations($declarations));
        self::assertSame('', $serializer->declarations([]));
    }

    public function testItWritesStyleSheets(): void
    {
        $parser = new Parser();
        $value = static fn (string $css): array => $parser->parseComponentValues($css);
        $declaration = static fn (string $name, string $css, bool $important = false): Declaration => new Declaration($name, $value($css), $important);

        $sheet = new StyleSheet([
            new StyleRule([$value('h1'), $value('p > em')], [$declaration('color', 'red'), $declaration('margin', '0', true)]),
            new GroupRule('media', $value('print'), [], [
                new StyleRule([$value('a')], [$declaration('color', 'blue')]),
                new GroupRule('supports', $value('(display: grid)'), [], [new StyleRule([$value('b')], [$declaration('display', 'grid')])]),
            ]),
            new GroupRule('page', $value(':first'), [$declaration('size', 'A4')], [new GroupRule('top-center', [], [$declaration('content', '"x"')])]),
            new GroupRule('page', [], [], [new GroupRule('bottom-center', [], [$declaration('content', 'counter(page)')])]),
            new GroupRule('font-face', [], [$declaration('font-family', '"X"')]),
            new StyleRule([$value('i')], []),
            new GroupRule('media', $value('screen')),
            new GroupRule('media', $value('print'), [], [new StyleRule([$value('u')], [])]),
        ]);

        self::assertSame(<<<'CSS'
            h1, p > em {
              color: red;
              margin: 0 !important;
            }

            @media print {
              a {
                color: blue;
              }

              @supports (display: grid) {
                b {
                  display: grid;
                }
              }
            }

            @page :first {
              size: A4;

              @top-center {
                content: "x";
              }
            }

            @page {
              @bottom-center {
                content: counter(page);
              }
            }

            @font-face {
              font-family: "X";
            }

            i {}

            @media screen {}

            @media print {
              u {}
            }

            CSS, new Serializer()->styleSheet($sheet));
        self::assertSame('', new Serializer()->styleSheet(new StyleSheet()));
    }

    public function testItWritesImportsFirstAndNestedRules(): void
    {
        $parser = new Parser();
        $value = static fn (string $css): array => $parser->parseComponentValues($css);
        $declaration = static fn (string $name, string $css): Declaration => new Declaration($name, $value($css));

        $sheet = new StyleSheet(
            [new StyleRule([$value('a')], [$declaration('color', 'red')], [
                new StyleRule([$value('& b')], [$declaration('color', 'blue')]),
                new GroupRule('media', $value('print'), [$declaration('color', 'black')]),
            ])],
            [new ImportRule('/a.css'), new ImportRule('/b".css', $value('print'))],
        );

        self::assertSame(<<<'CSS'
            @import url("/a.css");
            @import url("/b\".css") print;

            a {
              color: red;

              & b {
                color: blue;
              }

              @media print {
                color: black;
              }
            }

            CSS, new Serializer()->styleSheet($sheet));
        self::assertSame("@import url(\"/a.css\");\n", new Serializer()->styleSheet(new StyleSheet([], [new ImportRule('/a.css')])));
        self::assertSame("a {\n  & b {}\n}\n", new Serializer()->styleSheet(new StyleSheet([new StyleRule([$value('a')], [], [new StyleRule([$value('& b')], [])])])));
    }
}
