<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Tests\Unit\Rule;

use Phil\AccessibilityCheck\ContrastLevel;
use Phil\AccessibilityCheck\Policy;
use Phil\AccessibilityCheck\Rule\ColorContrast;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(ColorContrast::class)]
final class ColorContrastTest extends RuleTestCase
{
    public function testTheRuleIsDescribed(): void
    {
        self::assertSame(['color-contrast', SuccessCriterion::ContrastMinimum, Severity::Error], [new ColorContrast()->id(), new ColorContrast()->criterion(), new ColorContrast()->severity()]);
    }

    public function testATextColouredByTheContentStandsOut(): void
    {
        self::assertSame(
            ['color-contrast.insufficient @ p:nth-of-type(1) (text=Pale, ratio=2.84, foreground=#999999, background=#ffffff, required=4.5) #1.4.3'],
            self::html(new ColorContrast(), '<p style="color: #999">Pale</p><p style="color: #595959">Dark enough</p><p>Not coloured</p>'),
        );
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function contents(): iterable
    {
        yield 'background only' => ['<p style="background-color: #222">Text</p>', ['p (text=Text, ratio=1.31, foreground=#000000, background=#222222, required=4.5)']];
        yield 'inherited colour' => ['<div style="color: #aaa"><p>Inner <b>bold</b></p></div>', ['div > p (text=Inner, ratio=2.32, foreground=#aaaaaa, background=#ffffff, required=4.5)', 'div > p > b (text=bold, ratio=2.32, foreground=#aaaaaa, background=#ffffff, required=4.5)']];
        yield 'inherit keyword' => ['<div style="color: #aaa"><p style="color: inherit">x</p></div>', ['div > p (text=x, ratio=2.32, foreground=#aaaaaa, background=#ffffff, required=4.5)']];
        yield 'font colour' => ['<font color="yellow">Yellow</font>', ['font (text=Yellow, ratio=1.07, foreground=#ffff00, background=#ffffff, required=4.5)']];
        yield 'bgcolor' => ['<table><tr><td bgcolor="black">Cell</td></tr></table>', ['table > tbody > tr > td (text=Cell, ratio=1.00, foreground=#000000, background=#000000, required=4.5)']];
        yield 'background shorthand' => ['<p style="background: navy; color: #000">x</p>', ['p (text=x, ratio=1.31, foreground=#000000, background=#000080, required=4.5)']];
        yield 'translucent background' => ['<div style="background-color: #000"><p style="background-color: rgb(255 255 255 / 50%); color: #fff">x</p></div>', ['div > p (text=x, ratio=3.97, foreground=#ffffff, background=#808080, required=4.5)']];
        yield 'translucent foreground' => ['<p style="color: rgb(0 0 0 / 30%)">x</p>', ['p (text=x, ratio=2.10, foreground=#b3b3b3, background=#ffffff, required=4.5)']];
        yield 'translucent background over the policy' => ['<p style="background-color: rgba(0, 0, 0, 0.8)">x</p>', ['p (text=x, ratio=1.66, foreground=#000000, background=#333333, required=4.5)']];
        yield 'image behind' => ['<p style="color: #eee; background-image: url(a.png)">x</p>', []];
        yield 'no image behind' => ['<p style="color: #eee; background-image: none">x</p>', ['p (text=x, ratio=1.16, foreground=#eeeeee, background=#ffffff, required=4.5)']];
        yield 'gradient behind' => ['<div style="background: linear-gradient(red, blue)"><p style="color: #eee">x</p></div>', []];
        yield 'url behind' => ['<p style="color: #eee; background: url(a.png) red">x</p>', []];
        yield 'unknown foreground' => ['<p style="color: var(--x)">x</p>', []];
        yield 'unknown background' => ['<p style="color: #eee; background-color: var(--x)">x</p>', []];
        yield 'inherited background' => ['<div style="background-color: #000"><p style="background-color: inherit; color: #111">x</p></div>', ['div > p (text=x, ratio=1.11, foreground=#111111, background=#000000, required=4.5)']];
        yield 'hidden' => ['<p style="color: #eee" hidden>x</p>', []];
        yield 'no own text' => ['<p style="color: #eee"> <b style="color: #000">x</b> </p>', []];
        yield 'large text' => ['<p style="color: #777; font-size: 24px">x</p><p style="color: #777; font-size: 23.9px">y</p>', ['p:nth-of-type(2) (text=y, ratio=4.47, foreground=#777777, background=#ffffff, required=4.5)']];
        yield 'large bold text' => ['<p style="color: #777; font-size: 14pt; font-weight: bold">x</p><p style="color: #777; font-size: 14pt; font-weight: 600">y</p>', ['p:nth-of-type(2) (text=y, ratio=4.47, foreground=#777777, background=#ffffff, required=4.5)']];
        yield 'bolder and numeric weights' => ['<p style="color: #777; font-size: 1.2em"><b>x</b><span style="font-weight: bolder">y</span><span style="font-weight: 700">z</span><span style="font-weight: normal">w</span></p>', ['p > span:nth-of-type(3) (text=w, ratio=4.47, foreground=#777777, background=#ffffff, required=4.5)']];
        yield 'sizes in rem and percent' => ['<p style="color: #777; font-size: 1.5rem">x</p><p style="color: #777; font-size: 150%">y</p><p style="color: #777; font-size: 1.4rem">z</p>', ['p:nth-of-type(3) (text=z, ratio=4.47, foreground=#777777, background=#ffffff, required=4.5)']];
        yield 'unknown size' => ['<p style="color: #777; font-size: large">x</p>', ['p (text=x, ratio=4.47, foreground=#777777, background=#ffffff, required=4.5)']];
        yield 'headings' => ['<div style="color: #777"><h1>a</h1><h2>b</h2><h3>c</h3><h4>d</h4><h3 style="font-weight: normal">e</h3><h2><span>f</span></h2></div>', ['div > h4 (text=d, ratio=4.47, foreground=#777777, background=#ffffff, required=4.5)', 'div > h3:nth-of-type(2) (text=e, ratio=4.47, foreground=#777777, background=#ffffff, required=4.5)']];
        yield 'small headings' => ['<div style="color: #777"><h5>a</h5><h6>b</h6></div>', ['div > h5 (text=a, ratio=4.47, foreground=#777777, background=#ffffff, required=4.5)', 'div > h6 (text=b, ratio=4.47, foreground=#777777, background=#ffffff, required=4.5)']];
        yield 'bold cell' => ['<table style="color: #777"><tr><th style="font-size: 19px">a</th><td style="font-size: 19px"><strong>b</strong></td><td style="font-size: 19px">c</td></tr></table>', ['table > tbody > tr > td:nth-of-type(2) (text=c, ratio=4.47, foreground=#777777, background=#ffffff, required=4.5)']];
        yield 'uncomputable before' => ['<p style="color: var(--x)">a</p><p style="color: #eee">b</p>', ['p:nth-of-type(2) (text=b, ratio=1.16, foreground=#eeeeee, background=#ffffff, required=4.5)']];
        yield 'background-color before the shorthand' => ['<p style="background: #fff; background-color: #000; color: #111">x</p>', ['p (text=x, ratio=1.11, foreground=#111111, background=#000000, required=4.5)']];
        yield 'shorthand before bgcolor' => ['<table><tr><td bgcolor="white" style="background: black; color: #111">x</td></tr></table>', ['table > tbody > tr > td (text=x, ratio=1.11, foreground=#111111, background=#000000, required=4.5)']];
        yield 'opaque background hides what is behind' => ['<div style="background-image: url(a.png)"><p style="background-color: #000; color: #111">x</p></div>', ['div > p (text=x, ratio=1.11, foreground=#111111, background=#000000, required=4.5)']];
        yield 'large bold at the bound' => ['<p style="color: #777; font-size: 18.66px; font-weight: bold">x</p><p style="color: #777; font-size: 18.65px; font-weight: bold">y</p>', ['p:nth-of-type(2) (text=y, ratio=4.47, foreground=#777777, background=#ffffff, required=4.5)']];
        yield 'normal sizes in pt and percent' => ['<p style="color: #777; font-size: 17pt">x</p><p style="color: #777; font-size: 100%">y</p><p style="color: #777; font-size: 18pt">z</p>', ['p:nth-of-type(1) (text=x, ratio=4.47, foreground=#777777, background=#ffffff, required=4.5)', 'p:nth-of-type(2) (text=y, ratio=4.47, foreground=#777777, background=#ffffff, required=4.5)']];
        yield 'translucent over an image' => ['<div style="background-image: url(a.png)"><p style="background-color: rgba(0, 0, 0, 0.5); color: #111">x</p></div>', []];
        yield 'long text' => ['<p style="color: #eee">'.str_repeat('a', 50).'</p>', ['p (text='.str_repeat('a', 39).'…, ratio=1.16, foreground=#eeeeee, background=#ffffff, required=4.5)']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('contents')]
    public function testTheColoursOfTheContentAreRead(string $html, array $expected): void
    {
        self::assertSame(
            array_map(static fn (string $line): string => 'color-contrast.insufficient @ '.$line.' #1.4.3', $expected),
            array_values(array_filter(self::html(new ColorContrast(), $html), static fn (string $line): bool => str_starts_with($line, 'color-contrast.insufficient'))),
        );
    }

    public function testAColourOfOklchIsComputed(): void
    {
        self::assertSame(
            ['color-contrast.insufficient @ p:nth-of-type(1) (text=Pale, ratio=2.67, foreground=#9e9e9e, background=#ffffff, required=4.5) #1.4.3'],
            self::html(new ColorContrast(), '<p style="color: oklch(70% 0 0)">Pale</p><p style="color: oklch(0.3 0.05 260)">Dark</p><p style="background-color: oklch(20% 0.02 260); color: oklch(98% 0.01 90)">Light on dark</p>'),
        );
    }

    public function testAColourOfTheStyleSheetsIsANotice(): void
    {
        self::assertSame(
            [
                'color-contrast.unresolved @ p:nth-of-type(1) (value=var(--text)) !notice',
                'color-contrast.unresolved @ div (value=color-mix(in oklch, red 40%, white)) !notice',
                'color-contrast.unresolved @ section (value=var(--panel) url(a.png)) !notice',
                'color-contrast.unresolved @ aside (value=var(--'.str_repeat('x', 53).'…) !notice',
            ],
            self::html(new ColorContrast(), '<p style="color: var(--text)">a</p><div style="background-color: color-mix(in oklch, red 40%, white)"><p style="color: #000">b</p></div><section style="background: var(--panel) url(a.png)">c</section><p style="color: var(--x)" hidden>d</p><p style="color: var(--x)"> </p><aside style="color: var(--'.str_repeat('x', 60).')">e</aside>'),
        );
    }

    public function testThePolicyGivesTheDefaultColoursAndTheLevel(): void
    {
        $policy = new Policy(contrast: ContrastLevel::AAA, foreground: '#595959', background: '#fafafa');

        self::assertSame(
            ['color-contrast.insufficient @ p:nth-of-type(1) (text=x, ratio=6.71, foreground=#595959, background=#fafafa, required=7) #1.4.6'],
            self::html(new ColorContrast(), '<p style="background-color: #fafafa">x</p><p style="color: #000">y</p>', $policy),
        );
        self::assertSame(
            ['color-contrast.insufficient @ p (text=x, ratio=2.99, foreground=#000000, background=#595959, required=4.5) #1.4.3'],
            self::html(new ColorContrast(), '<p style="color: #000">x</p>', new Policy(background: '#595959')),
        );
    }

    public function testATextTheContentDoesNotColourIsTheLooks(): void
    {
        self::assertSame([], self::html(new ColorContrast(), '<p>x</p>', new Policy(foreground: '#eeeeee')));
    }

    public function testTheTokensOfTheLookColourUjoTexts(): void
    {
        $policy = new Policy()->withColourTokens(['pale' => '#cccccc', 'dark' => '#222']);

        self::assertSame(
            ['color-contrast.insufficient @ blocks[0].data.text span:nth-of-type(1) (text=pale, ratio=1.60, foreground=#cccccc, background=#ffffff, required=4.5) #1.4.3'],
            self::ujo(new ColorContrast(), [['id' => 'p', 'type' => 'paragraph', 'data' => ['text' => '<span class="text-color" data-color="pale">pale</span> <span class="text-color" data-color="dark">dark</span> <span class="text-color" data-color="unknown">unknown</span>']]], $policy),
        );
        self::assertSame([], self::ujo(new ColorContrast(), [['id' => 'p', 'type' => 'paragraph', 'data' => ['text' => '<span class="text-color" data-color="pale">pale</span>']]]));
    }
}
