<?php

declare(strict_types=1);

namespace Phil\PrintPreflight\Tests\Unit;

use Phil\Fonts\EmbeddingPermissions;
use Phil\PrintPreflight\Box;
use Phil\PrintPreflight\Check\Colours;
use Phil\PrintPreflight\Check\Fonts;
use Phil\PrintPreflight\Check\ImageResolution;
use Phil\PrintPreflight\ColourSpace;
use Phil\PrintPreflight\ColourWorkflow;
use Phil\PrintPreflight\Document;
use Phil\PrintPreflight\Finding;
use Phil\PrintPreflight\Page;
use Phil\PrintPreflight\PlacedImage;
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflight\Rectangle;
use Phil\PrintPreflight\Size;
use Phil\PrintPreflight\UsedFont;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Fonts::class)]
#[CoversClass(ImageResolution::class)]
#[CoversClass(Colours::class)]
final class ContentChecksTest extends TestCase
{
    /**
     * @return iterable<string, array{UsedFont, list<array{string, string, string, ?int}>}>
     */
    public static function fonts(): iterable
    {
        yield 'installable' => [new UsedFont('Sans'), []];
        yield 'preview and print' => [new UsedFont('Sans', new EmbeddingPermissions(0x0004)), []];
        yield 'subset allowed' => [new UsedFont('Sans', subset: true), []];
        yield 'whole font that forbids subsetting' => [new UsedFont('Sans', new EmbeddingPermissions(0x0100)), []];
        yield 'not embedded' => [new UsedFont('Sans', new EmbeddingPermissions(0x0002), embedded: false), [['error', 'font.not-embedded', 'The font "Sans" is not embedded; the printer would replace it.', null]]];
        yield 'restricted' => [new UsedFont('Sans', new EmbeddingPermissions(0x0202)), [['error', 'font.restricted', 'The font "Sans" forbids embedding without its owner\'s permission.', null]]];
        yield 'bitmap only' => [new UsedFont('Sans', new EmbeddingPermissions(0x0300), subset: true), [['error', 'font.bitmap-only', 'The font "Sans" may only be embedded as bitmaps, which do not print sharp.', null]]];
        yield 'subset forbidden' => [new UsedFont('Sans', new EmbeddingPermissions(0x0100), subset: true), [['error', 'font.subset-forbidden', 'The font "Sans" is embedded as a subset, which its licence forbids; embed it whole.', null]]];
    }

    /**
     * @param list<array{string, string, string, ?int}> $expected
     */
    #[DataProvider('fonts')]
    public function testFontsMustBeEmbeddedAsTheirLicenceAllows(UsedFont $font, array $expected): void
    {
        $findings = new Fonts()->check(new Document([], [$font]), new PrintProfile());

        self::assertSame($expected, PageChecksTest::describe($findings));

        foreach ($findings as $finding) {
            self::assertSame('Sans', $finding->subject);
        }
    }

    public function testEveryFontIsChecked(): void
    {
        $findings = new Fonts()->check(new Document([], [new UsedFont('A', embedded: false), new UsedFont('B', embedded: false)]), new PrintProfile());

        self::assertSame(['A', 'B'], array_map(static fn (Finding $finding): ?string => $finding->subject, $findings));
    }

    /**
     * An image 1000 pixels wide placed at the width that gives the resolution asked.
     */
    private function image(string $id, float $resolution): PlacedImage
    {
        return new PlacedImage($id, Rectangle::pt(100, 100, 72 * 1000 / $resolution, 72 * 1000 / $resolution), 1000, 1000);
    }

    public function testImagesAtTheTargetPass(): void
    {
        $document = new Document([new Page(Size::a4(), images: [$this->image('a', 300), $this->image('b', 5000)])]);

        self::assertSame([], new ImageResolution()->check($document, new PrintProfile()));
        self::assertSame([], new ImageResolution()->check(new Document([new Page(Size::a4(), images: [$this->image('c', 1200)])]), new PrintProfile(maximumResolution: 1200)));
    }

    public function testAnImageBelowTheMinimumIsAnError(): void
    {
        $document = new Document([new Page(Size::a4()), new Page(Size::a4(), images: [$this->image('cover', 149.9)])]);
        $findings = new ImageResolution()->check($document, new PrintProfile());

        self::assertSame([['error', 'image.below-minimum', 'The image "cover" on page 2 prints at 149 ppi, below the 150 ppi minimum.', 2]], PageChecksTest::describe($findings));
        self::assertSame('cover', $findings[0]->subject);
        self::assertSame(['resolution' => 149, 'minimum' => 150.0], $findings[0]->parameters);
    }

    public function testAnImageBelowTheTargetIsAWarning(): void
    {
        $document = new Document([new Page(Size::a4(), images: [$this->image('map', 150), $this->image('hero', 299.5)])]);
        $findings = new ImageResolution()->check($document, new PrintProfile());

        self::assertSame([
            ['warning', 'image.below-target', 'The image "map" on page 1 prints at 150 ppi, below the 300 ppi target.', 1],
            ['warning', 'image.below-target', 'The image "hero" on page 1 prints at 299 ppi, below the 300 ppi target.', 1],
        ], PageChecksTest::describe($findings));
        self::assertSame(['resolution' => 150, 'target' => 300.0], $findings[0]->parameters);
        self::assertSame('hero', $findings[1]->subject);
    }

    public function testAnImageFarAboveTheMaximumIsANotice(): void
    {
        $document = new Document([new Page(Size::a4(), images: [$this->image('scan', 2400.5)])]);
        $findings = new ImageResolution()->check($document, new PrintProfile(maximumResolution: 1200));

        self::assertSame([['notice', 'image.above-maximum', 'The image "scan" on page 1 prints at 2400 ppi, more than the 1200 ppi the printer uses.', 1]], PageChecksTest::describe($findings));
        self::assertSame(['resolution' => 2400, 'maximum' => 1200.0], $findings[0]->parameters);
        self::assertSame('scan', $findings[0]->subject);
    }

    public function testColoursOfTheWorkflowPass(): void
    {
        $page = new Page(Size::a4(), images: [
            new PlacedImage('cmyk', Rectangle::pt(0, 0, 10, 10), 10, 10, ColourSpace::Cmyk),
            new PlacedImage('gray', Rectangle::pt(0, 0, 10, 10), 10, 10, ColourSpace::Gray),
        ], boxes: [new Box('text', Rectangle::pt(0, 0, 10, 10)), new Box('rule', Rectangle::pt(0, 0, 10, 10), ColourSpace::Cmyk)]);

        self::assertSame([], new Colours()->check(new Document([$page]), new PrintProfile()));

        $rgb = new Page(Size::a4(), images: [new PlacedImage('photo', Rectangle::pt(0, 0, 10, 10), 10, 10)]);

        self::assertSame([], new Colours()->check(new Document([$rgb]), new PrintProfile(colourWorkflow: ColourWorkflow::Rgb)));

        $spot = new Page(Size::a4(), boxes: [new Box('logo', Rectangle::pt(0, 0, 10, 10), ColourSpace::Spot)]);

        self::assertSame([], new Colours()->check(new Document([$spot]), new PrintProfile(allowSpotColours: true)));
    }

    public function testRgbInACmykWorkflowIsANotice(): void
    {
        $document = new Document([new Page(Size::a4()), new Page(Size::a4(), images: [new PlacedImage('photo', Rectangle::pt(0, 0, 10, 10), 10, 10)], boxes: [new Box('title', Rectangle::pt(0, 0, 10, 10), ColourSpace::Rgb)])]);
        $findings = new Colours()->check($document, new PrintProfile());

        self::assertSame([
            ['notice', 'colour.rgb-in-cmyk', '"photo" on page 2 is in RGB; the printer converts it to CMYK, and bright colours may dull.', 2],
            ['notice', 'colour.rgb-in-cmyk', '"title" on page 2 is in RGB; the printer converts it to CMYK, and bright colours may dull.', 2],
        ], PageChecksTest::describe($findings));
        self::assertSame(['photo', 'title'], [$findings[0]->subject, $findings[1]->subject]);
    }

    public function testCmykInAnRgbWorkflowIsANotice(): void
    {
        $document = new Document([new Page(Size::a4(), images: [new PlacedImage('photo', Rectangle::pt(0, 0, 10, 10), 10, 10, ColourSpace::Cmyk)])]);
        $findings = new Colours()->check($document, new PrintProfile(colourWorkflow: ColourWorkflow::Rgb));

        self::assertSame([['notice', 'colour.cmyk-in-rgb', '"photo" on page 1 is in CMYK; the printer converts it from RGB with its own profile.', 1]], PageChecksTest::describe($findings));
    }

    public function testSpotColoursAreAWarningUnlessThePrinterRunsThem(): void
    {
        $document = new Document([new Page(Size::a4(), boxes: [new Box('logo', Rectangle::pt(0, 0, 10, 10), ColourSpace::Spot)])]);
        $findings = new Colours()->check($document, new PrintProfile(colourWorkflow: ColourWorkflow::Rgb));

        self::assertSame([['warning', 'colour.spot', '"logo" on page 1 uses a spot colour; the printer converts it to process colours.', 1]], PageChecksTest::describe($findings));
        self::assertSame('logo', $findings[0]->subject);
    }
}
