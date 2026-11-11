<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate\Tests\Unit\Fixture;

use Phil\CommerceProductTemplate\Artwork;
use Phil\CommerceProductTemplate\Axis;
use Phil\CommerceProductTemplate\AxisValue;
use Phil\CommerceProductTemplate\Design;
use Phil\CommerceProductTemplate\Exclusion;
use Phil\CommerceProductTemplate\Face;
use Phil\CommerceProductTemplate\Label;
use Phil\CommerceProductTemplate\LanguageVersion;
use Phil\CommerceProductTemplate\PhotoBox;
use Phil\CommerceProductTemplate\Pricing;
use Phil\CommerceProductTemplate\PrintZone;
use Phil\CommerceProductTemplate\SkuPattern;
use Phil\CommerceProductTemplate\Swatch;
use Phil\CommerceProductTemplate\Template;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * A shirt template and a design in two languages, the example the documentation follows.
 */
final class Shirts
{
    public static function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }

    public static function template(string $pattern = '{design}{lang}{version}{size}{colour}'): Template
    {
        return Template::of(
            'unisex',
            Label::of(['en' => 'Unisex shirt', 'fr' => 'T-shirt unisexe']),
            SkuPattern::parse($pattern),
            Pricing::of(self::cad(2_500))
                ->withPrice('size', '2X', self::cad(2_800))
                ->withSupplement('version', 'L', self::cad(1_000))
                ->withSupplement('version', 'C', self::cad(1_000)),
            [
                Axis::of(
                    'version',
                    Label::of(['en' => 'Version']),
                    AxisValue::of('O', Label::of(['en' => 'Regular', 'fr' => 'Ordinaire'])),
                    AxisValue::of('L', Label::of(['en' => 'Loot box'])),
                    AxisValue::of('C', Label::of(['en' => 'Convention']), channels: ['convention']),
                ),
                Axis::of(
                    'size',
                    Label::of(['en' => 'Size', 'fr' => 'Taille']),
                    AxisValue::of('M', Label::of(['en' => 'M'])),
                    AxisValue::of('2X', Label::of(['en' => '2XL', 'fr' => 'TTG/2XL'])),
                ),
                Axis::of(
                    'colour',
                    Label::of(['en' => 'Colour', 'fr' => 'Couleur']),
                    AxisValue::of('BK', Label::of(['en' => 'Black', 'fr' => 'Noir']), Swatch::colour('#1a1a1a'), 'black', ['front' => 'blanks/front-bk.jpg', 'back' => 'blanks/back-bk.jpg']),
                    AxisValue::of('PK', Label::of(['en' => 'Pink', 'fr' => 'Rose']), Swatch::colour('#F4A7C0'), 'azalea', ['front' => 'blanks/front-pk.jpg']),
                ),
            ],
            [
                Face::of(
                    'front',
                    Label::of(['en' => 'Front', 'fr' => 'Avant']),
                    PrintZone::of('chest', 50, 60, 300, 400, photoBox: PhotoBox::of(30, 40, 30, 40)),
                    PrintZone::of('pocket', 200, 60, 80, 80, 600),
                ),
                Face::of('back', Label::of(['en' => 'Back']), PrintZone::of('full', 50, 80, 300, 450)),
            ],
            [Exclusion::of(['colour' => ['PK'], 'size' => ['2X']])],
            'Supplier 64000',
        );
    }

    public static function design(): Design
    {
        return Design::of(
            'NAQ001',
            Label::of(['fr' => 'Le gnome', 'en' => 'The gnome']),
            [LanguageVersion::of('F', 'fr'), LanguageVersion::of('E', 'en')],
            [
                Artwork::of('front', 'art/naq001-front.png'),
                Artwork::of('front', 'art/naq001-front-en.png', language: 'E'),
                Artwork::of('front', 'art/naq001-front-con.png', when: ['version' => ['C']]),
                Artwork::of('back', 'art/naq001-back.png', 'full'),
            ],
            ['unisex'],
            ['version' => ['O', 'C']],
        );
    }
}
