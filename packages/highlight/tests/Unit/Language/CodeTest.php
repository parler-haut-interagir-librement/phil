<?php

declare(strict_types=1);

namespace Phil\Highlight\Tests\Unit\Language;

use Phil\Highlight\Language\Code;
use Phil\Highlight\Language\Quote;
use Phil\Highlight\Language\Syntax;
use Phil\Highlight\Tests\Unit\Tokens;
use Phil\Highlight\Token;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Code::class)]
#[CoversClass(Syntax::class)]
#[CoversClass(Quote::class)]
final class CodeTest extends TestCase
{
    public function testItHasTheNameAndAliasesOfItsSyntax(): void
    {
        $code = new Code(new Syntax('lang', aliases: ['l']));

        self::assertSame('lang', $code->name());
        self::assertSame(['l'], $code->aliases());
        self::assertSame([], $code->tokenize(''));
    }

    /**
     * @return iterable<string, array{Syntax, string, string}>
     */
    public static function sources(): iterable
    {
        $plain = new Syntax('plain');
        yield 'spaces, words, operators, punctuation and other characters' => [$plain, "a  +=\tb;\n@ (x)", 'a  {operator:+=}	b{punctuation:;}
@ {punctuation:(}x{punctuation:)}'];
        yield 'a multibyte word' => [$plain, 'été = 1', 'été {operator:=} {number:1}'];

        $words = new Syntax('words', keywords: ['if', 'both'], types: ['int', 'both', 'twice'], constants: ['true', 'both', 'twice', 'thrice'], builtins: ['echo', 'both', 'twice', 'thrice']);
        yield 'each list, the keywords first, then constants, types and built-ins' => [$words, 'if int true echo both twice thrice other', '{keyword:if} {type:int} {constant:true} {builtin:echo} {keyword:both} {constant:twice} {constant:thrice} other'];
        yield 'a call, spaces and tabs before the parenthesis' => [$words, "f(x) g \t(y) h\n(z) echo(1)", "{function:f}{punctuation:(}x{punctuation:)} {function:g} \t{punctuation:(}y{punctuation:)} h\n{punctuation:(}z{punctuation:)} {builtin:echo}{punctuation:(}{number:1}{punctuation:)}"];
        yield 'words in their case' => [$words, 'IF If if', 'IF If {keyword:if}'];
        yield 'words in any case' => [new Syntax('sql', keywords: ['select'], caseInsensitive: true), 'SELECT Select select x', '{keyword:SELECT} {keyword:Select} {keyword:select} x'];
        yield 'capitals make types' => [new Syntax('java', keywords: ['If'], capitalizedTypes: true), 'Foo foo _Bar If Ébc', '{type:Foo} foo _Bar {keyword:If} Ébc'];
        yield 'capitals are plain otherwise' => [$plain, 'Foo', 'Foo'];
        yield 'a call after a sign' => [new Syntax('twig', callAfter: '|'), 'a|upper|e(1) |b c', 'a{operator:|}{function:upper}{operator:|}{function:e}{punctuation:(}{number:1}{punctuation:)} {operator:|}{function:b} c'];
        yield 'no call after a sign otherwise' => [$plain, 'a|b', 'a{operator:|}b'];
        yield 'macros' => [new Syntax('rust', keywords: ['if'], macros: true), 'vec![1] a != b c!=d if!x e!', '{function:vec!}{punctuation:[}{number:1}{punctuation:]} a {operator:!=} b c{operator:!=}d {keyword:if}{operator:!}x {function:e!}'];
        yield 'no macros otherwise' => [$plain, 'vec![1]', 'vec{operator:!}{punctuation:[}{number:1}{punctuation:]}'];
        yield 'word suffixes' => [new Syntax('ruby', keywords: ['defined?'], wordSuffixes: '?!'), 'a? b! c!=d defined? !e ?', 'a? b! c{operator:!=}d {keyword:defined?} {operator:!}e {operator:?}'];
        yield 'no word suffixes otherwise' => [$plain, 'a?', 'a{operator:?}'];
        yield 'listed words in any case' => [new Syntax('sql', keywords: ['SELECT'], caseInsensitive: true), 'select', '{keyword:select}'];
        yield 'a call holding word characters' => [new Syntax('js', wordChars: '$'), 'a$b(', '{function:a$b}{punctuation:(}'];
        yield 'capitals only' => [new Syntax('java', capitalizedTypes: true), 'FOO', '{type:FOO}'];
        yield 'three prefix letters' => [new Syntax('py', quotes: [new Quote('"')], stringPrefixes: 'rbf'), 'rbf"c"', '{string:rbf"c"}'];
        yield 'characters of two, three and four bytes' => [new Syntax('rust', quotes: [new Quote("'", character: true)]), "'€' '\u{0800}' '\xC0\x80' '\xF0\x9F\x98\x80'", "{string:'€'} {string:'\u{0800}'} {string:'\xC0\x80'} {string:'\xF0\x9F\x98\x80'}"];
        yield 'escaped slashes in a regular expression' => [new Syntax('js', regex: true), '= /\/\//g', '{operator:=} {regex:/\/\//g}'];
        yield 'more word characters' => [new Syntax('js', wordChars: '$'), '$a b$ $', '$a b$ $'];

        yield 'numbers' => [$plain, '0x1F 1_000u32 1.5 .5 1e-3 1E+3 2e 0xE+1 1. 1..2 3.x 4-5', '{number:0x1F} {number:1_000u32} {number:1.5} {number:.5} {number:1e-3} {number:1E+3} {number:2e} {number:0xE}{operator:+}{number:1} {number:1}{punctuation:.} {number:1}{punctuation:..}{number:2} {number:3}{punctuation:.}x {number:4}{operator:-}{number:5}'];
        yield 'an exponent sign at the end' => [$plain, '2e-', '{number:2e-}'];
        yield 'a hexadecimal in capitals' => [$plain, '0XE-1', '{number:0XE}{operator:-}{number:1}'];

        yield 'meta marks before anything' => [new Syntax('php', lineComments: ['#'], metaMarks: ['<?php', '#[']), "<?php #[A] # c\n<?", "{meta:<?php} {meta:#[}A{punctuation:]} {comment:# c}\n{operator:<?}"];

        $c = new Syntax('c', lineComments: ['//'], preprocessor: true);
        yield 'preprocessor lines' => [$c, "#include <a>\n  #define X \\\n  1\nx # y\n#if", "{meta:#include <a>}\n  {meta:#define X \\\n  1}\nx # y\n{meta:#if}"];
        yield 'a preprocessor line continued at the end' => [$c, '#define X \\', '{meta:#define X \\}'];
        yield 'no preprocessor otherwise' => [$plain, '#if', '#if'];

        yield 'line and block comments' => [new Syntax('c', lineComments: ['//', '--'], blockComments: ['/*' => '*/', '(*' => '*)']), "a // b\n/* c\nd */e -- f\n(* g *)/*/ h", "a {comment:// b}\n{comment:/* c\nd */}e {comment:-- f}\n{comment:(* g *)/*/ h}"];
        yield 'a block comment that opens like a line comment' => [new Syntax('lua', lineComments: ['--'], blockComments: ['--[[' => ']]']), "--[[ a\nb ]] c -- d", "{comment:--[[ a\nb ]]} c {comment:-- d}"];
        yield 'comments at the start of a word' => [new Syntax('bash', lineComments: ['#'], commentAfterSpace: true), "# a\nb#c d #e\n\t#f", "{comment:# a}\nb#c d {comment:#e}\n\t{comment:#f}"];
        yield 'comments anywhere otherwise' => [new Syntax('py', lineComments: ['#']), 'b#c', 'b{comment:#c}'];

        $quotes = new Syntax('q', quotes: [new Quote('"""', multiline: true), new Quote('"'), new Quote("'", escape: '', multiline: true), new Quote('`', '´')]);
        yield 'quotes' => [$quotes, "\"a\\\"b\" \"\"\"c\n\"d\"\"\" 'e''f\ng' `h´", "{string:\"a\\\"b\"} {string:\"\"\"c\n\"d\"\"\"} {string:'e''f\ng'} {string:`h´}"];
        yield 'a quote that cannot span lines stops at the line feed' => [$quotes, "\"a\nb", "{string:\"a}\nb"];
        yield 'a quote open to the end' => [$quotes, '"a\\', '{string:"a\\}'];
        yield 'a doubled quote' => [$quotes, "'a''b' c", "{string:'a''b'} c"];
        yield 'a mark longer than one character' => [new Syntax('q', quotes: [new Quote('<<', '>>')]), '<<a>b>> c', '{string:<<a>b>>} c'];
        yield 'prefixed quotes' => [new Syntax('py', quotes: [new Quote('"')], stringPrefixes: 'rbf'), 'f"a" rb"b" rbfr"c" br x"d"', '{string:f"a"} {string:rb"b"} rbfr{string:"c"} br x{string:"d"}'];
        yield 'no prefix otherwise' => [new Syntax('js', quotes: [new Quote('"')]), 'f"a"', 'f{string:"a"}'];

        $chars = new Syntax('rust', quotes: [new Quote('"'), new Quote("'", character: true)]);
        yield 'character literals' => [$chars, "'a' 'é' '😀' '\\n' '\\u{1F600}' '\\''", "{string:'a'} {string:'é'} {string:'😀'} {string:'\\n'} {string:'\\u{1F600}'} {string:'\\''}"];
        yield 'lifetimes are not character literals' => [$chars, "<'a, 'b> &'static ''", "{operator:<}'a{punctuation:,} 'b{operator:>} {operator:&}'static ''"];
        yield 'a lone quote at the end' => [$chars, "x '", "x '"];

        $json = new Syntax('json', quotes: [new Quote('"')], propertyKeys: true, operators: '-', punctuation: '{}:,');
        yield 'keys' => [$json, '{"a": "b", "c" :1, "d"'."\t:".'"e"}', '{punctuation:{}{property:"a"}{punctuation::} {string:"b"}{punctuation:,} {property:"c"} {punctuation::}{number:1}{punctuation:,} {property:"d"}'."\t".'{punctuation::}{string:"e"}{punctuation:}}'];
        yield 'no keys otherwise' => [new Syntax('js', quotes: [new Quote('"')]), '"a": 1', '{string:"a"}{operator::} {number:1}'];

        $shell = new Syntax('bash', variablePrefixes: '$', specialVariables: '#?');
        yield 'variables' => [$shell, '$a ${b c} $# $? $! $ $$ $1x', '{variable:$a} {variable:${b c}} {variable:$#} {variable:$?} {operator:$!} {operator:$} {variable:$$} {variable:$1x}'];
        yield 'two sigils' => [new Syntax('ruby', variablePrefixes: '@$'), '@a @@b $c @', '{variable:@a} {variable:@@b} {variable:$c} {operator:@}'];

        $python = new Syntax('python', annotation: '@');
        yield 'annotations' => [$python, "@app.route(1)\na @ b @1 @", "{meta:@app.route}{punctuation:(}{number:1}{punctuation:)}\na @ b {meta:@1} @"];
        yield 'a longer annotation sign' => [new Syntax('x', annotation: '@@'), '@@a @b', '{meta:@@a} @b'];

        $ruby = new Syntax('ruby', symbols: true);
        yield 'symbols' => [$ruby, ':a, b: :c a::B x:y ::d :1 :', '{constant::a}{punctuation:,} b{operator::} {constant::c} a{operator:::}B x{operator::}y {operator:::}d {constant::1} {operator::}'];
        yield 'a symbol first' => [$ruby, ':a', '{constant::a}'];
        yield 'no symbols otherwise' => [$plain, ':a', '{operator::}a'];

        $js = new Syntax('js', keywords: ['return'], quotes: [new Quote('"')], regex: true);
        yield 'regular expressions where a value is expected' => [$js, '/a/g; x = /b[/]\/c/; return /d/i; f(/e/)', '{regex:/a/g}{punctuation:;} x {operator:=} {regex:/b[/]\/c/}{punctuation:;} {keyword:return} {regex:/d/i}{punctuation:;} {function:f}{punctuation:(}{regex:/e/}{punctuation:)}'];
        yield 'a division after a value' => [$js, 'a / b / c; (1) / 2; x[0] / 3; {} / 4; "s" / 5; 6 / 7', 'a {operator:/} b {operator:/} c{punctuation:;} {punctuation:(}{number:1}{punctuation:)} {operator:/} {number:2}{punctuation:;} x{punctuation:[}{number:0}{punctuation:]} {operator:/} {number:3}{punctuation:;} {punctuation:{}} {operator:/} {number:4}{punctuation:;} {string:"s"} {operator:/} {number:5}{punctuation:;} {number:6} {operator:/} {number:7}'];
        yield 'a regular expression that does not close' => [$js, "= /a\nb", "{operator:=} {operator:/}a\nb"];
        yield 'a regular expression open to the end' => [$js, '= /a', '{operator:=} {operator:/}a'];
        yield 'an escape at the end' => [$js, '= /a\\', '{operator:=} {operator:/}a\\'];
        yield 'a class that holds the closing slash' => [$js, '= /[a/]x/', '{operator:=} {regex:/[a/]x/}'];
        yield 'a class closed then a slash' => [$js, '= /[a]/x', '{operator:=} {regex:/[a]/x}'];
        yield 'no regular expressions otherwise' => [$plain, '/a/', '{operator:/}a{operator:/}'];

        yield 'a shebang' => [new Syntax('sh', lineComments: ['#'], shebang: true), "#!/bin/sh\n#!x", "{meta:#!/bin/sh}\n{comment:#!x}"];
        yield 'no shebang otherwise' => [new Syntax('x', lineComments: ['#']), '#!/bin/sh', '{comment:#!/bin/sh}'];
        yield 'a shebang only first' => [new Syntax('sh', shebang: true), ' #!/bin/sh', ' #{operator:!/}bin{operator:/}sh'];

        yield 'chosen operators and punctuation' => [new Syntax('x', operators: '|', punctuation: ';'), 'a|b;c+d', 'a{operator:|}b{punctuation:;}c+d'];
    }

    #[DataProvider('sources')]
    public function testItTokenizes(Syntax $syntax, string $source, string $expected): void
    {
        $tokens = new Code($syntax)->tokenize($source);

        self::assertSame($expected, Tokens::render($tokens));
        self::assertSame($source, implode('', array_map(static fn (Token $token): string => $token->text, $tokens)));
    }
}
