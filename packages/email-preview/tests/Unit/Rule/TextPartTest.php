<?php

declare(strict_types=1);

namespace Phil\EmailPreview\Tests\Unit\Rule;

use Phil\EmailPreview\Rule\TextPart;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TextPart::class)]
final class TextPartTest extends RuleTestCase
{
    public function testTheRuleIsNamed(): void
    {
        self::assertSame('text-part', new TextPart()->name());
    }

    public function testAnEmailWithoutTextPartIsAnError(): void
    {
        self::assertSame(['[error] text-part: The email has no plain-text part.'], self::findings(new TextPart(), '<a href="https://a.test/">A</a>', " \n "));
        self::assertSame(['[error] text-part: The email has no plain-text part.'], self::findings(new TextPart(), '<p>A</p>', ''));
    }

    public function testATextPartThatGivesEveryAddressPasses(): void
    {
        $html = '<a href="https://a.test/x?a=1&amp;b=2">A</a> <a href=" http://b.test/ ">B</a> <a href="MAILTO:hi@a.test">write</a>';

        self::assertSame([], self::findings(new TextPart(), $html, "A (https://a.test/x?a=1&b=2)\nB (http://b.test/)\nwrite (hi@a.test)"));
    }

    public function testEachAddressMissingFromTheTextIsAnErrorOnce(): void
    {
        $html = '<a href="https://a.test/">A</a><a href="https://a.test/">again</a><a href="mailto:hi@a.test">write</a><a href="https://b.test/">B</a>';

        self::assertSame([
            '[error] text-part: The plain-text part does not give the address of a link. (https://a.test/)',
            '[error] text-part: The plain-text part does not give the address of a link. (mailto:hi@a.test)',
        ], self::findings(new TextPart(), $html, 'See https://b.test/'));
    }

    public function testOtherSchemesFragmentsAndEmptyAddressesAreNotRead(): void
    {
        $html = '<a href="tel:+15551234">call</a><a href="#top">top</a><a href="mailto:">x</a><a href="">y</a><a>z</a><a href="/relative">r</a><a href="ftp://a.test/">f</a>';

        self::assertSame([], self::findings(new TextPart(), $html, 'Text'));
    }

    public function testAnAddressIsTrimmedBeforeItIsLookedFor(): void
    {
        self::assertSame(['[error] text-part: The plain-text part does not give the address of a link. (https://c.test/)'], self::findings(new TextPart(), '<a href=" https://c.test/ ">C</a>', 'C'));
    }

    public function testAMailtoAddressIsLookedForWithoutItsScheme(): void
    {
        self::assertSame([], self::findings(new TextPart(), '<a href="mailto:hi@a.test">hi@a.test</a>', 'hi@a.test'));
        self::assertSame(['[error] text-part: The plain-text part does not give the address of a link. (mailto:hi@a.test)'], self::findings(new TextPart(), '<a href="mailto:hi@a.test">x</a>', 'mailto'));
    }

    public function testASchemeIsReadInAnyCase(): void
    {
        $html = '<a href="HTTPS://A.test/">A</a><a href="Http://b.test/">B</a><a href="MailTo:hi@a.test">write</a>';

        self::assertSame([
            '[error] text-part: The plain-text part does not give the address of a link. (HTTPS://A.test/)',
            '[error] text-part: The plain-text part does not give the address of a link. (Http://b.test/)',
            '[error] text-part: The plain-text part does not give the address of a link. (MailTo:hi@a.test)',
        ], self::findings(new TextPart(), $html, 'Text'));
    }

    public function testTheWholeAddressOfAMailtoLinkIsLookedFor(): void
    {
        self::assertSame(['[error] text-part: The plain-text part does not give the address of a link. (mailto:hi@a.test)'], self::findings(new TextPart(), '<a href="mailto:hi@a.test">x</a>', 'Write to i@a.test'));
    }
}
