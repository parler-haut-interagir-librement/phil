<?php

declare(strict_types=1);

namespace Phil\Highlight\Tests\Unit\Language;

use Phil\Highlight\Language\Code;
use Phil\Highlight\Language\Definitions;
use Phil\Highlight\Language\Syntax;
use Phil\Highlight\Tests\Unit\Tokens;
use Phil\Highlight\Token;
use Phil\Highlight\TokenType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Definitions::class)]
final class DefinitionsTest extends TestCase
{
    /**
     * @return iterable<string, array{Syntax}>
     */
    public static function syntaxes(): iterable
    {
        yield 'php' => [Definitions::php()];
        yield 'javascript' => [Definitions::javascript()];
        yield 'typescript' => [Definitions::typescript()];
        yield 'json' => [Definitions::json()];
        yield 'python' => [Definitions::python()];
        yield 'ruby' => [Definitions::ruby()];
        yield 'go' => [Definitions::go()];
        yield 'rust' => [Definitions::rust()];
        yield 'java' => [Definitions::java()];
        yield 'c' => [Definitions::c()];
        yield 'cpp' => [Definitions::cpp()];
        yield 'csharp' => [Definitions::csharp()];
        yield 'sql' => [Definitions::sql()];
        yield 'bash' => [Definitions::bash()];
        yield 'twigExpression' => [Definitions::twigExpression()];
    }

    /**
     * Every word of every list is read as its list says, alone: no word is in two lists.
     */
    #[DataProvider('syntaxes')]
    public function testEachWordIsReadAsItsList(Syntax $syntax): void
    {
        $code = new Code($syntax);

        foreach ([[$syntax->keywords, TokenType::Keyword], [$syntax->types, TokenType::Type], [$syntax->constants, TokenType::Constant], [$syntax->builtins, TokenType::Builtin]] as [$words, $type]) {
            foreach ($words as $word) {
                self::assertEquals([new Token($word, $type)], $code->tokenize($word), $syntax->name.': '.$word);
            }
        }
    }

    /**
     * @return iterable<string, array{Syntax, string, string}>
     */
    public static function sources(): iterable
    {
        yield 'php' => [Definitions::php(), "<?php #[Route] // a\n# b\n/* c */ \$x = \"d\ne\" . 'f\ng' ?><?= Foo::bar(\$y) ?>",
            "{meta:<?php} {meta:#[}{type:Route}{punctuation:]} {comment:// a}\n{comment:# b}\n{comment:/* c */} {variable:\$x} {operator:=} {string:\"d\ne\"} {punctuation:.} {string:'f\ng'} {meta:?><?=} {type:Foo}{operator:::}{function:bar}{punctuation:(}{variable:\$y}{punctuation:)} {meta:?>}"];
        yield 'javascript' => [Definitions::javascript(), "#!/usr/bin/env node\n@dec class A { m() { return /x/g.test(`a\nb`) && \"c\" + 'd' + \$el; } } // e\n/* f */",
            "{meta:#!/usr/bin/env node}\n{meta:@dec} {keyword:class} {type:A} {punctuation:{} {function:m}{punctuation:()} {punctuation:{} {keyword:return} {regex:/x/g}{punctuation:.}{function:test}{punctuation:(}{string:`a\nb`}{punctuation:)} {operator:&&} {string:\"c\"} {operator:+} {string:'d'} {operator:+} \$el{punctuation:;} {punctuation:}} {punctuation:}} {comment:// e}\n{comment:/* f */}"];
        yield 'typescript' => [Definitions::typescript(), "#!/usr/bin/env node\n@dec class A implements I { m(x: string): void { return /x/g.test(`a\nb`) && \"c\" + 'd' + \$el + undefined; } } // e\n/* f */ console",
            "{meta:#!/usr/bin/env node}\n{meta:@dec} {keyword:class} {type:A} {keyword:implements} {type:I} {punctuation:{} {function:m}{punctuation:(}x{operator::} {type:string}{punctuation:)}{operator::} {keyword:void} {punctuation:{} {keyword:return} {regex:/x/g}{punctuation:.}{function:test}{punctuation:(}{string:`a\nb`}{punctuation:)} {operator:&&} {string:\"c\"} {operator:+} {string:'d'} {operator:+} \$el {operator:+} {constant:undefined}{punctuation:;} {punctuation:}} {punctuation:}} {comment:// e}\n{comment:/* f */} {builtin:console}"];
        yield 'json' => [Definitions::json(), "{\"a\": [1, -2, +3, true], \"b\": \"c\" // d\n/* e */}",
            "{punctuation:{}{property:\"a\"}{punctuation::} {punctuation:[}{number:1}{punctuation:,} {operator:-}{number:2}{punctuation:,} {operator:+}{number:3}{punctuation:,} {constant:true}{punctuation:],} {property:\"b\"}{punctuation::} {string:\"c\"} {comment:// d}\n{comment:/* e */}{punctuation:}}"];
        yield 'python' => [Definitions::python(), "#!/usr/bin/env python\n@dataclass\nclass A:\n    \"\"\"doc\n    s\"\"\"\n    x: int = '''a\nb''' + f\"c\" + 'd' # e\n    y = a @ b if True else print(None)",
            "{meta:#!/usr/bin/env python}\n{meta:@dataclass}\n{keyword:class} {type:A}{operator::}\n    {string:\"\"\"doc\n    s\"\"\"}\n    x{operator::} {type:int} {operator:=} {string:'''a\nb'''} {operator:+} {string:f\"c\"} {operator:+} {string:'d'} {comment:# e}\n    y {operator:=} a {operator:@} b {keyword:if} {constant:True} {keyword:else} {builtin:print}{punctuation:(}{constant:None}{punctuation:)}"];
        yield 'ruby' => [Definitions::ruby(), "#!/usr/bin/env ruby\n=begin\nx\n=end\nclass A < B\n  def empty?() = @a.nil? || \$b =~ /c/ ? :d : \"e\n#{f}\" + 'g\nh' + `i\nj` # k\nend",
            "{meta:#!/usr/bin/env ruby}\n{comment:=begin\nx\n=end}\n{keyword:class} {type:A} {operator:<} {type:B}\n  {keyword:def} {function:empty?}{punctuation:()} {operator:=} {variable:@a}{punctuation:.}nil? {operator:||} {variable:\$b} {operator:=~} {regex:/c/} {operator:?} {constant::d} {operator::} {string:\"e\n#{f}\"} {operator:+} {string:'g\nh'} {operator:+} {string:`i\nj`} {comment:# k}\n{keyword:end}"];
        yield 'go' => [Definitions::go(), "func f() error { s := `a\\\nb` + \"c\" + string('d'); return nil } // e\n/* f */ len(x)",
            "{keyword:func} {function:f}{punctuation:()} {type:error} {punctuation:{} s {operator::=} {string:`a\\\nb`} {operator:+} {string:\"c\"} {operator:+} {type:string}{punctuation:(}{string:'d'}{punctuation:);} {keyword:return} {constant:nil} {punctuation:}} {comment:// e}\n{comment:/* f */} {builtin:len}{punctuation:(}x{punctuation:)}"];
        yield 'rust' => [Definitions::rust(), "#![allow(x)]\n#[derive(Debug)]\nfn f<'a>(x: &'a str) -> Option<u8> { let s = \"a\nb\"; let c = 'c'; let b = b\"d\"; println!(\"{}\", r\"e\"); None } // f\n/* g */",
            "{meta:#![}{function:allow}{punctuation:(}x{punctuation:)]}\n{meta:#[}{function:derive}{punctuation:(}{type:Debug}{punctuation:)]}\n{keyword:fn} f{operator:<}'a{operator:>}{punctuation:(}x{operator::} {operator:&}'a {type:str}{punctuation:)} {operator:->} {type:Option}{operator:<}{type:u8}{operator:>} {punctuation:{} {keyword:let} s {operator:=} {string:\"a\nb\"}{punctuation:;} {keyword:let} c {operator:=} {string:'c'}{punctuation:;} {keyword:let} b {operator:=} {string:b\"d\"}{punctuation:;} {function:println!}{punctuation:(}{string:\"{}\"}{punctuation:,} {string:r\"e\"}{punctuation:);} {constant:None} {punctuation:}} {comment:// f}\n{comment:/* g */}"];
        yield 'java' => [Definitions::java(), "@Override\npublic String f() { var s = \"\"\"\n  a\n  \"\"\"; char c = 'c'; return \"d\" + null; } // e\n/* f */",
            "{meta:@Override}\n{keyword:public} {type:String} {function:f}{punctuation:()} {punctuation:{} {keyword:var} s {operator:=} {string:\"\"\"\n  a\n  \"\"\"}{punctuation:;} {type:char} c {operator:=} {string:'c'}{punctuation:;} {keyword:return} {string:\"d\"} {operator:+} {constant:null}{punctuation:;} {punctuation:}} {comment:// e}\n{comment:/* f */}"];
        yield 'c' => [Definitions::c(), "#include <stdio.h>\nint main(void) { char c = 'a'; wchar_t *s = L\"b\"; char *t = u8\"c\"; return NULL; } // d\n/* e */",
            "{meta:#include <stdio.h>}\n{type:int} {function:main}{punctuation:(}{type:void}{punctuation:)} {punctuation:{} {type:char} c {operator:=} {string:'a'}{punctuation:;} wchar_t {operator:*}s {operator:=} {string:L\"b\"}{punctuation:;} {type:char} {operator:*}t {operator:=} {string:u8\"c\"}{punctuation:;} {keyword:return} {constant:NULL}{punctuation:;} {punctuation:}} {comment:// d}\n{comment:/* e */}"];
        yield 'cpp' => [Definitions::cpp(), "#include <vector>\ntemplate <typename T> class A { std::string s = u8\"a\"; char c = 'b'; auto p = nullptr; }; // c\n/* d */",
            "{meta:#include <vector>}\n{keyword:template} {operator:<}{keyword:typename} T{operator:>} {keyword:class} A {punctuation:{} std{operator:::}{type:string} s {operator:=} {string:u8\"a\"}{punctuation:;} {type:char} c {operator:=} {string:'b'}{punctuation:;} {keyword:auto} p {operator:=} {constant:nullptr}{punctuation:;} {punctuation:};} {comment:// c}\n{comment:/* d */}"];
        yield 'csharp' => [Definitions::csharp(), "#region R\n[Obsolete] public class A { string s = @\"a\\\"; string t = \$\"b\"; string u = \"\"\"\nc\n\"\"\"; char d = 'd'; object o = null; } // e\n/* f */",
            "{meta:#region R}\n{punctuation:[}{type:Obsolete}{punctuation:]} {keyword:public} {keyword:class} {type:A} {punctuation:{} {type:string} s {operator:=} {string:@\"a\\\"}{punctuation:;} {type:string} t {operator:=} {string:\$\"b\"}{punctuation:;} {type:string} u {operator:=} {string:\"\"\"\nc\n\"\"\"}{punctuation:;} {type:char} d {operator:=} {string:'d'}{punctuation:;} {type:object} o {operator:=} {constant:null}{punctuation:;} {punctuation:}} {comment:// e}\n{comment:/* f */}"];
        yield 'sql' => [Definitions::sql(), "SELECT a::int, 'it''s\nx\\' AS \"b\"\"c\", `d\\`, @v, \$1 FROM t WHERE x IS NULL; -- e\n/* f */ count(*)",
            "{keyword:SELECT} a{operator:::}{type:int}{punctuation:,} {string:'it''s\nx\\'} {keyword:AS} {string:\"b\"\"c\"}{punctuation:,} {string:`d\\`}{punctuation:,} {variable:@v}{punctuation:,} {variable:\$1} {keyword:FROM} t {keyword:WHERE} x {keyword:IS} {constant:NULL}{punctuation:;} {comment:-- e}\n{comment:/* f */} {builtin:count}{punctuation:(}{operator:*}{punctuation:)}"];
        yield 'bash' => [Definitions::bash(), "#!/bin/bash\necho \"a\n\$b\" 'c\\' `d\ne` \${f} \$# x#y # z\nif [ -f a ] && b; then c | d > e; fi",
            "{meta:#!/bin/bash}\n{builtin:echo} {string:\"a\n\$b\"} {string:'c\\'} {string:`d\ne`} {variable:\${f}} {variable:\$#} x#y {comment:# z}\n{keyword:if} {punctuation:[} -f a {punctuation:]} {operator:&&} b{operator:;} {keyword:then} c {operator:|} d {operator:>} e{operator:;} {keyword:fi}"];
        yield 'twigExpression' => [Definitions::twigExpression(), " a|upper ~ \"b\nc\" ~ 'd\ne' is defined and x[0].y(1, none) ",
            " a{operator:|}{function:upper} {operator:~} {string:\"b\nc\"} {operator:~} {string:'d\ne'} {keyword:is} {keyword:defined} {keyword:and} x{punctuation:[}{number:0}{punctuation:].}{function:y}{punctuation:(}{number:1}{punctuation:,} {constant:none}{punctuation:)} "];
    }

    #[DataProvider('sources')]
    public function testItReadsEachLanguage(Syntax $syntax, string $source, string $expected): void
    {
        self::assertSame($expected, Tokens::of(new Code($syntax), $source));
    }
}
