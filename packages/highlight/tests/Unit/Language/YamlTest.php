<?php

declare(strict_types=1);

namespace Phil\Highlight\Tests\Unit\Language;

use Phil\Highlight\Language;
use Phil\Highlight\Language\Yaml;
use Phil\Highlight\Tests\Unit\Tokens;
use Phil\Highlight\Token;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Yaml::class)]
final class YamlTest extends TestCase
{
    public function testItHasItsNames(): void
    {
        self::assertSame('yaml', new Yaml()->name());
        self::assertSame(['yml'], new Yaml()->aliases());
    }

    private function language(): Language
    {
        return new Yaml();
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function sources(): iterable
    {
        yield 'keys, scalars and comments' => ['yaml', "# top\nname: value # c\nurl: http://x#y\ntime: 12:30\nn: 1.5\nb: Yes\nz: ~\ne:\n\"q k\": 'v'\n'r': \"w\\\"x\"",
            "{comment:# top}\n{property:name}{punctuation::} {string:value} {comment:# c}\n{property:url}{punctuation::} {string:http://x#y}\n{property:time}{punctuation::} {string:12:30}\n{property:n}{punctuation::} {number:1.5}\n{property:b}{punctuation::} {constant:Yes}\n{property:z}{punctuation::} {constant:~}\n{property:e}{punctuation::}\n{property:\"q k\"}{punctuation::} {string:'v'}\n{property:'r'}{punctuation::} {string:\"w\\\"x\"}"];
        yield 'documents and sequences' => ['yaml', "---\n- a\n-  - b\n- c: d\n-\n...\n-1\n ---",
            "{meta:---}\n{punctuation:-} {string:a}\n{punctuation:-}  {punctuation:-} {string:b}\n{punctuation:-} {property:c}{punctuation::} {string:d}\n{punctuation:-}\n{meta:...}\n{number:-1}\n {string:---}"];
        yield 'block scalars' => ['yaml', "a: |\n  b\n\n  c\nd: >-\n  e\n f\ng: |2 # c\n  h",
            "{property:a}{punctuation::} {operator:|}\n  {string:b}\n\n  {string:c}\n{property:d}{punctuation::} {operator:>-}\n  {string:e}\n {string:f}\n{property:g}{punctuation::} {operator:|2} {comment:# c}\n  {string:h}"];
        yield 'a block scalar at the end' => ['yaml', 'a: |',
            '{property:a}{punctuation::} {operator:|}'];
        yield 'anchors, aliases and tags' => ['yaml', "a: &x [1, *x, {b: c}]\nd: !!str 1\ne: *x",
            "{property:a}{punctuation::} {variable:&x} {punctuation:[}{number:1}{punctuation:,} {variable:*x}{punctuation:,} {punctuation:{}{string:b: c}{punctuation:}]}\n{property:d}{punctuation::} {meta:!!str} {number:1}\n{property:e}{punctuation::} {variable:*x}"];
        yield 'flow collections' => ['yaml', "a: [b, 'c', [d]] # e\nf: {g: h, i: [j]}\nk: l, m\nn: ]",
            "{property:a}{punctuation::} {punctuation:[}{string:b}{punctuation:,} {string:'c'}{punctuation:,} {punctuation:[}{string:d}{punctuation:]]} {comment:# e}\n{property:f}{punctuation::} {punctuation:{}{string:g: h}{punctuation:,} {string:i:} {punctuation:[}{string:j}{punctuation:]}}\n{property:k}{punctuation::} {string:l, m}\n{property:n}{punctuation::} {string:]}"];
        yield 'keys that are not keys' => ['yaml', "a:b\n[x]: y\n\"open: z\nk#: v\n: w\n'q' x: y",
            "{string:a:b}\n{punctuation:[}{string:x}{punctuation:]}{string:: y}\n{string:\"open: z}\n{string:k#: v}\n{string:: w}\n{string:'q'} {string:x: y}"];
        yield 'a key at the end' => ['yaml', 'a:',
            '{property:a}{punctuation::}'];
        yield 'a quoted key followed by spaces' => ['yaml', '"a"  : b',
            '{property:"a"}  {punctuation::} {string:b}'];
        yield 'a hash inside a plain scalar' => ['yaml', "a: b#c #d\ne: #f",
            "{property:a}{punctuation::} {string:b#c} {comment:#d}\n{property:e}{punctuation::} {comment:#f}"];
        yield 'a block scalar holds what looks like keys' => ['yaml', "a: |\n  b: c # d\ne: f",
            "{property:a}{punctuation::} {operator:|}\n  {string:b: c # d}\n{property:e}{punctuation::} {string:f}"];
        yield 'an empty quoted key' => ['yaml', '"": x',
            '{property:""}{punctuation::} {string:x}'];
        yield 'a key holding colons' => ['yaml', "a:b: c\na:: c",
            "{property:a:b}{punctuation::} {string:c}\n{property:a:}{punctuation::} {string:c}"];
        yield 'spaces before the colon of a key' => ['yaml', 'a  : b',
            '{property:a}  {punctuation::} {string:b}'];
        yield 'a quoted text that is not a key' => ['yaml', "\"a\":b\n\"c\" d e",
            "{string:\"a\":b}\n{string:\"c\"} {string:d e}"];
        yield 'flow collections that close' => ['yaml', "a: [b] c, d\ne: {f} g, h\ni: [j] [k, l]",
            "{property:a}{punctuation::} {punctuation:[}{string:b}{punctuation:]} {string:c, d}\n{property:e}{punctuation::} {punctuation:{}{string:f}{punctuation:}} {string:g, h}\n{property:i}{punctuation::} {punctuation:[}{string:j}{punctuation:]} {punctuation:[}{string:k}{punctuation:,} {string:l}{punctuation:]}"];
        yield 'a hash at the end of a scalar' => ['yaml', "a: b#\nc: d",
            "{property:a}{punctuation::} {string:b#}\n{property:c}{punctuation::} {string:d}"];
        yield 'a quote that does not close on its line' => ['yaml', "\"a\n: b",
            "{string:\"a}\n{string:: b}"];
    }

    #[DataProvider('sources')]
    public function testItTokenizes(string $language, string $source, string $expected): void
    {
        $tokens = $this->language()->tokenize($source);

        self::assertSame($expected, Tokens::render($tokens));
        self::assertSame($source, implode('', array_map(static fn (Token $token): string => $token->text, $tokens)));
    }
}
