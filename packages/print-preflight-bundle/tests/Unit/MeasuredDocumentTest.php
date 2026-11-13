<?php

declare(strict_types=1);

namespace Phil\PrintPreflightBundle\Tests\Unit;

use Phil\Fonts\FontStyle;
use Phil\PrintPreflight\ColourSpace;
use Phil\PrintPreflight\Size;
use Phil\PrintPreflightBundle\Application\Exception\InvalidMeasurement;
use Phil\PrintPreflightBundle\Application\MeasuredDocument;
use Phil\PrintPreflightBundle\Tests\Unit\Fixture\RecordingResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MeasuredDocument::class)]
final class MeasuredDocumentTest extends TestCase
{
    public function testReadsADocumentMeasuredInCssPixels(): void
    {
        $resolver = new RecordingResolver();
        $document = new MeasuredDocument($resolver)->fromJson(json_encode([
            'pages' => [
                [
                    'width' => 816,
                    'height' => 1056.0,
                    'bleed' => 12,
                    'images' => [
                        ['id' => 'cover', 'x' => -12, 'y' => -12, 'width' => 840, 'height' => 1080, 'naturalWidth' => 2550, 'naturalHeight' => 3300.0, 'colourSpace' => 'cmyk', 'bleeds' => true],
                        ['id' => 'portrait', 'x' => 96, 'y' => 96, 'width' => 96, 'height' => 96, 'naturalWidth' => 300, 'naturalHeight' => 400, 'scaledWidth' => 96, 'scaledHeight' => 128],
                    ],
                    'boxes' => [
                        ['id' => 'title', 'x' => 48, 'y' => 48, 'width' => 480, 'height' => 64, 'colourSpace' => 'spot'],
                        ['id' => 'band', 'x' => -12, 'y' => 0, 'width' => 840, 'height' => 24, 'bleeds' => true],
                    ],
                ],
                ['width' => 816, 'height' => 1056],
            ],
            'fonts' => [
                ['name' => 'Example Sans Bold', 'fsType' => 0x0100, 'subset' => true],
                ['name' => 'Fallback Serif', 'embedded' => false],
                ['family' => 'Example Serif', 'weight' => 700, 'style' => 'italic', 'subset' => true],
                ['family' => 'Example Mono'],
            ],
        ], \JSON_THROW_ON_ERROR));

        [$first, $second] = $document->pages;
        self::assertTrue($first->trim->matches(Size::letter()));
        self::assertSame(9.0, $first->bleed->points);
        self::assertSame(0.0, $second->bleed->points);
        self::assertSame([], $second->images);
        self::assertSame([], $second->boxes);

        [$cover, $portrait] = $first->images;
        self::assertSame('cover', $cover->id);
        self::assertSame([-9.0, -9.0, 630.0, 810.0], [$cover->frame->x->points, $cover->frame->y->points, $cover->frame->width->points, $cover->frame->height->points]);
        self::assertSame([2550, 3300], [$cover->pixelWidth, $cover->pixelHeight]);
        self::assertSame(ColourSpace::Cmyk, $cover->colourSpace);
        self::assertTrue($cover->bleeds);
        self::assertNull($cover->scaledTo);
        self::assertSame(ColourSpace::Rgb, $portrait->colourSpace);
        self::assertFalse($portrait->bleeds);
        self::assertSame([72.0, 96.0], [$portrait->scaledTo?->width->points, $portrait->scaledTo?->height->points]);

        [$title, $band] = $first->boxes;
        self::assertSame('title', $title->id);
        self::assertSame([36.0, 36.0, 360.0, 48.0], [$title->frame->x->points, $title->frame->y->points, $title->frame->width->points, $title->frame->height->points]);
        self::assertSame(ColourSpace::Spot, $title->colourSpace);
        self::assertFalse($title->bleeds);
        self::assertNull($band->colourSpace);
        self::assertTrue($band->bleeds);

        [$sans, $fallback, $serif, $mono] = $document->fonts;
        self::assertSame(['Example Sans Bold', 0x0100, true, true], [$sans->name, $sans->permissions->fsType, $sans->embedded, $sans->subset]);
        self::assertSame(['Fallback Serif', 0, false, false], [$fallback->name, $fallback->permissions->fsType, $fallback->embedded, $fallback->subset]);
        self::assertSame(['Example Serif', true, true], [$serif->name, $serif->embedded, $serif->subset]);
        self::assertSame('Example Mono', $mono->name);
        self::assertSame([['Example Serif', 700, FontStyle::Italic, true, true], ['Example Mono', 400, FontStyle::Normal, true, false]], $resolver->asked);
    }

    public function testReadsInkOverprintLineWidthAndTransparency(): void
    {
        $document = new MeasuredDocument()->fromArray([
            'unit' => 'mm',
            'pages' => [[
                'width' => 210,
                'height' => 297,
                'images' => [
                    ['id' => 'photo', 'x' => 10, 'y' => 10, 'width' => 50, 'height' => 50, 'naturalWidth' => 600, 'naturalHeight' => 600, 'inkCoverage' => 312.5, 'transparency' => true],
                    ['id' => 'plain', 'x' => 10, 'y' => 70, 'width' => 50, 'height' => 50, 'naturalWidth' => 600, 'naturalHeight' => 600, 'inkCoverage' => null],
                ],
                'boxes' => [
                    ['id' => 'rule', 'x' => 10, 'y' => 130, 'width' => 190, 'height' => 1, 'inkCoverage' => 100, 'overprint' => true, 'lineWidth' => 0.1, 'transparency' => true],
                    ['id' => 'text', 'x' => 10, 'y' => 140, 'width' => 190, 'height' => 20, 'lineWidth' => 0],
                    ['id' => 'unknown', 'x' => 10, 'y' => 170, 'width' => 190, 'height' => 20, 'inkCoverage' => null, 'lineWidth' => null],
                ],
            ]],
        ]);

        [$photo, $plain] = $document->pages[0]->images;
        self::assertSame(312.5, $photo->inkCoverage);
        self::assertTrue($photo->transparency);
        self::assertNull($plain->inkCoverage);
        self::assertFalse($plain->transparency);

        [$rule, $text, $unknown] = $document->pages[0]->boxes;
        self::assertSame(100.0, $rule->inkCoverage);
        self::assertTrue($rule->overprint);
        self::assertEqualsWithDelta(0.2835, $rule->lineWidth?->points, 0.0001);
        self::assertTrue($rule->transparency);
        self::assertNull($text->inkCoverage);
        self::assertFalse($text->overprint);
        self::assertSame(0.0, $text->lineWidth?->points);
        self::assertFalse($text->transparency);
        self::assertNull($unknown->inkCoverage);
        self::assertNull($unknown->lineWidth);
    }

    public function testOtherUnits(): void
    {
        $document = new MeasuredDocument()->fromArray(['unit' => 'mm', 'pages' => [['width' => 148, 'height' => 210, 'bleed' => 3]]]);

        self::assertTrue($document->pages[0]->trim->matches(Size::a5()));
        self::assertEqualsWithDelta(3.0, $document->pages[0]->bleed->millimetres(), 0.0001);
        self::assertSame([], $document->fonts);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalid(): iterable
    {
        $page = ['width' => 100, 'height' => 100];
        $image = ['id' => 'i', 'x' => 0, 'y' => 0, 'width' => 10, 'height' => 10, 'naturalWidth' => 10, 'naturalHeight' => 10];
        $box = ['id' => 'b', 'x' => 0, 'y' => 0, 'width' => 10, 'height' => 10];

        yield 'not an object' => ['"pages"', 'The "pages" of the document must be a list of objects.'];
        yield 'no pages' => [[], 'The "pages" of the document must be a list of objects.'];
        yield 'pages not a list' => [['pages' => ['a' => $page]], 'The "pages" of the document must be a list of objects.'];
        yield 'a page not an object' => [['pages' => [$page, 3]], 'The "pages" of the document must be a list of objects.'];
        yield 'unit not a string' => [['unit' => 3, 'pages' => [$page]], 'The "unit" of the document must be a string.'];
        yield 'unknown unit' => [['unit' => 'cm', 'pages' => [$page]], 'The units are mm, in, pt and px; "cm" is not one.'];
        yield 'width not a number' => [['pages' => [['width' => '100', 'height' => 100]]], 'The "width" of a page must be a number.'];
        yield 'no height' => [['pages' => [['width' => 100]]], 'The "height" of a page must be a number.'];
        yield 'negative bleed' => [['pages' => [[...$page, 'bleed' => -1]]], 'The bleed of a page cannot be negative.'];
        yield 'images not a list' => [['pages' => [[...$page, 'images' => 'none']]], 'The "images" of a page must be a list of objects.'];
        yield 'image without id' => [['pages' => [[...$page, 'images' => [[...$image, 'id' => null]]]]], 'The "id" of an image must be a string.'];
        yield 'image without x' => [['pages' => [[...$page, 'images' => [[...$image, 'x' => null]]]]], 'The "x" of an image must be a number.'];
        yield 'image without natural width' => [['pages' => [[...$page, 'images' => [[...$image, 'naturalWidth' => 'big']]]]], 'The "naturalWidth" of an image must be a number.'];
        yield 'image of no pixel' => [['pages' => [[...$page, 'images' => [[...$image, 'naturalHeight' => 0]]]]], 'The image "i" must have at least one pixel each way; 10 × 0 given.'];
        yield 'image bleeds not boolean' => [['pages' => [[...$page, 'images' => [[...$image, 'bleeds' => 1]]]]], 'The "bleeds" of an image must be true or false.'];
        yield 'image colour space unknown' => [['pages' => [[...$page, 'images' => [[...$image, 'colourSpace' => 'lab']]]]], 'The colour space of an image is gray, rgb, cmyk or spot.'];
        yield 'image colour space not a string' => [['pages' => [[...$page, 'images' => [[...$image, 'colourSpace' => 3]]]]], 'The "colourSpace" of an image must be a string.'];
        yield 'image scaled height missing' => [['pages' => [[...$page, 'images' => [[...$image, 'scaledWidth' => 20]]]]], 'The "scaledHeight" of an image must be a number.'];
        yield 'image scaled width missing' => [['pages' => [[...$page, 'images' => [[...$image, 'scaledHeight' => 20]]]]], 'The "scaledWidth" of an image must be a number.'];
        yield 'box without height' => [['pages' => [[...$page, 'boxes' => [[...$box, 'height' => null]]]]], 'The "height" of a box must be a number.'];
        yield 'box without y' => [['pages' => [[...$page, 'boxes' => [[...$box, 'y' => null]]]]], 'The "y" of a box must be a number.'];
        yield 'box without width' => [['pages' => [[...$page, 'boxes' => [[...$box, 'width' => null]]]]], 'The "width" of a box must be a number.'];
        yield 'box without id' => [['pages' => [[...$page, 'boxes' => [[...$box, 'id' => 1]]]]], 'The "id" of a box must be a string.'];
        yield 'box colour space unknown' => [['pages' => [[...$page, 'boxes' => [[...$box, 'colourSpace' => 'lab']]]]], 'The colour space of a box is gray, rgb, cmyk or spot.'];
        yield 'image ink coverage not a number' => [['pages' => [[...$page, 'images' => [[...$image, 'inkCoverage' => '300%']]]]], 'The "inkCoverage" of an image must be a number.'];
        yield 'image negative ink coverage' => [['pages' => [[...$page, 'images' => [[...$image, 'inkCoverage' => -1]]]]], 'The ink coverage of "i" cannot be negative; -1 % given.'];
        yield 'image transparency not boolean' => [['pages' => [[...$page, 'images' => [[...$image, 'transparency' => 'yes']]]]], 'The "transparency" of an image must be true or false.'];
        yield 'box ink coverage not a number' => [['pages' => [[...$page, 'boxes' => [[...$box, 'inkCoverage' => '280']]]]], 'The "inkCoverage" of a box must be a number.'];
        yield 'box negative ink coverage' => [['pages' => [[...$page, 'boxes' => [[...$box, 'inkCoverage' => -0.5]]]]], 'The ink coverage of "b" cannot be negative; -0.5 % given.'];
        yield 'box overprint not boolean' => [['pages' => [[...$page, 'boxes' => [[...$box, 'overprint' => 1]]]]], 'The "overprint" of a box must be true or false.'];
        yield 'box line width not a number' => [['pages' => [[...$page, 'boxes' => [[...$box, 'lineWidth' => '1px']]]]], 'The "lineWidth" of a box must be a number.'];
        yield 'box negative line width' => [['pages' => [[...$page, 'boxes' => [[...$box, 'lineWidth' => -1]]]]], 'The line width of "b" cannot be negative.'];
        yield 'box transparency not boolean' => [['pages' => [[...$page, 'boxes' => [[...$box, 'transparency' => 'no']]]]], 'The "transparency" of a box must be true or false.'];
        yield 'box bleeds not boolean' => [['pages' => [[...$page, 'boxes' => [[...$box, 'bleeds' => 'yes']]]]], 'The "bleeds" of a box must be true or false.'];
        yield 'fonts not a list' => [['pages' => [$page], 'fonts' => 'none'], 'The "fonts" of the document must be a list of objects.'];
        yield 'font without name' => [['pages' => [$page], 'fonts' => [['fsType' => 0]]], 'The "name" of a font must be a string.'];
        yield 'font fsType not a number' => [['pages' => [$page], 'fonts' => [['name' => 'A', 'fsType' => 'none']]], 'The "fsType" of a font must be a number.'];
        yield 'font embedded not boolean' => [['pages' => [$page], 'fonts' => [['name' => 'A', 'embedded' => 1]]], 'The "embedded" of a font must be true or false.'];
        yield 'font subset not boolean' => [['pages' => [$page], 'fonts' => [['name' => 'A', 'subset' => 1]]], 'The "subset" of a font must be true or false.'];
    }

    #[DataProvider('invalid')]
    public function testRefusesWhatItCannotRead(mixed $data, string $message): void
    {
        $this->expectException(InvalidMeasurement::class);
        $this->expectExceptionMessageIsOrContains($message);

        new MeasuredDocument(new RecordingResolver())->fromJson(json_encode($data, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidFamilies(): iterable
    {
        yield 'family not a string' => [['family' => 3], 'The "family" of a font must be a string.'];
        yield 'weight not a number' => [['family' => 'A', 'weight' => 'bold'], 'The "weight" of a font must be a number.'];
        yield 'style not a string' => [['family' => 'A', 'style' => 1], 'The "style" of a font must be a string.'];
        yield 'unknown style' => [['family' => 'A', 'style' => 'slanted'], 'The style of a font is normal, italic or oblique.'];
    }

    /**
     * @param array<string, mixed> $font
     */
    #[DataProvider('invalidFamilies')]
    public function testRefusesAFamilyItCannotRead(array $font, string $message): void
    {
        $this->expectException(InvalidMeasurement::class);
        $this->expectExceptionMessageIsOrContains($message);

        new MeasuredDocument(new RecordingResolver())->fromArray(['pages' => [['width' => 100, 'height' => 100]], 'fonts' => [$font]]);
    }

    public function testAFamilyNeedsAResolver(): void
    {
        $this->expectException(InvalidMeasurement::class);
        $this->expectExceptionMessageIsOrContains('A font named by its family needs a font resolver, such as the catalogue of phil/fonts-bundle; name it with "name" and "fsType" otherwise.');

        new MeasuredDocument()->fromArray(['pages' => [['width' => 100, 'height' => 100]], 'fonts' => [['family' => 'Example Sans']]]);
    }

    public function testRefusesWhatIsNotJson(): void
    {
        $this->expectException(InvalidMeasurement::class);
        $this->expectExceptionMessageIsOrContains('The measured document is not valid JSON: Syntax error');

        new MeasuredDocument()->fromJson('{');
    }
}
