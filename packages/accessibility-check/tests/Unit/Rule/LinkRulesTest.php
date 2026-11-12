<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Tests\Unit\Rule;

use Phil\AccessibilityCheck\Policy;
use Phil\AccessibilityCheck\Rule\ButtonName;
use Phil\AccessibilityCheck\Rule\GenericLinkText;
use Phil\AccessibilityCheck\Rule\LinkName;
use Phil\AccessibilityCheck\Rule\LinkText;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LinkName::class)]
#[CoversClass(ButtonName::class)]
#[CoversClass(LinkText::class)]
#[CoversClass(GenericLinkText::class)]
final class LinkRulesTest extends RuleTestCase
{
    public function testTheRulesAreDescribed(): void
    {
        self::assertSame(['link-name', SuccessCriterion::LinkPurposeInContext, Severity::Error], [new LinkName()->id(), new LinkName()->criterion(), new LinkName()->severity()]);
        self::assertSame(['button-name', SuccessCriterion::NameRoleValue, Severity::Error], [new ButtonName()->id(), new ButtonName()->criterion(), new ButtonName()->severity()]);
        self::assertSame(['link-text', SuccessCriterion::LinkPurposeInContext, Severity::Warning], [new LinkText()->id(), new LinkText()->criterion(), new LinkText()->severity()]);
    }

    public function testALinkHasAName(): void
    {
        self::assertSame(
            ['link-name.empty @ a:nth-of-type(1) (href=/a)', 'link-name.empty @ a:nth-of-type(6) (href=)'],
            self::html(new LinkName(), '<a href="/a"> </a><a href="/b">B</a><a href="/c"><img alt="C"></a><a name="anchor"></a><a href="/d" hidden></a><a href=""><img src="x"></a>'),
        );
        self::assertSame(
            ['link-name.empty @ blocks[0].data.text a (href=/x)', 'link-name.empty @ blocks[2].data.text (href=https://e.org)', 'link-name.empty @ blocks[3].data.text (href=)'],
            self::ujo(new LinkName(), [
                ['id' => 'p', 'type' => 'paragraph', 'data' => ['text' => 'See <a href="/x"></a>']],
                ['id' => 'b', 'type' => 'button', 'data' => ['link' => 'https://e.org', 'text' => 'Shop']],
                ['id' => 'c', 'type' => 'button', 'data' => ['link' => 'https://e.org', 'text' => ' ']],
                ['id' => 'd', 'type' => 'button', 'data' => []],
            ]),
        );
    }

    public function testAButtonHasAName(): void
    {
        self::assertSame(
            ['button-name.empty @ button:nth-of-type(1)', 'button-name.empty @ input:nth-of-type(1)', 'button-name.empty @ input:nth-of-type(2)', 'button-name.empty @ span:nth-of-type(1)'],
            self::html(new ButtonName(), <<<'HTML'
                <button></button><button>Send</button><button hidden></button><button aria-label="Close"></button>
                <input type="button"><input type=" BUTTON " value=""><input type="button" value="Open"><input type="submit"><input type="reset"><input type="text">
                <span role="button"></span><span role="button">Menu</span><span>plain</span>
                HTML),
        );
        self::assertSame(['button-name.empty @ blocks[0].data.html button'], self::ujo(new ButtonName(), [['id' => 'r', 'type' => 'raw', 'data' => ['html' => '<button></button>']]]));
    }

    public function testALinkTextSaysWhereItLeads(): void
    {
        self::assertSame([
            'link-text.generic @ a:nth-of-type(1) (href=/a, text=Click here!)',
            'link-text.generic @ p > a (href=/c, text=Lire la suite…)',
            'link-text.generic @ a:nth-of-type(4) (href=/e, text=ici)',
            'link-text.generic @ a:nth-of-type(5) (href=/f, text=More)',
        ], self::html(new LinkText(), <<<'HTML'
            <a href="/a">Click   here!</a><a href="/b">Our prices</a><p lang="fr-CA"><a href="/c">Lire la suite…</a></p><a href="/d" hidden>here</a>
            <a href="/e" lang="fr">ici</a><a href="/f" aria-label="More">Prices of 2026</a><a href="/g" lang="ja">here</a><a>here</a><a href="/h"></a>
            HTML));
    }

    public function testTheLanguageOfTheContentChoosesTheGenericTexts(): void
    {
        self::assertSame([], self::html(new LinkText(), '<a href="/a">mehr</a>', new Policy(), 'fr'));
        self::assertSame(['link-text.generic @ a (href=/a, text=mehr)'], self::html(new LinkText(), '<a href="/a">mehr</a>', new Policy(), 'de-AT'));
        self::assertSame(['link-text.generic @ a (href=/a, text=mehr)'], self::html(new LinkText(), '<a href="/a">mehr</a>'));
        self::assertSame([], self::html(new LinkText(), '<a href="/a">here</a>', new Policy()->withGenericLinkTexts('en'), 'en'));
        self::assertSame(['link-text.generic @ a (href=/a, text=hier)'], self::html(new LinkText(), '<a href="/a">hier</a>', new Policy()->withGenericLinkTexts('nl', 'hier'), 'nl'));
    }

    public function testTheLinksOfADocumentAreItsLinksAndButtons(): void
    {
        self::assertSame([
            'link-text.generic @ blocks[0].data.text a (href=/x, text=ici)',
            'link-text.generic @ blocks[1].data.text (href=https://e.org, text=Read more)',
        ], self::ujo(new LinkText(), [
            ['id' => 'p', 'type' => 'paragraph', 'data' => ['text' => 'Voir <a href="/x">ici</a>'], 'tunes' => ['language' => ['language' => 'fr']]],
            ['id' => 'b', 'type' => 'button', 'data' => ['link' => 'https://e.org', 'text' => 'Read more']],
            ['id' => 'c', 'type' => 'button', 'data' => ['link' => 'https://e.org', 'text' => 'ici']],
            ['id' => 'd', 'type' => 'button', 'data' => ['text' => ['not text']]],
            ['id' => 'e', 'type' => 'paragraph', 'data' => ['text' => 'Read more']],
        ], new Policy(), 'en'));
        self::assertSame(['link-text.generic @ blocks[0].data.text (href=, text=ici)'], self::ujo(new LinkText(), [['id' => 'c', 'type' => 'button', 'data' => ['link' => 3, 'text' => 'ici']]]));
    }

    public function testALinkTextIsComparedWithoutCaseSpacesNorPunctuation(): void
    {
        self::assertSame("plus d'infos", GenericLinkText::normalize('  « Plus  d’INFOS » → '));
        self::assertSame('read more', GenericLinkText::normalize("Read\n more..."));
        self::assertSame('', GenericLinkText::normalize(' … '));
        self::assertSame('détails', GenericLinkText::normalize('DÉTAILS'));
        self::assertSame('', GenericLinkText::normalize("\xFF"));
    }
}
