<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate\Tests\Unit;

use Phil\CommerceProductTemplate\Axis;
use Phil\CommerceProductTemplate\AxisValue;
use Phil\CommerceProductTemplate\DesignArray;
use Phil\CommerceProductTemplate\Exception\InvalidDefinition;
use Phil\CommerceProductTemplate\Internal\Reader;
use Phil\CommerceProductTemplate\Label;
use Phil\CommerceProductTemplate\Pricing;
use Phil\CommerceProductTemplate\SkuPattern;
use Phil\CommerceProductTemplate\Swatch;
use Phil\CommerceProductTemplate\Template;
use Phil\CommerceProductTemplate\TemplateArray;
use Phil\CommerceProductTemplate\Tests\Unit\Fixture\Shirts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TemplateArray::class)]
#[CoversClass(DesignArray::class)]
#[CoversClass(Reader::class)]
final class ArraysTest extends TestCase
{
    public function testATemplateIsWrittenAsScalars(): void
    {
        $array = TemplateArray::toArray(Shirts::template());

        self::assertSame(1, $array['version']);
        self::assertSame('unisex', $array['code']);
        self::assertSame(['en' => 'Unisex shirt', 'fr' => 'T-shirt unisexe'], $array['name']);
        self::assertSame('{design}{lang}{version}{size}{colour}', $array['sku_pattern']);
        self::assertSame('Supplier 64000', $array['supplier']);
        self::assertSame(['currency' => 'CAD', 'base' => 2_500, 'prices' => ['size' => ['2X' => 2_800]], 'supplements' => ['version' => ['L' => 1_000, 'C' => 1_000]]], $array['pricing']);
        self::assertSame([['colour' => ['PK'], 'size' => ['2X']]], $array['exclusions']);
        self::assertIsArray($array['axes']);
        self::assertSame([
            'code' => 'colour',
            'label' => ['en' => 'Colour', 'fr' => 'Couleur'],
            'values' => [
                ['code' => 'BK', 'label' => ['en' => 'Black', 'fr' => 'Noir'], 'swatch' => ['hex' => '#1A1A1A', 'photo' => null], 'supplier_name' => 'black', 'photos' => ['front' => 'blanks/front-bk.jpg', 'back' => 'blanks/back-bk.jpg'], 'channels' => []],
                ['code' => 'PK', 'label' => ['en' => 'Pink', 'fr' => 'Rose'], 'swatch' => ['hex' => '#F4A7C0', 'photo' => null], 'supplier_name' => 'azalea', 'photos' => ['front' => 'blanks/front-pk.jpg'], 'channels' => []],
            ],
        ], $array['axes'][2]);
        $version = $array['axes'][0];
        self::assertIsArray($version);
        self::assertIsArray($version['values']);
        self::assertSame(['code' => 'C', 'label' => ['en' => 'Convention'], 'swatch' => null, 'supplier_name' => null, 'photos' => [], 'channels' => ['convention']], $version['values'][2]);
        self::assertIsArray($array['faces']);
        self::assertSame([
            'code' => 'front',
            'label' => ['en' => 'Front', 'fr' => 'Avant'],
            'zones' => [
                ['name' => 'chest', 'x' => 50.0, 'y' => 60.0, 'width' => 300.0, 'height' => 400.0, 'dpi' => 300, 'photo_box' => ['x' => 30, 'y' => 40, 'width' => 30, 'height' => 40]],
                ['name' => 'pocket', 'x' => 200.0, 'y' => 60.0, 'width' => 80.0, 'height' => 80.0, 'dpi' => 600, 'photo_box' => null],
            ],
        ], $array['faces'][0]);
    }

    public function testATemplateReadsBackThroughJson(): void
    {
        $template = Template::of('poster', Label::of(['en' => 'Poster']), SkuPattern::parse('{design}-{format}'), Pricing::of(Shirts::cad(1_000))->withPrice('format', '0', Shirts::cad(900))->withSupplement('format', '1', Shirts::cad(100)), [
            Axis::of('format', Label::of(['en' => 'Format']), AxisValue::of('0', Label::of(['en' => 'A0'])), AxisValue::of('1', Label::of(['en' => 'A1']), Swatch::photo('swatches/a1.jpg'), channels: ['web', 'shop'])),
        ]);

        foreach ([$template, Shirts::template()] as $original) {
            $json = json_decode(json_encode(TemplateArray::toArray($original), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
            self::assertIsArray($json);

            self::assertEquals($original, TemplateArray::fromArray($json));
        }
    }

    public function testADesignReadsBackThroughJson(): void
    {
        $array = DesignArray::toArray(Shirts::design());

        self::assertSame([
            'version' => 1,
            'code' => 'NAQ001',
            'name' => ['fr' => 'Le gnome', 'en' => 'The gnome'],
            'languages' => [['code' => 'F', 'locale' => 'fr'], ['code' => 'E', 'locale' => 'en']],
            'artworks' => [
                ['face' => 'front', 'path' => 'art/naq001-front.png', 'zone' => null, 'language' => null, 'when' => []],
                ['face' => 'front', 'path' => 'art/naq001-front-en.png', 'zone' => null, 'language' => 'E', 'when' => []],
                ['face' => 'front', 'path' => 'art/naq001-front-con.png', 'zone' => null, 'language' => null, 'when' => ['version' => ['C']]],
                ['face' => 'back', 'path' => 'art/naq001-back.png', 'zone' => 'full', 'language' => null, 'when' => []],
            ],
            'templates' => ['unisex'],
            'allowed' => ['version' => ['O', 'C']],
        ], $array);

        $json = json_decode(json_encode($array, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($json);
        self::assertEquals(Shirts::design(), DesignArray::fromArray($json));
    }

    /**
     * @return iterable<string, array{callable(array<string, mixed>): array<array-key, mixed>, string}>
     */
    public static function refusedTemplates(): iterable
    {
        yield 'another version' => [static fn (array $a): array => ['version' => 2] + $a, 'template is written in version 2; this library reads version 1.'];
        yield 'no version' => [static fn (array $a): array => ['version' => '1'] + $a, 'template.version is not an integer.'];
        yield 'a code of no string' => [static fn (array $a): array => ['code' => 1] + $a, 'template.code is not a string.'];
        yield 'a supplier of no string' => [static fn (array $a): array => ['supplier' => 1] + $a, 'template.supplier is not a string or null.'];
        yield 'no pricing' => [static fn (array $a): array => ['pricing' => 'x'] + $a, 'template.pricing is not an object.'];
        yield 'an unknown currency' => [static fn (array $a): array => self::with($a, 'pricing', ['currency' => 'XXZ']), 'template.pricing is not a price: '];
        yield 'a base of no integer' => [static fn (array $a): array => self::with($a, 'pricing', ['base' => 25.0]), 'template.pricing.base is not an integer.'];
        yield 'prices of no object' => [static fn (array $a): array => self::with($a, 'pricing', ['prices' => 'x']), 'template.pricing.prices is not an object.'];
        yield 'prices of an axis of no code' => [static fn (array $a): array => self::with($a, 'pricing', ['prices' => [0 => ['M' => 1]]]), 'template.pricing.prices names its axes by their codes.'];
        yield 'prices of an axis of no object' => [static fn (array $a): array => self::with($a, 'pricing', ['prices' => ['size' => 1]]), 'template.pricing.prices.size is not an object.'];
        yield 'a price of no integer' => [static fn (array $a): array => self::with($a, 'pricing', ['supplements' => ['version' => ['L' => '1000']]]), 'template.pricing.supplements.version.L is not an integer.'];
        yield 'a name of no strings' => [static fn (array $a): array => ['name' => ['en' => 1]] + $a, 'template.name.en is not a string.'];
        yield 'axes of no list' => [static fn (array $a): array => ['axes' => ['size' => []]] + $a, 'template.axes is not a list.'];
        yield 'an axis of no object' => [static fn (array $a): array => ['axes' => ['size']] + $a, 'template.axes[0] is not an object.'];
        yield 'a value of no object' => [static fn (array $a): array => ['axes' => [['code' => 'size', 'label' => ['en' => 'Size'], 'values' => ['M']]]] + $a, 'template.axes[0].values[0] is not an object.'];
        yield 'channels of no strings' => [static fn (array $a): array => ['axes' => [['code' => 'size', 'label' => ['en' => 'Size'], 'values' => [['code' => 'M', 'label' => ['en' => 'M'], 'swatch' => null, 'supplier_name' => null, 'photos' => [], 'channels' => [1]]]]]] + $a, 'template.axes[0].values[0].channels[0] is not a string.'];
        yield 'a swatch of no object' => [static fn (array $a): array => ['axes' => [['code' => 'size', 'label' => ['en' => 'Size'], 'values' => [['code' => 'M', 'label' => ['en' => 'M'], 'swatch' => 'red', 'supplier_name' => null, 'photos' => [], 'channels' => []]]]]] + $a, 'template.axes[0].values[0].swatch is not an object.'];
        yield 'a zone of no number' => [static fn (array $a): array => ['faces' => [['code' => 'front', 'label' => ['en' => 'Front'], 'zones' => [['name' => 'chest', 'x' => '0', 'y' => 0, 'width' => 1, 'height' => 1, 'dpi' => 300, 'photo_box' => null]]]]] + $a, 'template.faces[0].zones[0].x is not a number.'];
        yield 'an exclusion of no object' => [static fn (array $a): array => ['exclusions' => ['PK']] + $a, 'template.exclusions lists objects.'];
    }

    /**
     * @param callable(array<string, mixed>): array<array-key, mixed> $change
     */
    #[DataProvider('refusedTemplates')]
    public function testATemplateArrayRefusesWhatNoCodeWrote(callable $change, string $message): void
    {
        try {
            TemplateArray::fromArray($change(TemplateArray::toArray(Shirts::template())));
            self::fail('The array is refused.');
        } catch (InvalidDefinition $invalidDefinition) {
            self::assertTrue(str_starts_with($invalidDefinition->getMessage(), $message), $invalidDefinition->getMessage());
        }
    }

    public function testAZoneMeasuredInWholeMillimetresReadsBack(): void
    {
        $array = TemplateArray::toArray(Shirts::template());
        $array['faces'] = [['code' => 'front', 'label' => ['en' => 'Front'], 'zones' => [['name' => 'chest', 'x' => 1, 'y' => 2.5, 'width' => 3, 'height' => 4, 'dpi' => 300, 'photo_box' => null]]]];
        $array['axes'] = [['code' => 'version', 'label' => ['en' => 'v'], 'values' => [['code' => 'O', 'label' => ['en' => 'O'], 'swatch' => ['hex' => null, 'photo' => 's.jpg'], 'supplier_name' => null, 'photos' => [], 'channels' => []]]], ['code' => 'size', 'label' => ['en' => 's'], 'values' => [['code' => '2X', 'label' => ['en' => '2X'], 'swatch' => null, 'supplier_name' => null, 'photos' => [], 'channels' => []]]], ['code' => 'colour', 'label' => ['en' => 'c'], 'values' => [['code' => 'PK', 'label' => ['en' => 'PK'], 'swatch' => null, 'supplier_name' => null, 'photos' => [], 'channels' => []]]]];
        $array['pricing'] = ['currency' => 'CAD', 'base' => 1, 'prices' => [], 'supplements' => []];
        $array['exclusions'] = [];
        $template = TemplateArray::fromArray($array);
        $zone = $template->face('front')?->zone();

        self::assertSame([1.0, 2.5, 3.0, 4.0], [$zone?->x, $zone?->y, $zone?->width, $zone?->height]);
        self::assertSame('s.jpg', $template->axis('version')?->value('O')?->swatch?->photo);
    }

    /**
     * @return iterable<string, array{callable(array<string, mixed>): array<array-key, mixed>, string}>
     */
    public static function refusedDesigns(): iterable
    {
        yield 'another version' => [static fn (array $a): array => ['version' => 0] + $a, 'design is written in version 0; this library reads version 1.'];
        yield 'languages of no list' => [static fn (array $a): array => ['languages' => 'F'] + $a, 'design.languages is not a list.'];
        yield 'a locale of no string' => [static fn (array $a): array => ['languages' => [['code' => 'F', 'locale' => null]]] + $a, 'design.languages[0].locale is not a string.'];
        yield 'a zone of no string' => [static fn (array $a): array => ['artworks' => [['face' => 'front', 'path' => 'a.png', 'zone' => 1, 'language' => null, 'when' => []]]] + $a, 'design.artworks[0].zone is not a string or null.'];
        yield 'a condition of no object' => [static fn (array $a): array => ['artworks' => [['face' => 'front', 'path' => 'a.png', 'zone' => null, 'language' => null, 'when' => 'x']]] + $a, 'design.artworks[0].when is not an object.'];
        yield 'templates of no strings' => [static fn (array $a): array => ['templates' => [1]] + $a, 'design.templates[0] is not a string.'];
        yield 'allowed values of no object' => [static fn (array $a): array => ['allowed' => null] + $a, 'design.allowed is not an object.'];
    }

    /**
     * @param callable(array<string, mixed>): array<array-key, mixed> $change
     */
    #[DataProvider('refusedDesigns')]
    public function testADesignArrayRefusesWhatNoCodeWrote(callable $change, string $message): void
    {
        $this->expectExceptionObject(InvalidDefinition::because($message));

        DesignArray::fromArray($change(DesignArray::toArray(Shirts::design())));
    }

    /**
     * @param array<array-key, mixed> $array
     * @param array<string, mixed>    $values
     *
     * @return array<array-key, mixed>
     */
    private static function with(array $array, string $key, array $values): array
    {
        $inner = $array[$key];
        self::assertIsArray($inner);
        $array[$key] = $values + $inner;

        return $array;
    }
}
