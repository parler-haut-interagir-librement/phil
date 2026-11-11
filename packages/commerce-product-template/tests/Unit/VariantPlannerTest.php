<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate\Tests\Unit;

use Phil\CommerceProductTemplate\Artwork;
use Phil\CommerceProductTemplate\Axis;
use Phil\CommerceProductTemplate\AxisValue;
use Phil\CommerceProductTemplate\Design;
use Phil\CommerceProductTemplate\Exception\InvalidDefinition;
use Phil\CommerceProductTemplate\Exception\PlanRefused;
use Phil\CommerceProductTemplate\Face;
use Phil\CommerceProductTemplate\Label;
use Phil\CommerceProductTemplate\PlacedArtwork;
use Phil\CommerceProductTemplate\Pricing;
use Phil\CommerceProductTemplate\PrintZone;
use Phil\CommerceProductTemplate\SkuPattern;
use Phil\CommerceProductTemplate\Template;
use Phil\CommerceProductTemplate\Tests\Unit\Fixture\Shirts;
use Phil\CommerceProductTemplate\Variant;
use Phil\CommerceProductTemplate\VariantPlanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(VariantPlanner::class)]
#[CoversClass(Variant::class)]
#[CoversClass(PlacedArtwork::class)]
#[CoversClass(PlanRefused::class)]
final class VariantPlannerTest extends TestCase
{
    public function testAPlanHasOneProductPerLanguageAndAllowedCombination(): void
    {
        $variants = new VariantPlanner()->plan(Shirts::template(), Shirts::design());

        self::assertSame([
            'NAQ001FOMBK' => [2_500, []],
            'NAQ001FOMPK' => [2_500, []],
            'NAQ001FO2XBK' => [2_800, []],
            'NAQ001FCMBK' => [3_500, ['convention']],
            'NAQ001FCMPK' => [3_500, ['convention']],
            'NAQ001FC2XBK' => [3_800, ['convention']],
            'NAQ001EOMBK' => [2_500, []],
            'NAQ001EOMPK' => [2_500, []],
            'NAQ001EO2XBK' => [2_800, []],
            'NAQ001ECMBK' => [3_500, ['convention']],
            'NAQ001ECMPK' => [3_500, ['convention']],
            'NAQ001EC2XBK' => [3_800, ['convention']],
        ], array_combine(
            array_map(static fn (Variant $variant): string => $variant->sku, $variants),
            array_map(static fn (Variant $variant): array => [$variant->price->minorUnits, $variant->channels], $variants),
        ));
    }

    public function testEachProductPrintsTheMostSpecificArtworkOfEachZone(): void
    {
        $artworks = [];

        foreach (new VariantPlanner()->plan(Shirts::template(), Shirts::design()) as $variant) {
            $artworks[$variant->sku] = array_map(static fn (PlacedArtwork $artwork): string => $artwork->face->code.'/'.$artwork->zone->name.': '.$artwork->path, $variant->artworks);
        }

        self::assertSame(['front/chest: art/naq001-front.png', 'back/full: art/naq001-back.png'], $artworks['NAQ001FOMBK']);
        self::assertSame(['front/chest: art/naq001-front-con.png', 'back/full: art/naq001-back.png'], $artworks['NAQ001FCMBK']);
        self::assertSame(['front/chest: art/naq001-front-en.png', 'back/full: art/naq001-back.png'], $artworks['NAQ001EOMBK']);
        self::assertSame(['front/chest: art/naq001-front-en.png', 'back/full: art/naq001-back.png'], $artworks['NAQ001ECMBK']);
    }

    public function testAProductKnowsItsNameAttributesAndPhotos(): void
    {
        $variant = new VariantPlanner()->plan(Shirts::template(), Shirts::design())[0];

        self::assertSame('NAQ001', $variant->design->code);
        self::assertSame('unisex', $variant->template->code);
        self::assertSame('F', $variant->language?->code);
        self::assertSame(['version' => 'O', 'size' => 'M', 'colour' => 'BK'], $variant->combination->codes());
        self::assertSame('fr', $variant->locale('en'));
        self::assertSame('Le gnome – T-shirt unisexe (Ordinaire, M, Noir)', $variant->name('en'));
        self::assertSame(['design' => 'NAQ001', 'template' => 'unisex', 'language' => 'F', 'version' => 'O', 'size' => 'M', 'colour' => 'BK'], $variant->attributes());
        self::assertSame('blanks/front-bk.jpg', $variant->photo('front'));
        self::assertSame('blanks/back-bk.jpg', $variant->photo('back'));
        self::assertNull($variant->photo('sleeve'));
    }

    public function testATemplateCodeMayBePartOfTheSku(): void
    {
        $variants = new VariantPlanner()->plan(Shirts::template('{design}-{template}-{lang}{version}{size}{colour}'), Shirts::design());

        self::assertSame('NAQ001-unisex-FOMBK', $variants[0]->sku);
    }

    public function testADesignInOneLanguageHasNoLanguageCode(): void
    {
        $design = Design::of('GATS001', Label::of(['en' => 'Dice']), artworks: [Artwork::of('front', 'art/dice.png', 'pocket')]);
        $variants = new VariantPlanner()->plan(Shirts::template(), $design);

        self::assertCount(9, $variants);
        self::assertSame('GATS001OMBK', $variants[0]->sku);
        self::assertNull($variants[0]->language);
        self::assertSame('de', $variants[0]->locale('de'));
        self::assertSame('Dice – Unisex shirt (Regular, M, Black)', $variants[0]->name('en'));
        self::assertSame(['design' => 'GATS001', 'template' => 'unisex', 'version' => 'O', 'size' => 'M', 'colour' => 'BK'], $variants[0]->attributes());
        self::assertSame(['front/pocket'], array_map(static fn (PlacedArtwork $artwork): string => $artwork->face->code.'/'.$artwork->zone->name, $variants[0]->artworks));
        self::assertNull($variants[1]->photo('back'));
    }

    public function testAnArtworkOfAFaceTheTemplateLacksIsLeftOut(): void
    {
        $design = Design::of('A', Label::of(['en' => 'A']), artworks: [Artwork::of('sleeve', 'art/sleeve.png', 'band')]);

        self::assertSame([], new VariantPlanner()->plan(Shirts::template(), $design)[0]->artworks);
    }

    public function testValuesSoldThroughNoCommonChannelMakeNoProduct(): void
    {
        $name = Label::of(['en' => 'x']);
        $template = Template::of('t', $name, SkuPattern::parse('{design}{a}{b}'), Pricing::of(Shirts::cad(1)), [
            Axis::of('a', $name, AxisValue::of('1', $name, channels: ['web', 'shop']), AxisValue::of('2', $name)),
            Axis::of('b', $name, AxisValue::of('X', $name, channels: ['shop', 'fair']), AxisValue::of('Y', $name, channels: ['fair'])),
        ]);
        $variants = new VariantPlanner()->plan($template, Design::of('D', $name));

        self::assertSame(['D1X' => ['shop'], 'D2X' => ['shop', 'fair'], 'D2Y' => ['fair']], array_combine(
            array_map(static fn (Variant $variant): string => $variant->sku, $variants),
            array_map(static fn (Variant $variant): array => $variant->channels, $variants),
        ));
    }

    public function testADesignIsPlannedOnlyOnItsTemplates(): void
    {
        $design = Design::of('A', Label::of(['en' => 'A']), templates: ['fitted']);

        $this->expectExceptionObject(PlanRefused::templateNotAllowed('A', 'unisex'));

        new VariantPlanner()->plan(Shirts::template(), $design);
    }

    public function testTwoProductsNeverShareASku(): void
    {
        $this->expectExceptionObject(PlanRefused::collision('NAQ001FOM', 'F version O, size M, colour BK', 'F version O, size M, colour PK'));

        new VariantPlanner()->plan(Shirts::template('{design}{lang}{version}{size}'), Shirts::design());
    }

    public function testACollisionWithoutLanguagesNamesTheCombinations(): void
    {
        $this->expectExceptionObject(PlanRefused::collision('AOM', 'version O, size M, colour BK', 'version O, size M, colour PK'));

        new VariantPlanner()->plan(Shirts::template('{design}{version}{size}'), Design::of('A', Label::of(['en' => 'A'])));
    }

    public function testASkuACatalogueRefusesIsRefused(): void
    {
        $this->expectExceptionObject(PlanRefused::invalidSku('-AOMBK'));

        new VariantPlanner()->plan(Shirts::template('-{design}{version}{size}{colour}'), Design::of('A', Label::of(['en' => 'A'])));
    }

    public function testASkuOfSixtyFourCharactersIsTheLongest(): void
    {
        $design = Design::of(str_repeat('A', 32), Label::of(['en' => 'A']), allowed: ['size' => ['M']]);
        $sku = str_repeat('A', 32).str_repeat('x', 28).'OMBK';

        self::assertSame($sku, new VariantPlanner()->plan(Shirts::template('{design}'.str_repeat('x', 28).'{version}{size}{colour}'), $design)[0]->sku);

        $this->expectExceptionObject(PlanRefused::invalidSku($sku.'x'));

        new VariantPlanner()->plan(Shirts::template('{design}'.str_repeat('x', 28).'{version}{size}{colour}x'), $design);
    }

    public function testAnArtworkInAZoneTheTemplateLacksIsRefused(): void
    {
        $design = Design::of('A', Label::of(['en' => 'A']), artworks: [Artwork::of('front', 'a.png', 'sleeve')]);

        $this->expectExceptionObject(PlanRefused::unknownZone('A', 'front', 'sleeve', 'unisex'));

        new VariantPlanner()->plan(Shirts::template(), $design);
    }

    public function testTwoArtworksAsSpecificAsEachOtherAreRefused(): void
    {
        $design = Design::of('A', Label::of(['en' => 'A']), artworks: [
            Artwork::of('front', 'a.png', when: ['size' => ['M']]),
            Artwork::of('front', 'b.png', when: ['colour' => ['BK']]),
        ]);

        $this->expectExceptionObject(PlanRefused::ambiguousArtwork('A', 'front', 'chest', 'version O, size M, colour BK'));

        new VariantPlanner()->plan(Shirts::template(), $design);
    }

    public function testAMoreSpecificArtworkSettlesATie(): void
    {
        $design = Design::of('A', Label::of(['en' => 'A']), artworks: [
            Artwork::of('front', 'a.png', when: ['size' => ['M']]),
            Artwork::of('front', 'b.png', when: ['colour' => ['BK']]),
            Artwork::of('front', 'c.png', when: ['colour' => ['BK'], 'size' => ['M']]),
            Artwork::of('front', 'd.png'),
        ]);
        $variants = new VariantPlanner()->plan(Shirts::template(), $design);

        self::assertSame('c.png', $variants[0]->artworks[0]->path);
        self::assertSame('a.png', $variants[1]->artworks[0]->path);
        self::assertSame('b.png', $variants[2]->artworks[0]->path);
        self::assertSame('front', $variants[0]->artworks[0]->face->code);
        self::assertSame('chest', $variants[0]->artworks[0]->zone->name);
    }

    public function testAPlanHoldsUpToItsLimit(): void
    {
        self::assertCount(12, new VariantPlanner(12)->plan(Shirts::template(), Shirts::design()));

        $this->expectExceptionObject(PlanRefused::tooMany(11));

        new VariantPlanner(11)->plan(Shirts::template(), Shirts::design());
    }

    public function testThePlannerLimitIsBounded(): void
    {
        self::assertInstanceOf(VariantPlanner::class, new VariantPlanner(1));
        self::assertInstanceOf(VariantPlanner::class, new VariantPlanner(20_000));

        foreach ([0, 20_001] as $limit) {
            try {
                new VariantPlanner($limit);
                self::fail('The limit is refused.');
            } catch (InvalidDefinition $exception) {
                self::assertSame(\sprintf('A plan holds from 1 to 20000 products, not %d.', $limit), $exception->getMessage());
            }
        }
    }

    public function testAFaceWithoutArtworkPrintsNothing(): void
    {
        $name = Label::of(['en' => 'x']);
        $template = Template::of('t', $name, SkuPattern::parse('{design}'), Pricing::of(Shirts::cad(1)), [Axis::of('a', $name, AxisValue::of('1', $name))], [Face::of('front', $name, PrintZone::of('chest', 0, 0, 1, 1))]);

        self::assertSame([], new VariantPlanner()->plan($template, Design::of('D', $name))[0]->artworks);
    }
}
