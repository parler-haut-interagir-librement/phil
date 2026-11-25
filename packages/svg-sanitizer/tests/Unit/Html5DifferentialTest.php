<?php

declare(strict_types=1);

namespace Phil\SvgSanitizer\Tests\Unit;

use Dom\HTMLDocument;
use Phil\CssSanitizer\Url\CallbackRewriter;
use Phil\SvgSanitizer\Internal\Sanitization;
use Phil\SvgSanitizer\Internal\ScopedStyleSheet;
use Phil\SvgSanitizer\Policy;
use Phil\SvgSanitizer\Removal;
use Phil\SvgSanitizer\RemovalReason;
use Phil\SvgSanitizer\Sanitized;
use Phil\SvgSanitizer\SvgSanitizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * The sanitizer reads XML; a browser reads an SVG inlined in a page with its HTML parser, which can read the same bytes
 * otherwise. Every output is parsed again by PHP's HTML5 parser, `Dom\HTMLDocument`, in the places a page puts an
 * image — a `div`, after a `p`, inside a link —, and must hold no script, no HTML element, no event handler and no
 * `javascript:` address, under every policy.
 */
#[CoversClass(SvgSanitizer::class)]
#[CoversClass(Sanitization::class)]
#[CoversClass(ScopedStyleSheet::class)]
#[UsesClass(Policy::class)]
#[UsesClass(Sanitized::class)]
#[UsesClass(Removal::class)]
#[UsesClass(RemovalReason::class)]
#[UsesClass(CallbackRewriter::class)]
final class Html5DifferentialTest extends TestCase
{
    private const string NS = 'xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"';

    private const string HTML = 'http://www.w3.org/1999/xhtml';

    /** The elements of the page around the image, which are HTML by right. */
    private const array PAGE = ['html', 'head', 'body', 'div', 'p', 'a', 'span'];

    /** Where a page puts an image: `%s` is the output of the sanitizer. */
    private const array PLACES = [
        'in a div' => '<div>%s</div>',
        'after a paragraph' => '<p>text%s</p>',
        'in a link' => '<a href="/x"><span>%s</span></a>',
    ];

    /**
     * @return iterable<string, array{string}>
     */
    public static function charges(): iterable
    {
        $ns = self::NS;
        $img = '<img src=x onerror=alert(1)>';

        // A <style> that holds markup, or that a CDATA section closes early.
        yield 'a script in a style' => ["<svg {$ns}><style><script>alert(1)</script></style></svg>"];
        yield 'a CDATA section that closes the style' => ["<svg {$ns}><style><![CDATA[</style>{$img}]]></style></svg>"];
        yield 'markup escaped in a style' => ["<svg {$ns}><style>&lt;img src=x onerror=alert(1)&gt;</style></svg>"];
        yield 'a closing style escaped in a style' => ["<svg {$ns}><style>&lt;/style&gt;&lt;script&gt;alert(1)&lt;/script&gt;</style></svg>"];
        yield 'a style that ends in a raw text element' => ["<svg {$ns}><style>a{}</style><style><![CDATA[</svg>{$img}]]></style></svg>"];
        yield 'an address in a rule of a style' => ["<svg {$ns}><style>rect{fill:url(javascript:alert(1))}</style><rect/></svg>"];
        // Text that HTML reads as markup: in a title, a description, a text.
        yield 'markup escaped in a title' => ["<svg {$ns}><title>&lt;img src=x onerror=alert(1)&gt;</title></svg>"];
        yield 'a CDATA section in a title' => ["<svg {$ns}><title><![CDATA[{$img}]]></title></svg>"];
        yield 'a paragraph in a CDATA section of a text' => ["<svg {$ns}><text><![CDATA[<p>{$img}]]></text></svg>"];
        yield 'a CDATA section that closes the description' => ["<svg {$ns}><desc><![CDATA[</desc>{$img}]]></desc></svg>"];
        yield 'an HTML element that breaks out of foreign content' => ["<svg {$ns}><text><![CDATA[<font color=red>{$img}</font>]]></text></svg>"];
        yield 'a closing svg in text' => ["<svg {$ns}><text>&lt;/svg&gt;{$img}</text></svg>"];
        yield 'an HTML integration point' => ["<svg {$ns}><foreignObject><p xmlns=\"http://www.w3.org/1999/xhtml\"><img src=\"x\" onerror=\"alert(1)\"/></p></foreignObject></svg>"];
        yield 'MathML inside' => ["<svg {$ns}><math xmlns=\"http://www.w3.org/1998/Math/MathML\"><mi><img src=\"x\" onerror=\"alert(1)\"/></mi></math></svg>"];
        yield 'an element in the HTML namespace' => ["<svg {$ns} xmlns:h=\"http://www.w3.org/1999/xhtml\"><h:img src=\"x\" onerror=\"alert(1)\"/></svg>"];
        yield 'a script element in capitals' => ["<svg {$ns}><SCRIPT>alert(1)</SCRIPT></svg>"];
        // Names of attributes the parsers read otherwise.
        yield 'an event handler in capitals' => ["<svg {$ns}><rect ONLOAD=\"alert(1)\" width=\"1\"/></svg>"];
        yield 'an event handler in mixed case' => ["<svg {$ns} onLoad=\"alert(1)\"><rect/></svg>"];
        yield 'an event handler under a prefix' => ["<svg {$ns} xmlns:x=\"http://www.w3.org/2000/svg\"><rect x:onload=\"alert(1)\"/></svg>"];
        yield 'an event handler under the xlink prefix' => ["<svg {$ns}><rect xlink:onclick=\"alert(1)\"/></svg>"];
        yield 'a quote that closes the attribute' => ["<svg {$ns}><rect fill='a\"&gt;&lt;img src=x onerror=alert(1)&gt;'/></svg>"];
        // Addresses.
        yield 'a scheme with a tab and a space' => ["<svg {$ns}><a xlink:href=\" java&#x09;script:alert(1)\"><rect/></a></svg>"];
        yield 'a scheme in mixed case' => ["<svg {$ns}><a href=\"JaVaScRiPt:alert(1)\"><rect/></a></svg>"];
        yield 'a scheme of character references' => ["<svg {$ns}><a href=\"&#106;&#97;&#118;&#97;&#115;&#99;&#114;&#105;&#112;&#116;&#58;alert(1)\"><rect/></a></svg>"];
        yield 'an address in a style attribute' => ["<svg {$ns}><rect style=\"fill:url(javascript:alert(1))\"/></svg>"];
        yield 'an external use' => ["<svg {$ns}><use href=\"data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=#x\"/></svg>"];
        yield 'an animation that rewrites a link' => ["<svg {$ns}><a><set attributeName=\"href\" to=\"javascript:alert(1)\"/><rect/></a></svg>"];
        yield 'an animation of an event' => ["<svg {$ns}><rect><animate attributeName=\"onclick\" to=\"alert(1)\"/></rect></svg>"];
        // What XML allows that HTML does not read the same way.
        yield 'a comment that HTML closes early' => ["<svg {$ns}><!-- --!>{$img} --></svg>"];
        yield 'a processing instruction' => ["<svg {$ns}><?x {$img}?></svg>"];
        yield 'an entity of the document type' => ["<!DOCTYPE svg [<!ENTITY x \"&lt;img src=x onerror=alert(1)&gt;\">]><svg {$ns}><text>&x;</text></svg>"];
    }

    /**
     * @return iterable<string, array{Policy}>
     */
    public static function policies(): iterable
    {
        yield 'the default policy' => [new Policy()];
        yield 'with style elements' => [new Policy()->withStyleElements(true)];
        yield 'with animations' => [new Policy()->withAnimations()];
        yield 'with everything optional' => [new Policy()->withStyleElements(true)->withAnimations()->withDataImages(true)->withIdPrefix(null)];
    }

    /**
     * @return iterable<string, array{string, Policy}>
     */
    public static function chargesUnderPolicies(): iterable
    {
        foreach (self::charges() as $charge => [$svg]) {
            foreach (self::policies() as $name => [$policy]) {
                yield $charge.', '.$name => [$svg, $policy];
            }
        }
    }

    #[DataProvider('chargesUnderPolicies')]
    public function testTheOutputReadByAnHtmlParserHoldsNothingThatRuns(string $svg, Policy $policy): void
    {
        $output = new SvgSanitizer($policy)->sanitize($svg)->svg;

        foreach (self::PLACES as $place => $page) {
            self::assertSame([], $this->dangers(\sprintf($page, $output)), $place.': '.$output);
        }
    }

    /**
     * The check finds what it looks for: each danger, written in a page by hand, is reported.
     *
     * @param list<string> $expected
     */
    #[DataProvider('dangerousPages')]
    public function testTheCheckSeesEachDanger(string $html, array $expected): void
    {
        self::assertSame($expected, $this->dangers($html));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function dangerousPages(): iterable
    {
        yield 'nothing' => ['<div><svg><rect fill="red"/><a href="#x"><text>x</text></a></svg></div>', []];
        yield 'a script' => ['<div><svg><script>alert(1)</script></svg></div>', ['script']];
        yield 'an HTML element out of the svg' => ['<div><svg><text></text></svg><img src="x"></div>', ['html:img']];
        yield 'an HTML element broken out of the svg' => ['<div><svg><font color="red"></font></svg></div>', ['html:font']];
        yield 'an event handler in capitals' => ['<div><svg ONLOAD="x"></svg></div>', ['@onload']];
        yield 'a scheme padded with spaces' => ['<div><svg><a href=" java&#x09;script:x"></a></svg></div>', ['javascript:@href']];
        yield 'a scheme in a rule' => ['<div><svg><style>a{fill:url( JavaScript:x)}</style></svg></div>', ['javascript:style']];
    }

    /**
     * What a browser would run in a page, read as its HTML parser reads it.
     *
     * @return list<string>
     */
    private function dangers(string $body): array
    {
        $document = HTMLDocument::createFromString('<!doctype html><html><head></head><body>'.$body.'</body></html>', \LIBXML_NOERROR);
        $dangers = [];

        foreach ($document->getElementsByTagName('*') as $element) {
            $name = strtolower($element->localName);

            if ('script' === $name) {
                $dangers[] = 'script';
            } elseif (self::HTML === $element->namespaceURI && !\in_array($name, self::PAGE, true)) {
                $dangers[] = 'html:'.$name;
            }

            if ('style' === $name && $this->runs((string) $element->textContent)) {
                $dangers[] = 'javascript:style';
            }

            foreach ($element->attributes as $attribute) {
                $attributeName = strtolower($attribute->localName);

                if (str_starts_with($attributeName, 'on')) {
                    $dangers[] = '@'.$attributeName;
                }

                if ($this->runs($attribute->value)) {
                    $dangers[] = 'javascript:@'.$attributeName;
                }
            }
        }

        return $dangers;
    }

    /**
     * A `javascript:` address, as a browser strips the spaces, tabs and line feeds out of a scheme.
     */
    private function runs(string $value): bool
    {
        return str_contains(strtolower((string) preg_replace('/[\x00-\x20]+/', '', $value)), 'javascript:');
    }
}
