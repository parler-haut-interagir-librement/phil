<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Infrastructure;

use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Infrastructure\Svg\SanitizingSvgPurifier;
use Phil\MediaBundle\Infrastructure\Svg\SvgMinifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SvgMinifier::class)]
#[CoversClass(SanitizingSvgPurifier::class)]
final class SvgMinifierTest extends TestCase
{
    private const string SVG = '<svg xmlns="http://www.w3.org/2000/svg">';

    public function testWhatChangesNothingInTheDrawingIsDropped(): void
    {
        $svg = self::SVG."\n  <!-- made by hand -->\n  <?editor x?>\n  <metadata><rdf/></metadata>\n  <g>\n    <rect width=\"1\" height=\"1\"/>\n  </g>\n</svg>";

        self::assertSame(self::SVG.'<g><rect width="1" height="1"/></g></svg>', new SvgMinifier()->minify($svg));
    }

    public function testTextKeepsItsWhitespace(): void
    {
        foreach (['text', 'tspan', 'textPath', 'title', 'desc', 'style'] as $element) {
            $svg = self::SVG.'<'.$element.'> <b> </b> </'.$element.'></svg>';
            self::assertSame($svg, new SvgMinifier()->minify($svg), $element);
        }

        $preserved = self::SVG.'<g xml:space="preserve"> <g> </g> </g> <g> </g></svg>';
        self::assertSame(self::SVG.'<g xml:space="preserve"> <g> </g> </g><g/></svg>', new SvgMinifier()->minify($preserved));
        self::assertSame(self::SVG.'<g xml:space="default"/></svg>', new SvgMinifier()->minify(self::SVG.'<g xml:space="default"> </g></svg>'));
        self::assertSame(self::SVG.'<text>ac</text></svg>', new SvgMinifier()->minify(self::SVG.'<text>a<!-- b -->c</text></svg>'));
    }

    /**
     * @return iterable<string, array{string, string, int<0, 8>}>
     */
    public static function coordinates(): iterable
    {
        yield 'kept within the precision' => ['M1.123 2.5', 'M1.123 2.5', 3];
        yield 'zeros kept within the precision' => ['M0.50 0.120', 'M0.50 0.120', 3];
        yield 'rounded' => ['M1.12345 2.98765', 'M1.123 2.988', 3];
        yield 'trailing zeros' => ['M1.10001 2.00001', 'M1.1 2', 3];
        yield 'negative' => ['M-1.23456-0.00001', 'M-1.235-0', 3];
        yield 'whole before a point' => ['M1.9999.5', 'M2 .5', 3];
        yield 'shorthand kept short' => ['M1.5.123456', 'M1.5.123', 3];
        yield 'shorthand rounded to zero' => ['M1.5.00001', 'M1.5 0', 3];
        yield 'shorthand at the start' => ['.123456', '.123', 3];
        yield 'shorthand whole at the start' => ['.99999', '1', 3];
        yield 'shorthand whole after a letter' => ['M.99999', 'M1', 3];
        yield 'exponent left alone' => ['M1.123456e2 3.1234567E-1', 'M1.123456e2 3.1234567E-1', 3];
        yield 'precision zero' => ['M10.6 20.4', 'M11 20', 0];
        yield 'precision one' => ['M10.06 0.04', 'M10.1 0', 1];
        yield 'integers' => ['M100 200', 'M100 200', 0];
        yield 'precision two' => ['M1.004', 'M1', 2];
    }

    /**
     * @param int<0, 8> $precision
     */
    #[DataProvider('coordinates')]
    public function testCoordinatesAreRounded(string $d, string $rounded, int $precision): void
    {
        self::assertSame(self::SVG.'<path d="'.$rounded.'"/></svg>', new SvgMinifier($precision)->minify(self::SVG.'<path d="'.$d.'"/></svg>'));
    }

    public function testPointsAndTransformsAreRoundedOtherAttributesAreNot(): void
    {
        $svg = self::SVG.'<polygon points="0.12345,1.5 2,3" transform="rotate(45.55555)" x="1.23456"/></svg>';

        self::assertSame(self::SVG.'<polygon points="0.123,1.5 2,3" transform="rotate(45.556)" x="1.23456"/></svg>', new SvgMinifier()->minify($svg));
    }

    public function testMalformedXmlIsRefused(): void
    {
        $this->expectException(InvalidMedia::class);
        $this->expectExceptionMessageIsOrContains('The SVG image is not well-formed XML.');

        new SvgMinifier()->minify('<svg');
    }

    public function testAnEmptyImageIsRefused(): void
    {
        $this->expectException(InvalidMedia::class);
        $this->expectExceptionMessageIsOrContains('The SVG image is not well-formed XML.');

        new SvgMinifier()->minify('');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refused(): iterable
    {
        yield 'not svg' => ['<html/>', 'The SVG image is refused: not_svg.'];
        yield 'malformed' => ['<svg', 'The SVG image is refused: malformed.'];
    }

    #[DataProvider('refused')]
    public function testThePurifierRefusesWhatTheSanitizerRefuses(string $svg, string $message): void
    {
        $this->expectException(InvalidMedia::class);
        $this->expectExceptionMessageIsOrContains($message);

        new SanitizingSvgPurifier()->purify($svg);
    }

    public function testThePurifierSanitizesThenMinifies(): void
    {
        self::assertSame(self::SVG.'<path d="M0.333 1"/></svg>', new SanitizingSvgPurifier()->purify(self::SVG.' <path d="M0.33333 1" onload="x()"/> <foreignObject/></svg>'));
    }
}
