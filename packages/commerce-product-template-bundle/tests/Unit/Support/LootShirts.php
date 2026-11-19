<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Tests\Unit\Support;

use Phil\CommerceBundle\Domain\Option\TextSource;
use Phil\CommerceBundle\Domain\ValueObject\OptionChoice;
use Phil\CommerceBundle\Domain\ValueObject\OptionDefinition;
use Phil\CommerceBundle\Domain\ValueObject\PrintZone as TextZone;
use Phil\CommerceBundle\Domain\ValueObject\TextConstraints;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\CommerceProductTemplate\Artwork;
use Phil\CommerceProductTemplate\Axis;
use Phil\CommerceProductTemplate\AxisValue;
use Phil\CommerceProductTemplate\Design;
use Phil\CommerceProductTemplate\Face;
use Phil\CommerceProductTemplate\Label;
use Phil\CommerceProductTemplate\LanguageVersion;
use Phil\CommerceProductTemplate\Pricing;
use Phil\CommerceProductTemplate\PrintZone;
use Phil\CommerceProductTemplate\SkuPattern;
use Phil\CommerceProductTemplate\Template;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductDesign;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductTemplate;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\DiceSlot;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\PrintElement;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\TemplateOption;
use Phil\PrintCompositor\Box;

/**
 * A shirt printed small, at 72 dpi, so that its print files compose fast: a base, a loot box and a convention version,
 * a front chest zone and a back zone, and a design whose layout prints fixed objects, two dice of objects, a tombstone,
 * an epitaph and a glowing layer.
 */
final class LootShirts
{
    public const string FONT = 'Test Sans';

    public static function template(): ProductTemplate
    {
        $template = ProductTemplate::create(Template::of(
            'tee',
            Label::of(['en' => 'Tee', 'fr' => 'Tee']),
            SkuPattern::parse('{design}{lang}{version}{size}{colour}'),
            Pricing::of(Shirts::cad(2_500)),
            [
                Axis::of('version', Label::of(['en' => 'Version']), AxisValue::of('O', Label::of(['en' => 'Regular'])), AxisValue::of('L', Label::of(['en' => 'Loot box'])), AxisValue::of('C', Label::of(['en' => 'Convention']))),
                Axis::of('size', Label::of(['en' => 'Size']), AxisValue::of('M', Label::of(['en' => 'M']))),
                Axis::of('colour', Label::of(['en' => 'Colour', 'fr' => 'Couleur']), AxisValue::of('BLK', Label::of(['en' => 'Black', 'fr' => 'Noir']), supplierName: 'jet black'), AxisValue::of('WH', Label::of(['en' => 'White']))),
            ],
            [
                Face::of('front', Label::of(['en' => 'Front']), PrintZone::of('chest', 50, 60, 40, 50, 72), PrintZone::of('pocket', 0, 0, 40, 50, 72)),
                Face::of('back', Label::of(['en' => 'Back']), PrintZone::of('full', 0, 0, 20, 20, 72)),
            ],
        ), ProductKind::Physical, 'goods');
        $template->declareOptions(...self::options());

        return $template;
    }

    /**
     * @return list<TemplateOption>
     */
    public static function options(): array
    {
        return [
            TemplateOption::of(OptionDefinition::draw('loot', 'Loot', 'loot-2d6'), ['version' => ['L']]),
            TemplateOption::of(OptionDefinition::text('epitaph', 'Epitaph', TextConstraints::of(30, font: self::FONT, zone: TextZone::of('full', 1, 1, 18, 18), sources: [TextSource::Free, TextSource::Drawn], entries: ['Rest in pieces', 'Here lies a gnome']), drawer: 'songs')),
            TemplateOption::of(OptionDefinition::choice('tomb', 'Tombstone', [OptionChoice::of('cross', 'Cross'), OptionChoice::of('stone', 'Stone'), OptionChoice::of('none', 'None')])),
            TemplateOption::of(OptionDefinition::ink('ink', 'Ink', [OptionChoice::of('glow', 'Glow'), OptionChoice::of('plain', 'Plain')])),
        ];
    }

    public static function design(): ProductDesign
    {
        $design = ProductDesign::create(Design::of(
            'LOOT01',
            Label::of(['fr' => 'Butin', 'en' => 'Loot']),
            [LanguageVersion::of('F', 'fr')],
            [Artwork::of('front', 'art/chest.png')],
        ));
        $design->declarePrintLayout(...self::layout());

        return $design;
    }

    /**
     * @return list<PrintElement>
     */
    public static function layout(): array
    {
        $table = static fn (int $table): array => array_combine(range(1, 6), array_map(static fn (int $result): string => \sprintf('objects/t%d-%d.png', $table, $result), range(1, 6)));

        return [
            PrintElement::image('front', 'chest', 'objects/sword.png', new Box(2, 2, 10, 10), ['version' => ['O']]),
            PrintElement::image('front', 'chest', 'objects/purse.png', new Box(2, 2, 10, 10), ['version' => ['C']]),
            PrintElement::dice('front', 'chest', 'loot', [DiceSlot::of(new Box(0, 30, 10, 10), 6, $table(1)), DiceSlot::of(new Box(12, 30, 10, 10), 6, $table(2))], ['version' => ['L']]),
            PrintElement::choice('front', 'chest', 'tomb', new Box(20, 2, 15, 15), ['cross' => 'tombs/cross.png', 'stone' => 'tombs/stone.png']),
            PrintElement::text('back', 'full', 'epitaph', maxSize: 24, minSize: 2),
            PrintElement::image('front', 'chest', 'glow/stars.png', new Box(0, 0, 40, 20), chosen: ['ink' => ['glow']], ink: 'glow'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function images(): array
    {
        $images = [
            'art/chest.png' => Images::png(6, 6, 200, 0, 0),
            'objects/sword.png' => Images::png(4, 4, 90, 90, 90),
            'objects/purse.png' => Images::png(4, 4, 120, 60, 0),
            'tombs/cross.png' => Images::png(4, 4, 30, 30, 30),
            'tombs/stone.png' => Images::png(4, 4, 60, 60, 60),
            'glow/stars.png' => Images::png(4, 2, 200, 255, 200),
        ];

        foreach ([1, 2] as $table) {
            foreach (range(1, 6) as $result) {
                $images[\sprintf('objects/t%d-%d.png', $table, $result)] = Images::png(3, 3, 10 * $result, 20 * $table, 0);
            }
        }

        return $images;
    }

    public static function font(): string
    {
        return __DIR__.'/Fixtures/print-font.ttf';
    }
}
