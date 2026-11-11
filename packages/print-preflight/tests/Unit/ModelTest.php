<?php

declare(strict_types=1);

namespace Phil\PrintPreflight\Tests\Unit;

use Closure;
use Phil\Fonts\EmbeddingPermissions;
use Phil\Fonts\EmbeddingUsage;
use Phil\Fonts\Font;
use Phil\Fonts\Name\NameRecord;
use Phil\Fonts\Name\Names;
use Phil\PrintPreflight\Box;
use Phil\PrintPreflight\ColourSpace;
use Phil\PrintPreflight\Document;
use Phil\PrintPreflight\Exception\InvalidDocument;
use Phil\PrintPreflight\Finding;
use Phil\PrintPreflight\Length;
use Phil\PrintPreflight\Page;
use Phil\PrintPreflight\PlacedImage;
use Phil\PrintPreflight\Rectangle;
use Phil\PrintPreflight\Rule;
use Phil\PrintPreflight\Severity;
use Phil\PrintPreflight\Size;
use Phil\PrintPreflight\UsedFont;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PlacedImage::class)]
#[CoversClass(Box::class)]
#[CoversClass(Page::class)]
#[CoversClass(Document::class)]
#[CoversClass(UsedFont::class)]
#[CoversClass(ColourSpace::class)]
#[CoversClass(Rule::class)]
#[CoversClass(Finding::class)]
#[CoversClass(InvalidDocument::class)]
final class ModelTest extends TestCase
{
    public function testTheResolutionOfAnImageIsTheLowerOfItsTwoAxes(): void
    {
        self::assertEqualsWithDelta(300.0, new PlacedImage('a', Rectangle::pt(0, 0, 144, 72), 600, 300)->effectiveResolution(), 1e-9);
        self::assertEqualsWithDelta(300.0, new PlacedImage('b', Rectangle::pt(0, 0, 144, 72), 600, 600)->effectiveResolution(), 1e-9);
        self::assertEqualsWithDelta(150.0, new PlacedImage('c', Rectangle::pt(0, 0, 72, 144), 600, 300)->effectiveResolution(), 1e-9);
    }

    public function testACroppedImageTakesItsResolutionFromItsScaledSize(): void
    {
        $image = new PlacedImage('crop', Rectangle::pt(0, 0, 72, 72), 600, 600, scaledTo: Size::in(2, 4));

        self::assertEqualsWithDelta(150.0, $image->effectiveResolution(), 1e-9);

        $wide = new PlacedImage('crop', Rectangle::pt(0, 0, 72, 72), 600, 600, scaledTo: Size::in(4, 2));

        self::assertEqualsWithDelta(150.0, $wide->effectiveResolution(), 1e-9);
    }

    public function testAnImageKeepsWhatItIsGiven(): void
    {
        $image = new PlacedImage('photo', Rectangle::pt(0, 0, 1, 1), 1, 1);

        self::assertSame('photo', $image->id);
        self::assertSame(ColourSpace::Rgb, $image->colourSpace);
        self::assertFalse($image->bleeds);
        self::assertNull($image->scaledTo);
    }

    /**
     * @return iterable<string, array{int, int, float, float, string}>
     */
    public static function invalidImages(): iterable
    {
        yield 'no pixel wide' => [0, 10, 10, 10, 'The image "x" must have at least one pixel each way; 0 × 10 given.'];
        yield 'no pixel high' => [10, 0, 10, 10, 'The image "x" must have at least one pixel each way; 10 × 0 given.'];
        yield 'flat frame' => [10, 10, 0, 10, 'The image "x" must be placed in a frame of positive size.'];
        yield 'thin frame' => [10, 10, 10, 0, 'The image "x" must be placed in a frame of positive size.'];
    }

    #[DataProvider('invalidImages')]
    public function testAnImageNeedsPixelsAndAFrame(int $pixelWidth, int $pixelHeight, float $width, float $height, string $message): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains($message);

        new PlacedImage('x', Rectangle::pt(0, 0, $width, $height), $pixelWidth, $pixelHeight);
    }

    public function testABoxHasNoColourSpaceAndDoesNotBleedByDefault(): void
    {
        $box = new Box('title', Rectangle::pt(0, 0, 1, 1));

        self::assertSame('title', $box->id);
        self::assertNull($box->colourSpace);
        self::assertFalse($box->bleeds);
        self::assertNull($box->inkCoverage);
        self::assertFalse($box->overprint);
        self::assertNull($box->lineWidth);
        self::assertFalse($box->transparency);

        $image = new PlacedImage('x', Rectangle::pt(0, 0, 1, 1), 1, 1);

        self::assertNull($image->inkCoverage);
        self::assertFalse($image->transparency);
        self::assertSame(0.0, new Box('rule', Rectangle::pt(0, 0, 1, 1), inkCoverage: 0, lineWidth: Length::pt(0))->lineWidth?->points);
        self::assertSame(0.0, new PlacedImage('x', Rectangle::pt(0, 0, 1, 1), 1, 1, inkCoverage: 0)->inkCoverage);
    }

    /**
     * @return iterable<string, array{Closure(): object, string}>
     */
    public static function invalidInks(): iterable
    {
        yield 'image coverage' => [static fn (): object => new PlacedImage('photo', Rectangle::pt(0, 0, 1, 1), 1, 1, inkCoverage: -0.5), 'The ink coverage of "photo" cannot be negative; -0.5 % given.'];
        yield 'box coverage' => [static fn (): object => new Box('band', Rectangle::pt(0, 0, 1, 1), inkCoverage: -1), 'The ink coverage of "band" cannot be negative; -1 % given.'];
        yield 'line width' => [static fn (): object => new Box('rule', Rectangle::pt(0, 0, 1, 1), lineWidth: Length::pt(-0.1)), 'The line width of "rule" cannot be negative.'];
    }

    /**
     * @param Closure(): object $build
     */
    #[DataProvider('invalidInks')]
    public function testInkCoverageAndLineWidthCannotBeNegative(Closure $build, string $message): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    public function testAPageHasNoBleedByDefaultAndNeverANegativeOne(): void
    {
        self::assertSame(0.0, new Page(Size::a4())->bleed->points);
        self::assertSame(0.0, new Page(Size::a4(), Length::pt(0))->bleed->points);
        self::assertEqualsWithDelta(Length::mm(3)->points, new Page(Size::a4(), Length::mm(3))->bleed->points, 1e-9);

        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('The bleed of a page cannot be negative.');

        new Page(Size::a4(), Length::pt(-1));
    }

    public function testADocumentHasNoFontByDefault(): void
    {
        self::assertSame([], new Document([])->fonts);
    }

    public function testAFontIsReadFromPhilFonts(): void
    {
        $font = new Font(
            names: new Names([new NameRecord(3, 1, 0x0409, 1, 'Example Sans'), new NameRecord(3, 1, 0x0409, 4, 'Example Sans Bold')]),
            embedding: new EmbeddingPermissions(0x0002),
        );

        $used = UsedFont::fromFont($font, false, true);

        self::assertSame('Example Sans Bold', $used->name);
        self::assertSame(EmbeddingUsage::Restricted, $used->permissions->usage);
        self::assertFalse($used->embedded);
        self::assertTrue($used->subset);

        $plain = UsedFont::fromFont(new Font(names: new Names([new NameRecord(3, 1, 0x0409, 1, 'Example Serif')])));

        self::assertSame('Example Serif', $plain->name);
        self::assertTrue($plain->embedded);
        self::assertFalse($plain->subset);
        $default = new UsedFont('x');

        self::assertSame(EmbeddingUsage::Installable, $default->permissions->usage);
        self::assertTrue($default->embedded);
        self::assertFalse($default->subset);
    }

    /**
     * @return iterable<string, array{string, ColourSpace}>
     */
    public static function pdfColourSpaces(): iterable
    {
        yield 'device gray' => ['/DeviceGray', ColourSpace::Gray];
        yield 'calibrated gray' => ['CalGray', ColourSpace::Gray];
        yield 'device RGB' => ['/DeviceRGB', ColourSpace::Rgb];
        yield 'calibrated RGB' => ['CalRGB', ColourSpace::Rgb];
        yield 'device CMYK' => ['/DeviceCMYK', ColourSpace::Cmyk];
        yield 'separation' => ['/Separation', ColourSpace::Spot];
        yield 'device N' => ['DeviceN', ColourSpace::Spot];
    }

    #[DataProvider('pdfColourSpaces')]
    public function testPdfColourSpaceNamesAreRead(string $name, ColourSpace $expected): void
    {
        self::assertSame($expected, ColourSpace::fromPdf($name));
    }

    public function testAnUnknownPdfColourSpaceIsRefused(): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('The PDF colour space "/Lab" is not one the preflight knows.');

        ColourSpace::fromPdf('/Lab');
    }

    /**
     * @return iterable<string, array{Rule, Severity}>
     */
    public static function severities(): iterable
    {
        foreach (Rule::cases() as $rule) {
            $severity = match ($rule) {
                Rule::ImageBelowTarget, Rule::ColourSpot, Rule::PlacementOutsideTrim, Rule::PlacementInSafetyMargin,
                Rule::OverprintNotAllowed, Rule::LineHairline, Rule::TransparencyNotAllowed => Severity::Warning,
                Rule::ImageAboveMaximum, Rule::ColourRgbInCmyk, Rule::ColourCmykInRgb => Severity::Notice,
                default => Severity::Error,
            };

            yield $rule->value => [$rule, $severity];
        }
    }

    #[DataProvider('severities')]
    public function testEachRuleHasItsDefaultSeverity(Rule $rule, Severity $severity): void
    {
        self::assertSame($severity, $rule->severity());
    }

    public function testAFindingOfARuleTakesItsCodeAndSeverity(): void
    {
        $finding = Finding::of(Rule::ImageBelowTarget, 'Low.', 3, 'cover', ['resolution' => 200]);

        self::assertSame(Severity::Warning, $finding->severity);
        self::assertSame('image.below-target', $finding->code);
        self::assertSame([
            'severity' => 'warning',
            'code' => 'image.below-target',
            'message' => 'Low.',
            'page' => 3,
            'subject' => 'cover',
            'parameters' => ['resolution' => 200],
        ], $finding->toArray());

        $raised = $finding->withSeverity(Severity::Error);

        self::assertSame(Severity::Error, $raised->severity);
        self::assertSame(Severity::Warning, $finding->severity);
        self::assertSame(['severity' => 'error'] + $finding->toArray(), $raised->toArray());

        $plain = Finding::of(Rule::PageCountEmpty, 'None.');

        self::assertNull($plain->page);
        self::assertNull($plain->subject);
        self::assertSame([], $plain->parameters);
    }
}
