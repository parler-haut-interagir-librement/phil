<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate\Tests\Unit;

use Phil\CommerceProductTemplate\Axis;
use Phil\CommerceProductTemplate\AxisValue;
use Phil\CommerceProductTemplate\Exception\InvalidDefinition;
use Phil\CommerceProductTemplate\Face;
use Phil\CommerceProductTemplate\Internal\Check;
use Phil\CommerceProductTemplate\Label;
use Phil\CommerceProductTemplate\LanguageVersion;
use Phil\CommerceProductTemplate\PhotoBox;
use Phil\CommerceProductTemplate\PrintZone;
use Phil\CommerceProductTemplate\Swatch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Label::class)]
#[CoversClass(Swatch::class)]
#[CoversClass(AxisValue::class)]
#[CoversClass(Axis::class)]
#[CoversClass(PhotoBox::class)]
#[CoversClass(PrintZone::class)]
#[CoversClass(Face::class)]
#[CoversClass(LanguageVersion::class)]
#[CoversClass(Check::class)]
#[CoversClass(InvalidDefinition::class)]
final class ValuesTest extends TestCase
{
    public function testALabelReadsItsLocaleThenItsLanguageThenItsFirstText(): void
    {
        $label = Label::of(['fr' => 'Noir', 'en' => 'Black', 'fr_CA' => 'Noir foncé']);

        self::assertSame('Black', $label->in('en'));
        self::assertSame('Noir foncé', $label->in('fr_CA'));
        self::assertSame('Black', $label->in('en_GB'));
        self::assertSame('Noir', $label->in('de'));
        self::assertSame('Noir', $label->in());
        self::assertSame(['fr' => 'Noir', 'en' => 'Black', 'fr_CA' => 'Noir foncé'], $label->texts());
    }

    /**
     * @return iterable<string, array{array<array-key, string>, string}>
     */
    public static function refusedLabels(): iterable
    {
        yield 'no text' => [[], 'A label has its text in one locale at least.'];
        yield 'not a locale' => [['French' => 'Noir'], '"French" is not a locale: a language of 2 or 3 lowercase letters, then "_" and a region or script.'];
        yield 'a digit key' => [[0 => 'Noir'], '"0" is not a locale: a language of 2 or 3 lowercase letters, then "_" and a region or script.'];
        yield 'a locale with a line break' => [["fr\n" => 'Noir'], "\"fr\n\" is not a locale: a language of 2 or 3 lowercase letters, then \"_\" and a region or script."];
        yield 'a long language' => [['fren' => 'Noir'], '"fren" is not a locale: a language of 2 or 3 lowercase letters, then "_" and a region or script.'];
        yield 'a short region' => [['fr_C' => 'Noir'], '"fr_C" is not a locale: a language of 2 or 3 lowercase letters, then "_" and a region or script.'];
        yield 'a long region' => [['fr_ABCDEFGHI' => 'Noir'], '"fr_ABCDEFGHI" is not a locale: a language of 2 or 3 lowercase letters, then "_" and a region or script.'];
        yield 'blank' => [['fr' => '  '], 'The text of a label in fr holds 1 to 255 characters, not blank.'];
        yield 'too long' => [['fr' => str_repeat('é', 256)], 'The text of a label in fr holds 1 to 255 characters, not blank.'];
    }

    /**
     * @param array<array-key, string> $texts
     */
    #[DataProvider('refusedLabels')]
    public function testALabelRefusesWhatIsNotOne(array $texts, string $message): void
    {
        $this->expectExceptionObject(InvalidDefinition::because($message));

        Label::of($texts);
    }

    public function testALabelTakesTheLongestTextsAndLocales(): void
    {
        $label = Label::of(['zho_Hant_TW12345' => str_repeat('é', 255), 'fr' => 'x']);

        self::assertSame(str_repeat('é', 255), $label->in('zho_Hant_TW12345'));
    }

    public function testASwatchIsAColourInUppercaseOrAPhoto(): void
    {
        self::assertSame('#1A1A1A', Swatch::colour('#1a1a1a')->hex);
        self::assertNull(Swatch::colour('#1a1a1a')->photo);
        self::assertSame('#1A1A1A80', Swatch::colour('#1a1a1a80')->hex);
        self::assertSame('swatches/rainbow.jpg', Swatch::photo('swatches/rainbow.jpg')->photo);
        self::assertNull(Swatch::photo('swatches/rainbow.jpg')->hex);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedColours(): iterable
    {
        yield 'no hash' => ['1a1a1a'];
        yield 'short' => ['#1a1a1'];
        yield 'seven digits' => ['#1a1a1a8'];
        yield 'not hexadecimal' => ['#1a1a1g'];
        yield 'a line after' => ["#1a1a1a\n"];
        yield 'text before' => ['x#1a1a1a'];
    }

    #[DataProvider('refusedColours')]
    public function testASwatchRefusesWhatIsNotAColour(string $hex): void
    {
        $this->expectExceptionObject(InvalidDefinition::because(\sprintf('"%s" is not a colour: "#" and 6 or 8 hexadecimal digits.', $hex)));

        Swatch::colour($hex);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedPaths(): iterable
    {
        yield 'absolute' => ['/etc/passwd'];
        yield 'parent' => ['../secret.png'];
        yield 'parent inside' => ['a/../b.png'];
        yield 'current' => ['./a.png'];
        yield 'current inside' => ['a/./b.png'];
        yield 'parent at the end' => ['a/..'];
        yield 'empty segment' => ['a//b.png'];
        yield 'trailing slash' => ['a/'];
        yield 'a space' => ['a b.png'];
        yield 'a URL' => ['https://example.com/a.png'];
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('a', 513)];
        yield 'a line after' => ["a.png\n"];
    }

    #[DataProvider('refusedPaths')]
    public function testAPathStaysInsideTheStore(string $path): void
    {
        $this->expectExceptionObject(InvalidDefinition::because(\sprintf('"%s" is not the path of a swatch: up to 512 characters, segments of letters, digits, ".", "_" or "-", relative, never "." nor "..".', $path)));

        Swatch::photo($path);
    }

    public function testAPathMayHoldDotsInsideItsSegments(): void
    {
        self::assertSame('a/..b/c..d/.e/f.', Swatch::photo('a/..b/c..d/.e/f.')->photo);
        self::assertSame(str_repeat('a', 512), Swatch::photo(str_repeat('a', 512))->photo);
    }

    public function testAnAxisValueKeepsWhatItIsGiven(): void
    {
        $value = AxisValue::of('2X', Label::of(['en' => '2XL']), Swatch::colour('#000000'), 'black', ['front' => 'blanks/front.jpg'], ['web', 'web', 'shop']);

        self::assertSame('2X', $value->code);
        self::assertSame('2XL', $value->label->in());
        self::assertSame('#000000', $value->swatch?->hex);
        self::assertSame('black', $value->supplierName);
        self::assertSame(['front' => 'blanks/front.jpg'], $value->photos);
        self::assertSame('blanks/front.jpg', $value->photo('front'));
        self::assertNull($value->photo('back'));
        self::assertSame(['web', 'shop'], $value->channels);

        $value->checkFaces(['front']);
    }

    public function testAnAxisValueHasItsDefaults(): void
    {
        $value = AxisValue::of('M', Label::of(['en' => 'M']));

        self::assertNull($value->swatch);
        self::assertNull($value->supplierName);
        self::assertSame([], $value->photos);
        self::assertSame([], $value->channels);
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function refusedValues(): iterable
    {
        $label = Label::of(['en' => 'M']);

        yield 'an empty code' => [static fn (): AxisValue => AxisValue::of('', $label), '"" is not the code of an axis value: 1 to 16 letters, digits, ".", "_" or "-", starting with a letter or digit.'];
        yield 'a long code' => [static fn (): AxisValue => AxisValue::of(str_repeat('A', 17), $label), \sprintf('"%s" is not the code of an axis value: 1 to 16 letters, digits, ".", "_" or "-", starting with a letter or digit.', str_repeat('A', 17))];
        yield 'a code starting with a dash' => [static fn (): AxisValue => AxisValue::of('-M', $label), '"-M" is not the code of an axis value: 1 to 16 letters, digits, ".", "_" or "-", starting with a letter or digit.'];
        yield 'a code with a line after' => [static fn (): AxisValue => AxisValue::of("M\n", $label), "\"M\n\" is not the code of an axis value: 1 to 16 letters, digits, \".\", \"_\" or \"-\", starting with a letter or digit."];
        yield 'a blank supplier name' => [static fn (): AxisValue => AxisValue::of('M', $label, supplierName: ' '), 'The supplier name of M holds 1 to 255 characters, not blank.'];
        yield 'a photo of no face' => [static fn (): AxisValue => AxisValue::of('M', $label, photos: ['Front' => 'a.jpg']), '"Front" is not the code of a face: up to 32 lowercase letters, digits or "_", starting with a letter.'];
        yield 'a photo of a digit face' => [static fn (): AxisValue => AxisValue::of('M', $label, photos: [1 => 'a.jpg']), '"1" is not the code of a face: up to 32 lowercase letters, digits or "_", starting with a letter.'];
        yield 'a photo outside the store' => [static fn (): AxisValue => AxisValue::of('M', $label, photos: ['front' => '../a.jpg']), '"../a.jpg" is not the path of the front photo of M: up to 512 characters, segments of letters, digits, ".", "_" or "-", relative, never "." nor "..".'];
        yield 'a blank channel' => [static fn (): AxisValue => AxisValue::of('M', $label, channels: ['']), '"" is not a channel: 1 to 64 visible ASCII characters.'];
        yield 'a channel with a space' => [static fn (): AxisValue => AxisValue::of('M', $label, channels: ['a b']), '"a b" is not a channel: 1 to 64 visible ASCII characters.'];
        yield 'a long channel' => [static fn (): AxisValue => AxisValue::of('M', $label, channels: [str_repeat('c', 65)]), \sprintf('"%s" is not a channel: 1 to 64 visible ASCII characters.', str_repeat('c', 65))];
        yield 'a channel with a line after' => [static fn (): AxisValue => AxisValue::of('M', $label, channels: ["web\n"]), "\"web\n\" is not a channel: 1 to 64 visible ASCII characters."];
    }

    /**
     * @param callable(): mixed $build
     */
    #[DataProvider('refusedValues')]
    public function testAnAxisValueRefusesWhatIsNotOne(callable $build, string $message): void
    {
        $this->expectExceptionObject(InvalidDefinition::because($message));

        $build();
    }

    public function testAnAxisValueTakesTheLongestCodeAndChannel(): void
    {
        $value = AxisValue::of('A._-'.str_repeat('9', 12), Label::of(['en' => 'x']), channels: [str_repeat('c', 64), '!~']);

        self::assertSame('A._-999999999999', $value->code);
        self::assertSame([str_repeat('c', 64), '!~'], $value->channels);
    }

    public function testAnAxisValueRefusesAPhotoOfAFaceTheTemplateDoesNotHave(): void
    {
        $this->expectExceptionObject(InvalidDefinition::because('The value BK has a photo of the face back, which the template does not have.'));

        AxisValue::of('BK', Label::of(['en' => 'Black']), photos: ['front' => 'a.jpg', 'back' => 'b.jpg'])->checkFaces(['front']);
    }

    public function testAnAxisListsItsValuesInOrder(): void
    {
        $axis = Axis::of('size', Label::of(['en' => 'Size']), $m = AxisValue::of('M', Label::of(['en' => 'M'])), $l = AxisValue::of('L', Label::of(['en' => 'L'])));

        self::assertSame('size', $axis->code);
        self::assertSame('Size', $axis->label->in());
        self::assertSame([$m, $l], $axis->values());
        self::assertSame($l, $axis->value('L'));
        self::assertNull($axis->value('S'));
        self::assertTrue($axis->has('M'));
        self::assertFalse($axis->has('S'));
    }

    public function testAnAxisHoldsUpToItsLimitOfValues(): void
    {
        $values = array_map(static fn (int $index): AxisValue => AxisValue::of('V'.$index, Label::of(['en' => 'v'])), range(1, Axis::MAX_VALUES));

        self::assertCount(500, Axis::of('many', Label::of(['en' => 'Many']), ...$values)->values());

        $this->expectExceptionObject(InvalidDefinition::because('The axis many holds 1 to 500 values.'));

        Axis::of('many', Label::of(['en' => 'Many']), AxisValue::of('V0', Label::of(['en' => 'v'])), ...$values);
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function refusedAxes(): iterable
    {
        $label = Label::of(['en' => 'Axis']);
        $value = AxisValue::of('M', Label::of(['en' => 'M']));

        yield 'an uppercase code' => [static fn (): Axis => Axis::of('Size', $label, $value), '"Size" is not the code of an axis: up to 32 lowercase letters, digits or "_", starting with a letter.'];
        yield 'a code starting with a digit' => [static fn (): Axis => Axis::of('1size', $label, $value), '"1size" is not the code of an axis: up to 32 lowercase letters, digits or "_", starting with a letter.'];
        yield 'a long code' => [static fn (): Axis => Axis::of('a'.str_repeat('b', 32), $label, $value), \sprintf('"a%s" is not the code of an axis: up to 32 lowercase letters, digits or "_", starting with a letter.', str_repeat('b', 32))];
        yield 'a code with a line after' => [static fn (): Axis => Axis::of("size\n", $label, $value), "\"size\n\" is not the code of an axis: up to 32 lowercase letters, digits or \"_\", starting with a letter."];
        yield 'design' => [static fn (): Axis => Axis::of('design', $label, $value), '"design" names a part of the SKU of its own, not an axis.'];
        yield 'lang' => [static fn (): Axis => Axis::of('lang', $label, $value), '"lang" names a part of the SKU of its own, not an axis.'];
        yield 'template' => [static fn (): Axis => Axis::of('template', $label, $value), '"template" names a part of the SKU of its own, not an axis.'];
        yield 'no value' => [static fn (): Axis => Axis::of('size', $label), 'The axis size holds 1 to 500 values.'];
        yield 'a value twice' => [static fn (): Axis => Axis::of('size', $label, $value, $value), 'The axis size lists the value M twice.'];
    }

    /**
     * @param callable(): mixed $build
     */
    #[DataProvider('refusedAxes')]
    public function testAnAxisRefusesWhatIsNotOne(callable $build, string $message): void
    {
        $this->expectExceptionObject(InvalidDefinition::because($message));

        $build();
    }

    public function testAnAxisTakesTheLongestCode(): void
    {
        self::assertSame('a'.str_repeat('_', 31), Axis::of('a'.str_repeat('_', 31), Label::of(['en' => 'x']), AxisValue::of('M', Label::of(['en' => 'M'])))->code);
    }

    public function testAPhotoBoxStartsInsideItsPhotoAndHasAnArea(): void
    {
        $box = PhotoBox::of(0, 0, 1, 1);

        self::assertSame([0, 0, 1, 1], [$box->x, $box->y, $box->width, $box->height]);
    }

    /**
     * @return iterable<string, array{int, int, int, int, string}>
     */
    public static function refusedBoxes(): iterable
    {
        yield 'left of the photo' => [-1, 0, 1, 1, 'A photo box starts inside its photo, not at -1 × 0.'];
        yield 'above the photo' => [0, -1, 1, 1, 'A photo box starts inside its photo, not at 0 × -1.'];
        yield 'no width' => [0, 0, 0, 1, 'A photo box is at least one pixel wide and high, not 0 × 1.'];
        yield 'no height' => [0, 0, 1, 0, 'A photo box is at least one pixel wide and high, not 1 × 0.'];
    }

    #[DataProvider('refusedBoxes')]
    public function testAPhotoBoxRefusesWhatIsNotOne(int $x, int $y, int $width, int $height, string $message): void
    {
        $this->expectExceptionObject(InvalidDefinition::because($message));

        PhotoBox::of($x, $y, $width, $height);
    }

    public function testAPrintZoneKeepsItsMeasures(): void
    {
        $box = PhotoBox::of(1, 2, 3, 4);
        $zone = PrintZone::of('chest', 0, 0, 10_000, 0.5, 2400, $box);

        self::assertSame(['chest', 0.0, 0.0, 10_000.0, 0.5, 2400], [$zone->name, $zone->x, $zone->y, $zone->width, $zone->height, $zone->dpi]);
        self::assertSame($box, $zone->photoBox);

        $default = PrintZone::of('pocket', 1.5, 2.5, 3, 10_000);

        self::assertSame(300, $default->dpi);
        self::assertNull($default->photoBox);
        self::assertSame(72, PrintZone::of('low', 0, 0, 1, 1, 72)->dpi);
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function refusedZones(): iterable
    {
        yield 'a name with a capital' => [static fn (): PrintZone => PrintZone::of('Chest', 0, 0, 1, 1), '"Chest" is not the code of a print zone: up to 32 lowercase letters, digits or "_", starting with a letter.'];
        yield 'left of the face' => [static fn (): PrintZone => PrintZone::of('chest', -0.1, 0, 1, 1), 'The print zone chest starts on its face, at 0 mm or more.'];
        yield 'above the face' => [static fn (): PrintZone => PrintZone::of('chest', 0, -0.1, 1, 1), 'The print zone chest starts on its face, at 0 mm or more.'];
        yield 'no width' => [static fn (): PrintZone => PrintZone::of('chest', 0, 0, 0, 1), 'The print zone chest measures more than 0 and at most 10 000 mm each way.'];
        yield 'no height' => [static fn (): PrintZone => PrintZone::of('chest', 0, 0, 1, 0), 'The print zone chest measures more than 0 and at most 10 000 mm each way.'];
        yield 'too wide' => [static fn (): PrintZone => PrintZone::of('chest', 0, 0, 10_000.1, 1), 'The print zone chest measures more than 0 and at most 10 000 mm each way.'];
        yield 'too high' => [static fn (): PrintZone => PrintZone::of('chest', 0, 0, 1, 10_000.1), 'The print zone chest measures more than 0 and at most 10 000 mm each way.'];
        yield 'too coarse' => [static fn (): PrintZone => PrintZone::of('chest', 0, 0, 1, 1, 71), 'The print zone chest is printed at 72 to 2400 dpi, not 71.'];
        yield 'too fine' => [static fn (): PrintZone => PrintZone::of('chest', 0, 0, 1, 1, 2401), 'The print zone chest is printed at 72 to 2400 dpi, not 2401.'];
    }

    /**
     * @param callable(): mixed $build
     */
    #[DataProvider('refusedZones')]
    public function testAPrintZoneRefusesWhatIsNotOne(callable $build, string $message): void
    {
        $this->expectExceptionObject(InvalidDefinition::because($message));

        $build();
    }

    public function testAFaceHasItsZonesTheFirstTheDefault(): void
    {
        $face = Face::of('front', Label::of(['en' => 'Front']), $chest = PrintZone::of('chest', 0, 0, 1, 1), $pocket = PrintZone::of('pocket', 0, 0, 1, 1));

        self::assertSame('front', $face->code);
        self::assertSame('Front', $face->label->in());
        self::assertSame([$chest, $pocket], $face->zones());
        self::assertSame($chest, $face->zone());
        self::assertSame($pocket, $face->zone('pocket'));
        self::assertNull($face->zone('sleeve'));
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function refusedFaces(): iterable
    {
        $label = Label::of(['en' => 'Front']);
        $zone = PrintZone::of('chest', 0, 0, 1, 1);

        yield 'a code with a capital' => [static fn (): Face => Face::of('Front', $label, $zone), '"Front" is not the code of a face: up to 32 lowercase letters, digits or "_", starting with a letter.'];
        yield 'no zone' => [static fn (): Face => Face::of('front', $label), 'The face front has one print zone at least.'];
        yield 'a zone twice' => [static fn (): Face => Face::of('front', $label, $zone, $zone), 'The face front lists the zone chest twice.'];
    }

    /**
     * @param callable(): mixed $build
     */
    #[DataProvider('refusedFaces')]
    public function testAFaceRefusesWhatIsNotOne(callable $build, string $message): void
    {
        $this->expectExceptionObject(InvalidDefinition::because($message));

        $build();
    }

    public function testALanguageVersionHasItsCodeAndLocale(): void
    {
        $language = LanguageVersion::of('F', 'fr_CA');

        self::assertSame(['F', 'fr_CA'], [$language->code, $language->locale]);
        self::assertSame('ABCDEFGH', LanguageVersion::of('ABCDEFGH', 'en')->code);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function refusedLanguages(): iterable
    {
        yield 'a long code' => ['ABCDEFGHI', 'fr', '"ABCDEFGHI" is not the code of a language version: 1 to 8 letters, digits, ".", "_" or "-", starting with a letter or digit.'];
        yield 'not a locale' => ['F', 'French', '"French" is not a locale: a language of 2 or 3 lowercase letters, then "_" and a region or script.'];
        yield 'a locale with a line after' => ['F', "fr\n", "\"fr\n\" is not a locale: a language of 2 or 3 lowercase letters, then \"_\" and a region or script."];
        yield 'a locale with text before' => ['F', 'xfr_', '"xfr_" is not a locale: a language of 2 or 3 lowercase letters, then "_" and a region or script.'];
    }

    #[DataProvider('refusedLanguages')]
    public function testALanguageVersionRefusesWhatIsNotOne(string $code, string $locale, string $message): void
    {
        $this->expectExceptionObject(InvalidDefinition::because($message));

        LanguageVersion::of($code, $locale);
    }
}
