<?php

declare(strict_types=1);

namespace Phil\Highlight\Tests\Unit\Language;

use Phil\Highlight\Language;
use Phil\Highlight\Language\StyleSheet;
use Phil\Highlight\Tests\Unit\Tokens;
use Phil\Highlight\Token;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(StyleSheet::class)]
final class StyleSheetTest extends TestCase
{
    public function testItHasItsNames(): void
    {
        self::assertSame('css', $this->language('css')->name());
        self::assertSame('css', new StyleSheet()->name());
        self::assertSame([], $this->language('css')->aliases());
        self::assertSame('scss', $this->language('scss')->name());
        self::assertSame(['less', 'sass'], $this->language('scss')->aliases());
    }

    private function language(string $name): Language
    {
        return new StyleSheet('scss' === $name);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function sources(): iterable
    {
        yield 'selectors' => ['css', 'a:hover, .b > c + d ~ e, #f[g="h"] {}',
            '{selector:a:hover}{punctuation:,} {selector:.b} {operator:>} {selector:c} {operator:+} {selector:d} {operator:~} {selector:e}{punctuation:,} {selector:#f[g=}{string:"h"}{selector:]} {punctuation:{}}'];
        yield 'a slash in a selector' => ['css', 'a/b {}',
            '{selector:a}/{selector:b} {punctuation:{}}'];
        yield 'declarations' => ['css', 'a { color: #fff; margin: -1.5em .5px 0 10%; --x: 1; width: calc(100% - var(--x)) !important }',
            '{selector:a} {punctuation:{} {property:color}{punctuation::} {number:#fff}{punctuation:;} {property:margin}{punctuation::} {number:-1.5em} {number:.5px} {number:0} {number:10%}{punctuation:;} {variable:--x}{punctuation::} {number:1}{punctuation:;} {property:width}{punctuation::} {function:calc}{punctuation:(}{number:100%} - {function:var}{punctuation:(}{variable:--x}{punctuation:))} {keyword:!important} {punctuation:}}'];
        yield 'functions and plain words' => ['css', 'a { font: bold 12px/1.5 "A B", serif; b: rgb(1, 2, 3); c: a+b*c=d<e>f; d: @x }',
            '{selector:a} {punctuation:{} {property:font}{punctuation::} bold {number:12px}{operator:/}{number:1.5} {string:"A B"}{punctuation:,} serif{punctuation:;} {property:b}{punctuation::} {function:rgb}{punctuation:(}{number:1}{punctuation:,} {number:2}{punctuation:,} {number:3}{punctuation:);} {property:c}{punctuation::} a{operator:+}b{operator:*}c{operator:=}d{operator:<}e{operator:>}f{punctuation:;} {property:d}{punctuation::} @x {punctuation:}}'];
        yield 'addresses' => ['css', 'a { b: url(http://x/y.png); c: url("z"); d: URL( q); e: url() }',
            '{selector:a} {punctuation:{} {property:b}{punctuation::} {function:url}{punctuation:(}{string:http://x/y.png}{punctuation:);} {property:c}{punctuation::} {function:url}{punctuation:(}{string:"z"}{punctuation:);} {property:d}{punctuation::} {function:URL}{punctuation:(} q{punctuation:);} {property:e}{punctuation::} {function:url}{punctuation:()} {punctuation:}}'];
        yield 'at-rules' => ['css', "@import 'a.css';\n@media (min-width: 600px) and print { a { b: c } }",
            "{keyword:@import} {string:'a.css'}{punctuation:;}\n{keyword:@media} {punctuation:(}min-width{punctuation::} {number:600px}{punctuation:)} and print {punctuation:{} {selector:a} {punctuation:{} {property:b}{punctuation::} c {punctuation:}} {punctuation:}}"];
        yield 'comments' => ['css', "/* a */ b /* c */ { d: e; /* f */ }\n// g",
            "{comment:/* a */} {selector:b} {comment:/* c */} {punctuation:{} {property:d}{punctuation::} e{punctuation:;} {comment:/* f */} {punctuation:}}\n// {selector:g}"];
        yield 'a property without a name' => ['css', 'a { *zoom: 1; }',
            '{selector:a} {punctuation:{} *{property:zoom}{punctuation::} {number:1}{punctuation:;} {punctuation:}}'];
        yield 'a stray closing brace' => ['css', '} a { b: c }',
            '{punctuation:}} {selector:a} {punctuation:{} {property:b}{punctuation::} c {punctuation:}}'];
        yield 'a string at a statement start' => ['css', '"a" b {}',
            '{string:"a"} {selector:b} {punctuation:{}}'];
        yield 'scss variables, comments and interpolations' => ['scss', "\$x: 1px; // a\n.#{\$b} { width: \$x; }",
            "{variable:\$x}{punctuation::} {number:1px}{punctuation:;} {comment:// a}\n{selector:.}{variable:#{\$b}} {punctuation:{} {property:width}{punctuation::} {variable:\$x}{punctuation:;} {punctuation:}}"];
        yield 'scss nesting' => ['scss', '.a { color: red; &:hover { color: blue; } .b { c: d } e: f }',
            '{selector:.a} {punctuation:{} {property:color}{punctuation::} red{punctuation:;} {selector:&:hover} {punctuation:{} {property:color}{punctuation::} blue{punctuation:;} {punctuation:}} {selector:.b} {punctuation:{} {property:c}{punctuation::} d {punctuation:}} {property:e}{punctuation::} f {punctuation:}}'];
        yield 'a declaration at the end of a block' => ['scss', '.a { b: c }',
            '{selector:.a} {punctuation:{} {property:b}{punctuation::} c {punctuation:}}'];
        yield 'a scss variable inside a block' => ['scss', '.a { $y: 2; }',
            '{selector:.a} {punctuation:{} {variable:$y}{punctuation::} {number:2}{punctuation:;} {punctuation:}}'];
        yield 'scss at-rules' => ['scss', "@mixin m(\$a) { b: \$a; }\n.c { @include m(1); }",
            "{keyword:@mixin} {function:m}{punctuation:(}{variable:\$a}{punctuation:)} {punctuation:{} {property:b}{punctuation::} {variable:\$a}{punctuation:;} {punctuation:}}\n{selector:.c} {punctuation:{} {keyword:@include} {function:m}{punctuation:(}{number:1}{punctuation:);} {punctuation:}}"];
        yield 'an interpolation alone' => ['scss', '#{$a} b {}',
            '{variable:#{$a}} {selector:b} {punctuation:{}}'];
        yield 'a hash before a block in css' => ['css', 'a#{}',
            '{selector:a#}{punctuation:{}}'];
        yield 'an at sign inside a value' => ['scss', '.a { b: c@d; }',
            '{selector:.a} {punctuation:{} {property:b}{punctuation::} c@d{punctuation:;} {punctuation:}}'];
        yield 'a word that starts like an address' => ['css', 'a { b: urlx,y }',
            '{selector:a} {punctuation:{} {property:b}{punctuation::} urlx{punctuation:,}y {punctuation:}}'];
        yield 'an at sign before a number in a value' => ['css', 'a { b: @1 }',
            '{selector:a} {punctuation:{} {property:b}{punctuation::} @{number:1} {punctuation:}}'];
    }

    #[DataProvider('sources')]
    public function testItTokenizes(string $language, string $source, string $expected): void
    {
        $tokens = $this->language($language)->tokenize($source);

        self::assertSame($expected, Tokens::render($tokens));
        self::assertSame($source, implode('', array_map(static fn (Token $token): string => $token->text, $tokens)));
    }
}
