<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Tests\Unit\Html;

use Dom\Element;
use Phil\AccessibilityCheck\Exception\InvalidContent;
use Phil\AccessibilityCheck\Exception\LimitExceeded;
use Phil\AccessibilityCheck\Html\HtmlTarget;
use Phil\AccessibilityCheck\Location;
use Phil\AccessibilityCheck\Policy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(HtmlTarget::class)]
#[CoversClass(LimitExceeded::class)]
#[CoversClass(InvalidContent::class)]
final class HtmlTargetTest extends TestCase
{
    public function testAContentAtTheLimitsIsRead(): void
    {
        $target = HtmlTarget::parse('<p>ab</p>', new Policy(maxBytes: 9, maxDepth: 3, maxElements: 4));

        self::assertCount(4, $target->elements());
    }

    public function testAContentLargerThanThePolicyIsRefusedBeforeItIsParsed(): void
    {
        $this->expectExceptionObject(LimitExceeded::size(10, 9));
        $this->expectExceptionMessageIsOrContains('The content holds 10 bytes; at most 9 are checked.');

        HtmlTarget::parse('<p>abc</p>', new Policy(maxBytes: 9));
    }

    public function testElementsNestedTooDeepAreRefused(): void
    {
        $this->expectExceptionObject(LimitExceeded::depth(3));
        $this->expectExceptionMessageIsOrContains('The content nests deeper than 3 levels.');

        HtmlTarget::parse('<p><b>x</b></p>', new Policy(maxDepth: 3));
    }

    public function testTooManyElementsAreRefused(): void
    {
        $this->expectExceptionObject(LimitExceeded::count(4));
        $this->expectExceptionMessageIsOrContains('The content holds more than 4 elements or blocks.');

        HtmlTarget::parse('<p>a</p><p>b</p>', new Policy(maxElements: 4));
    }

    public function testAContentThatIsNotUtf8IsRefused(): void
    {
        $this->expectExceptionObject(InvalidContent::notUtf8());
        $this->expectExceptionMessageIsOrContains('The content is not valid UTF-8.');

        HtmlTarget::parse("<p>\xFF</p>", new Policy());
    }

    /**
     * @return iterable<array{string, bool}>
     */
    public static function documents(): iterable
    {
        yield 'doctype' => ['<!DOCTYPE html><title>t</title>', true];
        yield 'html' => ["\n  <html lang=\"fr\"><p>x</p></html>", true];
        yield 'comment first' => ["<!-- a -->\n<!-- b --><!doctype html>", true];
        yield 'byte order mark' => ["\xEF\xBB\xBF<!doctype html>", true];
        yield 'fragment' => ['<p>x</p><html>', false];
        yield 'text first' => ['x<!doctype html>', false];
        yield 'htmlx' => ['<htmlx>', false];
    }

    #[DataProvider('documents')]
    public function testAWholeDocumentIsToldFromAFragment(string $html, bool $whole): void
    {
        self::assertSame($whole, HtmlTarget::parse($html, new Policy())->isFullDocument);
    }

    public function testTheElementsAreIndexedInDocumentOrder(): void
    {
        $target = HtmlTarget::parse('<div id="a"><p>1</p><span id="a">2</span></div><p id="">3</p>', new Policy(), 'fr');

        self::assertSame(['html', 'head', 'body', 'div', 'p', 'span', 'p'], $this->namesOf($target->elements()));
        self::assertSame(['1', '3'], array_map(static fn (Element $element): string => $element->textContent ?? '', $target->elements('p')));
        self::assertSame([], $target->elements('table'));
        self::assertSame(['span', 'p', 'p'], $this->namesOf($target->elements('span', 'p')));
        self::assertSame(['a'], array_keys($target->identifiers()));
        self::assertSame(['div', 'span'], $this->namesOf($target->identifiers()['a']));
        self::assertSame('fr', $target->language);
        self::assertSame(['svg', 'title'], $this->namesOf(HtmlTarget::parse('<svg><TITLE>t</TITLE></svg>', new Policy())->elements('svg', 'title')));
    }

    public function testAnElementIsLocatedByLinePathAndOpeningTag(): void
    {
        $target = HtmlTarget::parse("<div>\n<p>a</p>\n<p>b <a href=\"/x\" title=\"T&quot;\">c</a></p></div><span>d</span>", new Policy());
        $link = $target->elements('a')[0];

        self::assertFalse($target->isUjoField());
        self::assertEquals(new Location(3, 'div > p:nth-of-type(2) > a', '<a href="/x" title="T&quot;">'), $target->locate($link));
        self::assertSame('span', $target->locate($target->elements('span')[0])->element);
        self::assertSame('body', $target->locate($target->elements('body')[0])->element);
        self::assertSame('html', $target->locate($target->elements('html')[0])->element);
        self::assertSame(1, $target->locate($target->elements('div')[0])->line);
    }

    public function testThePositionIsWrittenAmongElementsOfTheName(): void
    {
        $target = HtmlTarget::parse('<p>a</p><span>x</span><p>b</p><i>y</i><p>c</p><b>z</b>', new Policy());

        self::assertSame(['p:nth-of-type(1)', 'p:nth-of-type(2)', 'p:nth-of-type(3)'], array_map(static fn (Element $element): ?string => $target->locate($element)->element, $target->elements('p')));
        self::assertSame(['span', 'i', 'b'], array_map(static fn (Element $element): ?string => $target->locate($element)->element, $target->elements('span', 'i', 'b')));
    }

    public function testTheOpeningTagIsCut(): void
    {
        $value = str_repeat('é', 70);
        $target = HtmlTarget::parse('<img alt="'.$value.'" src="'.$value.'" title="'.$value.'">', new Policy());
        $snippet = $target->locate($target->elements('img')[0])->snippet;

        self::assertSame('<img alt="'.str_repeat('é', 59).'…" src="'.str_repeat('é', 59).'…" title="'.str_repeat('é', 13).'…', $snippet);
        self::assertSame(160, mb_strwidth($snippet));
    }

    public function testAFieldOfAUjoBlockIsLocatedInTheBlock(): void
    {
        $origin = new Location(block: 'b1', blockType: 'paragraph', pointer: 'blocks[0].data.text');
        $target = HtmlTarget::parse('<a href="#">x</a>', new Policy(), null, $origin);

        self::assertTrue($target->isUjoField());
        self::assertEquals(new Location(null, 'a', '<a href="#">', 'b1', 'paragraph', 'blocks[0].data.text'), $target->locate($target->elements('a')[0]));
    }

    public function testTheLanguageOfAnElementIsTheNearestOne(): void
    {
        $target = HtmlTarget::parse('<div lang=" de "><p><b>x</b></p></div><p lang=""><i>y</i></p><s>z</s><u lang="  ">w</u>', new Policy(), 'fr');

        self::assertNull($target->languageOf($target->elements('u')[0]));

        self::assertSame('de', $target->languageOf($target->elements('b')[0]));
        self::assertNull($target->languageOf($target->elements('i')[0]));
        self::assertSame('fr', $target->languageOf($target->elements('s')[0]));
        self::assertNull(HtmlTarget::parse('<s>z</s>', new Policy())->languageOf(HtmlTarget::parse('<s>z</s>', new Policy())->elements('s')[0]));
    }

    /**
     * @return iterable<array{string, bool}>
     */
    public static function visibility(): iterable
    {
        yield 'shown' => ['<b id="t">x</b>', false];
        yield 'hidden' => ['<div hidden><b id="t">x</b></div>', true];
        yield 'aria-hidden' => ['<div aria-hidden=" TRUE "><b id="t">x</b></div>', true];
        yield 'aria-hidden false' => ['<div aria-hidden="false"><b id="t">x</b></div>', false];
        yield 'display none' => ['<div style="color: red; DISPLAY : none"><b id="t">x</b></div>', true];
        yield 'display block' => ['<div style="display: block"><b id="t">x</b></div>', false];
        yield 'visibility hidden' => ['<b id="t" style="visibility:hidden">x</b>', true];
        yield 'visibility collapse' => ['<b id="t" style="visibility: collapse">x</b>', true];
        yield 'visibility visible' => ['<b id="t" style="visibility: visible">x</b>', false];
        yield 'script' => ['<script id="t">x</script>', true];
        yield 'style' => ['<style id="t">x</style>', true];
        yield 'template' => ['<b><noscript id="t">x</noscript></b>', true];
        yield 'head' => ['<!doctype html><head><title id="t">x</title></head>', true];
    }

    #[DataProvider('visibility')]
    public function testAnElementIsHiddenByItselfOrAnAncestor(string $html, bool $hidden): void
    {
        $target = HtmlTarget::parse($html, new Policy());

        self::assertSame($hidden, $target->isHidden($target->identifiers()['t'][0]));
    }

    public function testATemplateIsHidden(): void
    {
        $target = HtmlTarget::parse('<template><b>x</b></template>', new Policy());

        self::assertTrue($target->isHidden($target->elements('template')[0]));
        self::assertSame([], $target->elements('b'));
    }

    public function testTheInlineStyleIsReadByProperty(): void
    {
        $target = HtmlTarget::parse('<p style=" Color : RED ; ;background:url(a;b); broken">x</p><b>y</b>', new Policy());

        self::assertSame(['color' => 'red', 'background' => 'url(a'], $target->style($target->elements('p')[0]));
        self::assertSame([], $target->style($target->elements('b')[0]));
    }

    public function testTheFirstRoleCounts(): void
    {
        $target = HtmlTarget::parse('<b role=" Button  link">x</b><i>y</i>', new Policy());

        self::assertTrue($target->hasRole($target->elements('b')[0], 'button'));
        self::assertTrue($target->hasRole($target->elements('b')[0], 'img', 'button'));
        self::assertFalse($target->hasRole($target->elements('b')[0], 'link'));
        self::assertFalse($target->hasRole($target->elements('i')[0], 'button'));
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function accessibleNames(): iterable
    {
        yield 'text' => ['<a id="t" href="/">  Read   the <b>news</b> </a>', 'Read the news'];
        yield 'labelled by' => ['<a id="t" aria-labelledby="l1 missing l2" aria-label="ignored">x</a><span id="l1">First</span><span id="l2">second <i hidden>no</i></span>', 'First second'];
        yield 'labelled by nothing' => ['<a id="t" aria-labelledby="missing" aria-label=" Label ">x</a>', 'Label'];
        yield 'image' => ['<a id="t"><img alt="Logo"> <img src="x"></a>', 'Logo'];
        yield 'own image' => ['<img id="t" alt=" Cat " title="T">', 'Cat'];
        yield 'own area' => ['<map><area id="t" alt="Zone"></map>', 'Zone'];
        yield 'area without alt' => ['<map><area id="t" title="T"></map>', 'T'];
        yield 'drawing' => ['<a id="t"><svg><title>Home</title></svg></a>', 'Home'];
        yield 'drawing without title' => ['<a id="t" title="Fallback"><svg><g></g></svg></a>', 'Fallback'];
        yield 'image input' => ['<input id="t" type="IMAGE" alt="Go">', 'Go'];
        yield 'image input without alt' => ['<input id="t" type="image">', ''];
        yield 'submit' => ['<input id="t" type="submit">', 'Submit'];
        yield 'reset' => ['<input id="t" type="reset">', 'Reset'];
        yield 'submit value' => ['<input id="t" type="submit" value="Send">', 'Send'];
        yield 'reset value' => ['<input id="t" type="reset" value="Clear">', 'Clear'];
        yield 'button input' => ['<input id="t" type="button">', ''];
        yield 'button input value' => ['<input id="t" type="button" value="Open">', 'Open'];
        yield 'text input' => ['<input id="t" value="v">', 'v'];
        yield 'child label' => ['<a id="t"><span aria-label="Inner">x</span> after</a>', 'Inner after'];
        yield 'hidden child' => ['<a id="t"><span hidden>x</span>y</a>', 'y'];
        yield 'title' => ['<a id="t" title=" Title "></a>', 'Title'];
        yield 'nothing' => ['<a id="t"></a>', ''];
        yield 'inline joins' => ['<a id="t">re<b>ad</b> on</a>', 'read on'];
        yield 'blocks apart' => ['<a id="t"><p>one</p><div>two</div>three</a>', 'one two three'];
        yield 'images apart' => ['<a id="t">x<img alt="one"><img alt="two">y</a>', 'x one two y'];
        yield 'drawing with text' => ['<a id="t"><svg><title>Home</title><text>H</text></svg></a>', 'Home'];
    }

    #[DataProvider('accessibleNames')]
    public function testTheAccessibleNameIsComputed(string $html, string $name): void
    {
        $target = HtmlTarget::parse($html, new Policy());

        self::assertSame($name, $target->accessibleName($target->identifiers()['t'][0]));
    }

    public function testTheLabelIsOnlyThatOfAria(): void
    {
        $target = HtmlTarget::parse('<a id="a" title="T">text</a><a id="b" aria-label="L">text</a>', new Policy());

        self::assertSame('', $target->labelOf($target->identifiers()['a'][0]));
        self::assertSame('L', $target->labelOf($target->identifiers()['b'][0]));
    }

    public function testTheOwnTextLeavesTheDescendantsOut(): void
    {
        $target = HtmlTarget::parse("<p> a <b>b</b>\n c </p>", new Policy());

        self::assertSame('a c', $target->ownText($target->elements('p')[0]));
        self::assertSame('a b c', $target->textOf($target->elements('p')[0]));
        self::assertSame('a b', HtmlTarget::collapse(" a \n\t b "));
    }

    public function testTheTitleOfADrawingIsItsTitleChild(): void
    {
        $target = HtmlTarget::parse('<svg><g><title>deep</title></g><desc>d</desc><title>T</title></svg><svg></svg>', new Policy());

        self::assertSame('T', $target->svgTitle($target->elements('svg')[0])?->textContent);
        self::assertNull($target->svgTitle($target->elements('svg')[1]));
    }

    /**
     * @param list<Element> $elements
     *
     * @return list<string>
     */
    private function namesOf(array $elements): array
    {
        return array_map(static fn (Element $element): string => strtolower($element->localName), $elements);
    }
}
