<?php

declare(strict_types=1);

namespace Phil\FontsBundle\Tests\Unit;

use Override;
use Phil\Fonts\Css\FontDisplay;
use Phil\Fonts\Format;
use Phil\FontsBundle\Application\CatalogueEntry;
use Phil\FontsBundle\Application\FontCatalogue;
use Phil\FontsBundle\Application\FontFaces;
use Phil\FontsBundle\Tests\Unit\Fixture\Directory;
use Phil\FontsBundle\Tests\Unit\Fixture\Fonts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FontFaces::class)]
final class FontFacesTest extends TestCase
{
    #[Override]
    protected function tearDown(): void
    {
        Directory::clean();
    }

    public function testOneRulePerFaceItsFilesSmallestFirst(): void
    {
        $faces = new FontFaces(new FontCatalogue(Directory::with([
            'sans/Regular.ttf' => Fonts::trueType('Example Sans'),
            'sans/Regular.otf' => Fonts::openType('Example Sans'),
            'sans/Regular.woff' => Fonts::woff('Example Sans'),
            'sans/Regular.woff2' => Fonts::woff2('Example Sans'),
            'Gras et maigre/Bold Face.woff' => Fonts::woff('Example Serif', 'Bold', 700),
        ])), '/assets/fonts/', FontDisplay::Optional);

        self::assertSame(<<<'CSS'
            @font-face {
                font-family: "Example Serif";
                src: local("Example Serif Bold"), local("ExampleSerif-Bold"), url("/assets/fonts/Gras%20et%20maigre/Bold%20Face.woff") format("woff");
                font-weight: 700;
                font-style: normal;
                font-stretch: 100%;
                font-display: optional;
            }

            @font-face {
                font-family: "Example Sans";
                src: local("Example Sans Regular"), local("ExampleSans-Regular"), url("/assets/fonts/sans/Regular.woff2") format("woff2"), url("/assets/fonts/sans/Regular.woff") format("woff"), url("/assets/fonts/sans/Regular.otf") format("opentype"), url("/assets/fonts/sans/Regular.ttf") format("truetype");
                font-weight: 400;
                font-style: normal;
                font-stretch: 100%;
                font-display: optional;
            }

            CSS, $faces->css());
    }

    public function testTheDefaultsServeTheFilesUnderFontsAndSwap(): void
    {
        $css = new FontFaces(new FontCatalogue(Directory::with(['Sans.ttf' => Fonts::trueType('Example Sans')])))->css();

        self::assertStringContainsString('url("/fonts/Sans.ttf") format("truetype")', $css);
        self::assertStringContainsString('font-display: swap;', $css);
    }

    public function testAUrlOfTheApplication(): void
    {
        $faces = new FontFaces(new FontCatalogue(Directory::with(['Sans.ttf' => Fonts::trueType('Example Sans')])));

        self::assertStringContainsString('url("../fonts/Sans.ttf")', $faces->css(static fn (CatalogueEntry $entry): string => '../fonts/'.$entry->path));
        self::assertSame('/fonts/Sans.ttf', $faces->url(new CatalogueEntry('Sans.ttf', '/x/Sans.ttf', Format::TrueType)));
    }

    public function testLeavesOutCollectionsNamelessFacesAndFacesNoneOfWhoseFilesWasRead(): void
    {
        $faces = new FontFaces(new FontCatalogue(Directory::with([
            'Both.ttc' => Fonts::collection([['One', 'Regular'], ['Two', 'Regular']]),
            'Nameless.ttf' => Fonts::trueType(''),
            'Brotli.woff2' => Fonts::woff2('Brotli Sans'),
            'Served.woff2' => Fonts::woff2('Served Sans'),
            'Served.ttf' => Fonts::trueType('Served Sans'),
        ])));
        $rules = $faces->rules();

        self::assertCount(1, $rules);
        self::assertStringContainsString('url("/fonts/Served.woff2") format("woff2"), url("/fonts/Served.ttf") format("truetype")', $rules[0]->toCss());
    }

    public function testNoFontIsAnEmptyStylesheet(): void
    {
        self::assertSame('', new FontFaces(new FontCatalogue('/nonexistent'))->css());
    }
}
