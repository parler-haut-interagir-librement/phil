<?php

declare(strict_types=1);

namespace Phil\Markup\Tests\Unit\Adapter;

use Dom\Attr;
use Dom\Element;
use Dom\HTMLDocument;
use Dom\HTMLElement;
use Generator;
use Phil\Markup\Adapter\AmmoniaPurifier;
use Phil\Markup\Adapter\CodeClasses;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\HtmlPurifier;
use Phil\Markup\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/**
 * The same hostile HTML given to each purifier, at each level: known XSS vectors, mutation XSS, address schemes,
 * elements that act on the page and deep trees. The output is judged on properties, not bytes — nothing that runs,
 * nothing outside the level, the same once purified again — and both purifiers must keep the same elements. The
 * ammonia purifier is tested where its extension is loaded, as in the `bench-ext` image of `phil/dev-tools`; under
 * PHP 8.6, for which it is not built, only the sanitizer runs.
 */
#[CoversClass(SanitizerPurifier::class)]
#[CoversClass(AmmoniaPurifier::class)]
#[CoversClass(CodeClasses::class)]
final class PurifierCorpusTest extends TestCase
{
    /** The schemes that run code or carry a document, in any case, after the characters a browser ignores. */
    private const string DANGEROUS = '/^(?:javascript|vbscript|data|livescript|mocha):/i';

    /**
     * @return Generator<string, array{string}>
     */
    public static function hostile(): Generator
    {
        yield 'script' => ['<script>alert(1)</script><p>a</p>'];
        yield 'event handler' => ['<img src=x onerror=alert(1)><b onmouseover="alert(1)">b</b>'];
        yield 'svg handler' => ['<svg onload=alert(1)><circle r="1"/></svg>'];
        yield 'svg script' => ['<svg><script>alert(1)</script><a xlink:href="javascript:alert(1)"><text>x</text></a></svg>'];
        yield 'javascript link' => ['<a href="javascript:alert(1)">a</a><a href="JaVaScRiPt:alert(1)">b</a>'];
        yield 'entity in scheme' => ['<a href="&#106;avascript:alert(1)">a</a><a href="jav&#x09;ascript:alert(1)">b</a><a href="&#14; javascript:alert(1)">c</a>'];
        yield 'other schemes' => ['<a href="vbscript:msgbox(1)">a</a><a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">b</a><img src="data:image/svg+xml,<svg onload=alert(1)>">'];
        yield 'frames' => ['<iframe src="javascript:alert(1)"></iframe><iframe srcdoc="<script>alert(1)</script>"></iframe><frameset><frame src="x"></frameset>'];
        yield 'plugins' => ['<object data="javascript:alert(1)"></object><embed src="javascript:alert(1)"><applet code="x"></applet>'];
        yield 'forms' => ['<form action="javascript:alert(1)"><input autofocus onfocus=alert(1)><button formaction="javascript:alert(1)">b</button><select><option>o</option></select><textarea><script>alert(1)</script></textarea></form>'];
        yield 'math mxss' => ['<math><mtext><table><mglyph><style><img src=x onerror=alert(1)>'];
        yield 'noscript mxss' => ['<noscript><p title="</noscript><img src=x onerror=alert(1)>"></noscript>'];
        yield 'svg style mxss' => ['<svg></p><style><a id="</style><img src=1 onerror=alert(1)>">'];
        yield 'nested forms mxss' => ['<form><math><mtext></form><form><mglyph><svg><mtext><textarea><path id="</textarea><img onerror=alert(1) src=1>">'];
        yield 'styles' => ['<style>@import "javascript:alert(1)";</style><div style="background:url(javascript:alert(1))">d</div><p style="position:fixed">p</p>'];
        yield 'page' => ['<base href="javascript:/"><meta http-equiv="refresh" content="0;url=javascript:alert(1)"><link rel="stylesheet" href="https://example.com/a.css"><title><script>alert(1)</script></title>'];
        yield 'inputs' => ['<ul><li><input type="checkbox" checked onclick=alert(1) name=x value=y form=f>a</li></ul><input type="text" value="password" autofocus onfocus=alert(1)><input><input type="image" src="javascript:alert(1)"><input type="CHECKBOX" formaction="javascript:alert(1)"><label for=x><input type=hidden name=csrf>l</label>'];
        yield 'toggle' => ['<details open ontoggle=alert(1)><summary>s</summary>t</details>'];
        yield 'template' => ['<template><script>alert(1)</script><img src=x onerror=alert(1)></template>'];
        yield 'comments' => ['<!--<img src=x onerror=alert(1)>--><![CDATA[<script>alert(1)</script>]]><!--><img src=x onerror=alert(1)>-->'];
        yield 'raw text' => ['<xmp><script>alert(1)</script></xmp><plaintext><img src=x onerror=alert(1)>'];
        yield 'media addresses' => ['<video poster="javascript:alert(1)"><source src="javascript:alert(1)"></video><img srcset="javascript:alert(1) 1x"><blockquote cite="javascript:alert(1)">q</blockquote>'];
        yield 'mathml link' => ['<math href="javascript:alert(1)"><mi xlink:href="javascript:alert(1)">x</mi></math>'];
        yield 'broken tags' => ['<<script>script>alert(1)<</script>/script><img src="x` `<script>alert(1)</script>"` `><a href="x'];
        yield 'clobbering' => ['<img name="cookie" id="x"><form name="body"></form><a id="location" name="location">a</a>'];
        yield 'not utf-8' => ["<b>a\xff\xfe<script>alert(1)</script></b><img src=x onerror=alert(1)>"];
        yield 'deep divs' => [str_repeat('<div>', 2_000).'x'];
        yield 'deep inline' => [str_repeat('<b><i>', 2_500).'x'];
        yield 'deep lists' => [str_repeat('<ul><li>', 1_000).'x'];
        yield 'deep quotes' => [str_repeat('<blockquote>', 2_000).'<script>alert(1)</script>'];
    }

    /**
     * @return Generator<string, array{string, Level}>
     */
    public static function hostileAtEachLevel(): Generator
    {
        foreach (self::hostile() as $name => [$html]) {
            foreach (Level::cases() as $level) {
                yield $name.' at '.$level->value => [$html, $level];
            }
        }
    }

    #[DataProvider('hostileAtEachLevel')]
    public function testTheSanitizerNeutralises(string $html, Level $level): void
    {
        $this->assertNeutralised(SanitizerPurifier::defaultPolicies(), $html, $level);
    }

    #[DataProvider('hostileAtEachLevel')]
    #[RequiresPhpExtension('ammonia')]
    public function testTheExtensionNeutralises(string $html, Level $level): void
    {
        $this->assertNeutralised(AmmoniaPurifier::defaultPolicies(minimumBytes: 0), $html, $level);
    }

    #[DataProvider('hostileAtEachLevel')]
    #[RequiresPhpExtension('ammonia')]
    public function testBothKeepTheSameElements(string $html, Level $level): void
    {
        self::assertSame(
            $this->elements(SanitizerPurifier::defaultPolicies()->purify($html, $level)),
            $this->elements(AmmoniaPurifier::defaultPolicies(minimumBytes: 0)->purify($html, $level)),
        );
    }

    private function assertNeutralised(HtmlPurifier $purifier, string $html, Level $level): void
    {
        $once = $purifier->purify($html, $level);
        $twice = $purifier->purify($once, $level);
        $allowed = array_keys(SanitizerPurifier::configurations()[$level->value]->getAllowedElements());

        foreach ([$once, $twice] as $output) {
            foreach ($this->body($output)->getElementsByTagName('*') as $element) {
                self::assertContains(strtolower($element->localName), [...$allowed, 'tbody'], $output);
                $this->assertInert($element, $output);
                $this->assertNoField($element, $output);
            }
        }

        self::assertSame($this->elements($once), $this->elements($twice), 'Purified again, the HTML keeps its elements.');
    }

    private function assertInert(Element $element, string $output): void
    {
        foreach ($element->attributes as $attribute) {
            $name = strtolower($attribute->localName);
            self::assertStringStartsNotWith('on', $name, $output);
            self::assertNotContains($name, ['style', 'srcdoc', 'action', 'formaction'], $output);
            $value = (string) preg_replace('/[\x00-\x20]+/', '', $attribute->value);
            self::assertDoesNotMatchRegularExpression(self::DANGEROUS, $value, $output);
        }
    }

    /**
     * An input is only ever the disabled checkbox of a checklist: nothing a reader could type in or submit.
     */
    private function assertNoField(Element $element, string $output): void
    {
        if ('input' === strtolower($element->localName)) {
            self::assertSame('checkbox', $element->getAttribute('type'), $output);
            self::assertTrue($element->hasAttribute('disabled'), $output);
            self::assertSame(\array_slice(['type', 'disabled', 'checked'], 0, $element->attributes->length), array_values(array_map(static fn (Attr $attribute): string => $attribute->localName, iterator_to_array($element->attributes))), $output);
        }
    }

    /**
     * The names of the elements of an HTML, in the order they open.
     *
     * @return list<string>
     */
    private function elements(string $html): array
    {
        $names = [];

        foreach ($this->body($html)->getElementsByTagName('*') as $element) {
            $names[] = strtolower($element->localName);
        }

        return $names;
    }

    private function body(string $html): HTMLElement
    {
        $body = HTMLDocument::createFromString('<!doctype html><html><body>'.$html, \LIBXML_NOERROR)->body;
        self::assertNotNull($body);

        return $body;
    }
}
