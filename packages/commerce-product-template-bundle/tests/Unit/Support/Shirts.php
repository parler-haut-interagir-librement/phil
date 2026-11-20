<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Tests\Unit\Support;

use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
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
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductDesign;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductTemplate;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * A shirt template — versions, sizes, colours, pink not made in 2XL — and a design in two languages.
 */
final class Shirts
{
    public static function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }

    /**
     * @param list<string> $colours
     */
    public static function template(array $colours = ['BK', 'PK'], int $base = 2_500): Template
    {
        $all = [
            'BK' => AxisValue::of('BK', Label::of(['en' => 'Black', 'fr' => 'Noir']), Swatch::colour('#1A1A1A'), 'black', ['front' => 'blanks/front-bk.png', 'back' => 'blanks/back-bk.png']),
            'PK' => AxisValue::of('PK', Label::of(['en' => 'Pink', 'fr' => 'Rose']), Swatch::photo('swatches/pk.jpg'), 'azalea', ['front' => 'blanks/front-pk.png']),
            'WH' => AxisValue::of('WH', Label::of(['en' => 'White', 'fr' => 'Blanc'])),
        ];

        return Template::of(
            'unisex',
            Label::of(['en' => 'Unisex shirt', 'fr' => 'T-shirt unisexe']),
            SkuPattern::parse('{design}{lang}{version}{size}{colour}'),
            Pricing::of(self::cad($base))->withPrice('size', '2X', self::cad(2_800))->withSupplement('version', 'C', self::cad(1_000)),
            [
                Axis::of(
                    'version',
                    Label::of(['en' => 'Version']),
                    AxisValue::of('O', Label::of(['en' => 'Regular'])),
                    AxisValue::of('C', Label::of(['en' => 'Convention']), channels: ['convention']),
                ),
                Axis::of(
                    'size',
                    Label::of(['en' => 'Size', 'fr' => 'Taille']),
                    AxisValue::of('M', Label::of(['en' => 'M'])),
                    AxisValue::of('2X', Label::of(['en' => '2XL'])),
                ),
                Axis::of('colour', Label::of(['en' => 'Colour', 'fr' => 'Couleur']), ...array_map(static fn (string $code): AxisValue => $all[$code], $colours)),
            ],
            [
                Face::of('front', Label::of(['en' => 'Front']), PrintZone::of('chest', 50, 60, 300, 400, photoBox: PhotoBox::of(2, 2, 4, 4)), PrintZone::of('pocket', 0, 0, 10, 10)),
                Face::of('back', Label::of(['en' => 'Back']), PrintZone::of('full', 0, 0, 300, 400, photoBox: PhotoBox::of(0, 0, 2, 2))),
            ],
            \in_array('PK', $colours, true) ? [Exclusion::of(['colour' => ['PK'], 'size' => ['2X']])] : [],
        );
    }

    /**
     * @param list<string> $languages
     */
    public static function design(array $languages = ['F', 'E']): Design
    {
        $versions = ['F' => LanguageVersion::of('F', 'fr'), 'E' => LanguageVersion::of('E', 'en')];

        return Design::of(
            'NAQ001',
            Label::of(['fr' => 'Le gnome', 'en' => 'The gnome']),
            array_map(static fn (string $code): LanguageVersion => $versions[$code], $languages),
            [Artwork::of('front', 'art/front.png'), Artwork::of('front', 'art/pocket.png', 'pocket'), Artwork::of('back', 'art/back.png')],
        );
    }

    public static function productTemplate(?Template $template = null): ProductTemplate
    {
        return ProductTemplate::create($template ?? self::template(), ProductKind::Physical, 'goods');
    }

    public static function productDesign(?Design $design = null): ProductDesign
    {
        return ProductDesign::create($design ?? self::design());
    }
}
