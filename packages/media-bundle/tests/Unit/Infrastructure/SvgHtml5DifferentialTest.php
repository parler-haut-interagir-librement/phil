<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Infrastructure;

use Dom\HTMLDocument;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Infrastructure\Svg\SanitizingSvgPurifier;
use Phil\MediaBundle\Infrastructure\Svg\SvgMinifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * An SVG image the library keeps, purified then made smaller, is parsed again by PHP's HTML5 parser,
 * `Dom\HTMLDocument`, as a page that inlines it would be: it holds no script, no HTML element, no event handler and no
 * `javascript:` address, whatever the minifier rewrote after the sanitizer. An image refused whole is never kept.
 */
#[CoversClass(SanitizingSvgPurifier::class)]
#[CoversClass(SvgMinifier::class)]
final class SvgHtml5DifferentialTest extends TestCase
{
    private const string NS = 'xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"';

    private const string HTML = 'http://www.w3.org/1999/xhtml';

    /** The elements of the page around the image. */
    private const array PAGE = ['html', 'head', 'body', 'div', 'p'];

    /**
     * @return iterable<string, array{string}>
     */
    public static function charges(): iterable
    {
        $ns = self::NS;
        $img = '<img src=x onerror=alert(1)>';

        yield 'a script in a style' => ["<svg {$ns}><style><script>alert(1)</script></style><rect/></svg>"];
        yield 'a CDATA section that closes the style' => ["<svg {$ns}><style><![CDATA[</style>{$img}]]></style><rect/></svg>"];
        yield 'markup escaped in a title' => ["<svg {$ns}><title>&lt;img src=x onerror=alert(1)&gt;</title><rect/></svg>"];
        yield 'a CDATA section in a text' => ["<svg {$ns}><text><![CDATA[<p>{$img}]]></text></svg>"];
        yield 'an HTML element that breaks out of foreign content' => ["<svg {$ns}><text><![CDATA[<font color=red>{$img}</font>]]></text></svg>"];
        yield 'text with spaces the minifier keeps' => ["<svg {$ns}><text>  &lt;/svg&gt;  {$img}  </text></svg>"];
        yield 'an HTML integration point' => ["<svg {$ns}><foreignObject><p xmlns=\"http://www.w3.org/1999/xhtml\"><img src=\"x\" onerror=\"alert(1)\"/></p></foreignObject><rect/></svg>"];
        yield 'an element in the HTML namespace' => ["<svg {$ns} xmlns:h=\"http://www.w3.org/1999/xhtml\"><h:img src=\"x\" onerror=\"alert(1)\"/><rect/></svg>"];
        yield 'an event handler in capitals' => ["<svg {$ns}><rect ONLOAD=\"alert(1)\" width=\"1\"/></svg>"];
        yield 'an event handler under a prefix' => ["<svg {$ns} xmlns:x=\"http://www.w3.org/2000/svg\"><rect x:onload=\"alert(1)\"/></svg>"];
        yield 'a quote that closes the attribute' => ["<svg {$ns}><rect fill='a\"&gt;&lt;img src=x onerror=alert(1)&gt;'/></svg>"];
        yield 'a scheme with a tab and a space' => ["<svg {$ns}><a xlink:href=\" java&#x09;script:alert(1)\"><rect/></a></svg>"];
        yield 'a scheme in mixed case' => ["<svg {$ns}><a href=\"JaVaScRiPt:alert(1)\"><rect/></a></svg>"];
        yield 'an address in a style attribute' => ["<svg {$ns}><rect style=\"fill:url(javascript:alert(1))\"/></svg>"];
        yield 'an animation that rewrites a link' => ["<svg {$ns}><a><set attributeName=\"href\" to=\"javascript:alert(1)\"/><rect/></a></svg>"];
        yield 'a coordinate that hides markup' => ["<svg {$ns}><path d=\"M0 0&quot;&gt;&lt;img src=x onerror=alert(1)&gt;\"/></svg>"];
        yield 'a comment that HTML closes early' => ["<svg {$ns}><!-- --!>{$img} --><rect/></svg>"];
        yield 'a processing instruction' => ["<svg {$ns}><?x {$img}?><rect/></svg>"];
    }

    #[DataProvider('charges')]
    public function testAKeptImageReadByAnHtmlParserHoldsNothingThatRuns(string $svg): void
    {
        try {
            $kept = new SanitizingSvgPurifier()->purify($svg);
        } catch (InvalidMedia $invalidMedia) {
            self::assertStringStartsWith('The SVG image is refused', $invalidMedia->getMessage());

            return;
        }

        foreach (['<div>%s</div>', '<p>text%s</p>'] as $page) {
            self::assertSame([], $this->dangers(\sprintf($page, $kept)), $kept);
        }
    }

    /**
     * The check sees what it looks for.
     */
    public function testTheCheckSeesEachDanger(): void
    {
        self::assertSame([], $this->dangers('<div><svg><rect/></svg></div>'));
        self::assertSame(
            ['script', 'html:img', '@onload', 'javascript:@href'],
            $this->dangers('<div><svg><script>x</script></svg><img src="x"><svg ONLOAD="x"><a href=" java&#x09;script:x"></a></svg></div>'),
        );
    }

    /**
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

            foreach ($element->attributes as $attribute) {
                $attributeName = strtolower($attribute->localName);

                if (str_starts_with($attributeName, 'on')) {
                    $dangers[] = '@'.$attributeName;
                }

                if (str_contains(strtolower((string) preg_replace('/[\x00-\x20]+/', '', $attribute->value)), 'javascript:')) {
                    $dangers[] = 'javascript:@'.$attributeName;
                }
            }
        }

        return $dangers;
    }
}
