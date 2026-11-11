<?php

declare(strict_types=1);

namespace Phil\Highlight\Tests\Unit\Language;

use Phil\Highlight\Language;
use Phil\Highlight\Language\Code;
use Phil\Highlight\Language\Definitions;
use Phil\Highlight\Language\Markup;
use Phil\Highlight\Language\StyleSheet;
use Phil\Highlight\Tests\Unit\Tokens;
use Phil\Highlight\Token;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Markup::class)]
final class MarkupTest extends TestCase
{
    public function testItHasItsNames(): void
    {
        self::assertSame('html', $this->language('html')->name());
        self::assertSame(['htm', 'xhtml', 'vue', 'svelte'], $this->language('html')->aliases());
        self::assertSame('xml', $this->language('xml')->name());
        self::assertSame(['svg', 'xsl', 'xslt', 'xsd', 'rss', 'atom', 'plist', 'wsdl', 'xaml', 'mathml'], $this->language('xml')->aliases());
        self::assertSame([], new Markup('x')->aliases());
    }

    private function language(string $name): Language
    {
        return 'html' === $name ? Markup::html(new Code(Definitions::javascript()), new StyleSheet()) : Markup::xml();
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function sources(): iterable
    {
        yield 'text, references and lone signs' => ['html', 'a &amp; b &#38; &x c & d &; e < f <1',
            'a {entity:&amp;} b {entity:&#38;} &x c & d &; e < f <1'];
        yield 'comments, doctypes, instructions' => ['html', "<!DOCTYPE html><!-- a\nb --><?xml v?><!-- open",
            "{meta:<!DOCTYPE html>}{comment:<!-- a\nb -->}{meta:<?xml v?>}{comment:<!-- open}"];
        yield 'tags and attributes' => ['html', "<a href=\"x\" title='y' data-z=w disabled>t</a><br/><img src=a/><p\n  class=\"c\">",
            "{punctuation:<}{tag:a} {attr-name:href}{punctuation:=}{string:\"x\"} {attr-name:title}{punctuation:=}{string:'y'} {attr-name:data-z}{punctuation:=}{string:w} {attr-name:disabled}{punctuation:>}t{punctuation:</}{tag:a}{punctuation:><}{tag:br}{punctuation:/><}{tag:img} {attr-name:src}{punctuation:=}{string:a/}{punctuation:><}{tag:p}\n  {attr-name:class}{punctuation:=}{string:\"c\"}{punctuation:>}"];
        yield 'odd attributes' => ['html', '<a b="c"d / =e <f>',
            '{punctuation:<}{tag:a} {attr-name:b}{punctuation:=}{string:"c"}{attr-name:d} / {punctuation:=}{string:e} <{attr-name:f}{punctuation:>}'];
        yield 'an attribute quoted to the end' => ['html', '<a b="c',
            '{punctuation:<}{tag:a} {attr-name:b}{punctuation:=}{string:"c}'];
        yield 'a tag open to the end' => ['html', '<a b',
            '{punctuation:<}{tag:a} {attr-name:b}'];
        yield 'embedded script and style' => ['html', '<script type="module">if (a < b) x = /c/;</script><STYLE>a { b: c }</style>',
            '{punctuation:<}{tag:script} {attr-name:type}{punctuation:=}{string:"module"}{punctuation:>}{keyword:if} {punctuation:(}a {operator:<} b{punctuation:)} x {operator:=} {regex:/c/}{punctuation:;</}{tag:script}{punctuation:><}{tag:STYLE}{punctuation:>}{selector:a} {punctuation:{} {property:b}{punctuation::} c {punctuation:}</}{tag:style}{punctuation:>}'];
        yield 'an embedded element that does not close' => ['html', '<script>let a',
            '{punctuation:<}{tag:script}{punctuation:>}{keyword:let} a'];
        yield 'an end tag in any case' => ['html', '<script>a</SCRIPT>b',
            '{punctuation:<}{tag:script}{punctuation:>}a{punctuation:</}{tag:SCRIPT}{punctuation:>}b'];
        yield 'a self-closed embedded element' => ['html', '<script src="a"/>let b',
            '{punctuation:<}{tag:script} {attr-name:src}{punctuation:=}{string:"a"}{punctuation:/>}let b'];
        yield 'an end tag is not embedded' => ['html', '</script>let b',
            '{punctuation:</}{tag:script}{punctuation:>}let b'];
        yield 'cdata' => ['xml', '<a><![CDATA[x<y]]></a><![CDATA[open',
            '{punctuation:<}{tag:a}{punctuation:>}{meta:<![CDATA[}x<y{meta:]]>}{punctuation:</}{tag:a}{punctuation:>}{meta:<![CDATA[}open'];
        yield 'xml has no embedded languages' => ['xml', '<script>if</script>',
            '{punctuation:<}{tag:script}{punctuation:>}if{punctuation:</}{tag:script}{punctuation:>}'];
        yield 'names with colons and dots' => ['xml', '<x:a.b-c y:z="1"/>',
            '{punctuation:<}{tag:x:a.b-c} {attr-name:y:z}{punctuation:=}{string:"1"}{punctuation:/>}'];
        yield 'a lone sign before a tag' => ['html', '<<a>',
            '<{punctuation:<}{tag:a}{punctuation:>}'];
        yield 'a reference before a tag' => ['html', '&amp;<b>',
            '{entity:&amp;}{punctuation:<}{tag:b}{punctuation:>}'];
        yield 'a lone ampersand before a tag' => ['html', '&<b>',
            '&{punctuation:<}{tag:b}{punctuation:>}'];
        yield 'an embedded element holds other end tags' => ['html', '<script>a</b></script>',
            '{punctuation:<}{tag:script}{punctuation:>}a{operator:</}b{operator:>}{punctuation:</}{tag:script}{punctuation:>}'];
    }

    #[DataProvider('sources')]
    public function testItTokenizes(string $language, string $source, string $expected): void
    {
        $tokens = $this->language($language)->tokenize($source);

        self::assertSame($expected, Tokens::render($tokens));
        self::assertSame($source, implode('', array_map(static fn (Token $token): string => $token->text, $tokens)));
    }
}
