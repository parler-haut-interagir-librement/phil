<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\LineConfiguration;
use Phil\CommerceBundle\Domain\ValueObject\OptionDefinition;
use Phil\CommerceBundle\Domain\ValueObject\OptionValue;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Domain\ValueObject\TextConstraints;
use Phil\CommerceProductTemplate\Artwork;
use Phil\CommerceProductTemplate\Design;
use Phil\CommerceProductTemplate\Label;
use Phil\CommerceProductTemplate\VariantPlanner;
use Phil\CommerceProductTemplateBundle\Application\CatalogGeneration;
use Phil\CommerceProductTemplateBundle\Application\Port\PrintFonts;
use Phil\CommerceProductTemplateBundle\Application\PrintProduction;
use Phil\CommerceProductTemplateBundle\Application\ProductionCache;
use Phil\CommerceProductTemplateBundle\Application\TemplateOptions;
use Phil\CommerceProductTemplateBundle\Domain\Exception\TemplateRefused;
use Phil\CommerceProductTemplateBundle\Domain\Model\GroupMember;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductDesign;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductGroup;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductTemplate;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductDesignRepository;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductGroupRepository;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductTemplateRepository;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\DiceSlot;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\PrintElement;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\PrintFile;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\ProductionLine;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\TemplateOption;
use Phil\CommerceProductTemplateBundle\Infrastructure\Fonts\ConfiguredFonts;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryDesigns;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryGroups;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryImages;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryOrders;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryProductionStore;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryProducts;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryTemplates;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\LootShirts;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\Shirts;
use Phil\Money\Currency;
use Phil\PrintCompositor\Box;
use Phil\PrintCompositor\PrintCompositor;
use Phil\PrintCompositor\Raster;
use Phil\PrintCompositor\TypeFace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PrintProduction::class)]
#[CoversClass(ProductionCache::class)]
final class PrintProductionTest extends TestCase
{
    private InMemoryTemplates $templates;

    private InMemoryDesigns $designs;

    private InMemoryGroups $groups;

    private InMemoryOrders $orders;

    private InMemoryImages $images;

    private InMemoryProductionStore $store;

    #[Override]
    protected function setUp(): void
    {
        $this->templates = new InMemoryTemplates(LootShirts::template());
        $this->designs = new InMemoryDesigns(LootShirts::design());
        $this->groups = new InMemoryGroups();
        new CatalogGeneration($this->templates, $this->designs, $this->groups, new InMemoryProducts(), new VariantPlanner(), 'en')->generate('LOOT01', 'tee');
        $this->orders = new InMemoryOrders();
        $this->images = new InMemoryImages();
        $this->images->images = LootShirts::images();

        $this->store = new InMemoryProductionStore();
    }

    public function testAPlacedOrderGetsOnePrintFilePerZoneAndInkAndItsProductionOrder(): void
    {
        $order = $this->order('R-42', [
            ['LOOT01FLMBLK', 2, ['tomb' => OptionValue::chosen('cross'), 'ink' => OptionValue::chosen('glow'), 'epitaph' => OptionValue::chosen('Rest in pieces')]],
            ['LOOT01FOMWH', 3, ['tomb' => OptionValue::chosen('none'), 'ink' => OptionValue::chosen('plain')]],
        ]);
        $order->recordDraws($order->lines()[0]->reference, 'loot', ['3 5', '6,1']);

        $production = $this->production()->produce('R-42');

        self::assertSame('R-42', $production->orderNumber);
        self::assertSame(5, $production->units());
        self::assertSame(['LOOT01FLMBLK', 'LOOT01FLMBLK', 'LOOT01FOMWH'], array_map(static fn (ProductionLine $line): string => $line->sku, $production->lines));
        self::assertSame([1, 1, 3], array_map(static fn (ProductionLine $line): int => $line->quantity, $production->lines));

        [$first, $second, $plain] = $production->lines;
        self::assertSame('F', $first->language);
        self::assertSame([
            'version' => ['code' => 'L', 'label' => 'Loot box', 'supplier' => null],
            'size' => ['code' => 'M', 'label' => 'M', 'supplier' => null],
            'colour' => ['code' => 'BLK', 'label' => 'Noir', 'supplier' => 'jet black'],
        ], $first->values);
        self::assertSame(['loot' => '35'], $first->dice);
        self::assertSame(['loot' => '61'], $second->dice);
        self::assertSame(['epitaph' => 'Rest in pieces', 'ink' => 'glow', 'loot' => '35', 'tomb' => 'cross'], $first->options);
        self::assertSame([], $plain->dice);
        self::assertSame(['ink' => 'plain', 'tomb' => 'none'], $plain->options);

        self::assertSame([['front', 'chest', 'colour'], ['front', 'chest', 'glow'], ['back', 'full', 'colour']], array_map(static fn (PrintFile $file): array => [$file->face, $file->zone, $file->ink], $first->files));
        self::assertSame([['front', 'chest', 'colour']], array_map(static fn (PrintFile $file): array => [$file->face, $file->zone, $file->ink], $plain->files));
        self::assertSame([40.0, 50.0, 72], [$first->files[0]->width, $first->files[0]->height, $first->files[0]->dpi]);
        self::assertEquals($first->files[1], $second->files[1], 'the glowing layer of both units is one file');
        self::assertEquals($first->files[2], $second->files[2], 'the epitaph of both units is one file');
        self::assertNotSame($first->files[0]->path, $second->files[0]->path, 'each unit prints its own objects');

        foreach ([...$first->files, ...$second->files, ...$plain->files] as $file) {
            self::assertMatchesRegularExpression('#^print-files/[0-9a-f]{32}-(colour|glow)\.png$#D', $file->path);
            $size = getimagesizefromstring($this->store->files[$file->path] ?? '');
            self::assertNotFalse($size);
            self::assertSame('front' === $file->face ? [113, 142] : [57, 57], [$size[0], $size[1]]);
        }

        $colour = Raster::fromBytes($this->store->files[$plain->files[0]->path] ?? '');
        self::assertSame([200, 0, 0], [$colour->pixel(100, 70)->red, $colour->pixel(100, 70)->green, $colour->pixel(100, 70)->blue], 'the artwork fills the zone');
        self::assertSame([90, 90, 90], [$colour->pixel(15, 15)->red, $colour->pixel(15, 15)->green, $colour->pixel(15, 15)->blue], 'the sword of the base version');
        $glow = Raster::fromBytes($this->store->files[$first->files[1]->path] ?? '');
        self::assertSame(255, $glow->pixel(50, 20)->green);
        self::assertSame(0, $glow->pixel(50, 100)->alpha, 'the glowing ink holds the glowing layer only');
        $loot = Raster::fromBytes($this->store->files[$first->files[0]->path] ?? '');
        self::assertSame([30, 20], [$loot->pixel(14, 99)->red, $loot->pixel(14, 99)->green], 'the first die, a 3, picks the third object of the first table');
        self::assertSame([50, 40], [$loot->pixel(48, 99)->red, $loot->pixel(48, 99)->green], 'the second die, a 5, picks the fifth object of the second table');
        self::assertSame([30, 30, 30], [$loot->pixel(78, 30)->red, $loot->pixel(78, 30)->green, $loot->pixel(78, 30)->blue], 'the tombstone chosen');

        self::assertSame($production->json(), $this->store->files['production-orders/R-42.json'] ?? null);
        self::assertSame($production->csv(), $this->store->files['production-orders/R-42.csv'] ?? null);
        self::assertCount(7, $this->store->writes, 'five distinct print files and the two forms of the production order');
    }

    public function testProducingAgainWritesNothingAndGivesTheSameOrder(): void
    {
        $order = $this->order('R-43', [['LOOT01FLMBLK', 1, ['epitaph' => OptionValue::chosen('Rest in pieces')]]]);
        $order->recordDraws($order->lines()[0]->reference, 'loot', ['1 6']);

        $first = $this->production()->produce('R-43');
        $writes = $this->store->writes;

        $again = $this->production()->produce('R-43');

        self::assertEquals($first, $again);
        self::assertSame($writes, $this->store->writes);
    }

    public function testAFileMissingFromTheStoreIsComposedAgainAndTheChangedOrderRewritten(): void
    {
        $order = $this->order('R-44', [['LOOT01FOMBLK', 1, []]]);
        $production = $this->production()->produce('R-44');
        $path = $production->lines[0]->files[0]->path;
        unset($this->store->files[$path]);
        $this->store->files['production-orders/R-44.csv'] = 'stale';
        $this->store->writes = [];

        $this->production()->produce('R-44');

        self::assertSame([$path, 'production-orders/R-44.csv'], $this->store->writes);
        self::assertNotNull($order);
    }

    public function testUnitsDrawnAlikeMakeOneLine(): void
    {
        $order = $this->order('R-45', [['LOOT01FLMBLK', 2, []]]);
        $order->recordDraws($order->lines()[0]->reference, 'loot', ['2 4', '24']);

        $production = $this->production()->produce('R-45');

        self::assertCount(1, $production->lines);
        self::assertSame(2, $production->lines[0]->quantity);
        self::assertSame(['loot' => '24'], $production->lines[0]->dice);
        self::assertSame(['loot' => '24'], $production->lines[0]->options);
    }

    public function testATextDrawnAfterPaymentIsPrintedForEachUnit(): void
    {
        $order = $this->order('R-46', [['LOOT01FOMBLK', 2, ['epitaph' => OptionValue::toDraw()]]]);
        $order->recordDraws($order->lines()[0]->reference, 'epitaph', ['Rest in pieces', 'Here lies a gnome']);

        $production = $this->production()->produce('R-46');

        self::assertSame([['epitaph' => 'Rest in pieces'], ['epitaph' => 'Here lies a gnome']], array_map(static fn (ProductionLine $line): array => $line->options, $production->lines));
        self::assertNotSame($production->lines[0]->files[1]->path, $production->lines[1]->files[1]->path);
    }

    public function testAnOrderWithoutAProductOfAGroupProducesNothing(): void
    {
        $order = Order::open('R-47', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('gift_card', 'LOOT01FOMBLK'), 'Gift card', Shirts::cad(5_000), 'none'), 1, Shirts::cad(5_000));
        $order->add(Sellable::of(SellableReference::of('product', 'MUG'), 'Mug', Shirts::cad(1_000), 'goods'), 1, Shirts::cad(1_000));
        $order->place(new DateTimeImmutable());

        $this->orders->add($order);

        $production = $this->production()->produce('R-47');

        self::assertTrue($production->isEmpty());
        self::assertSame([], $this->store->writes);
    }

    public function testAnOrderNumberOutsideTheSafeCharactersNamesItsFileInHexadecimal(): void
    {
        $production = $this->production();

        self::assertSame('production-orders/R-42_x.1.json', $production->orderPath('R-42_x.1'));
        self::assertSame('production-orders/hex/522f3432.json', $production->orderPath('R/42'));
        self::assertSame('production-orders/hex/2e6a.json', $production->orderPath('.j'));
        self::assertSame('production-orders/hex/'.sodium_bin2hex(str_repeat('a', 65)).'.json', $production->orderPath(str_repeat('a', 65)));
    }

    public function testAnUnknownOrderIsRefused(): void
    {
        $this->expectExceptionObject(TemplateRefused::unknownOrder('R-0'));

        $this->production()->produce('R-0');
    }

    public function testAnOrderNotPlacedIsRefused(): void
    {
        $order = Order::open('R-48', Currency::of('CAD'));
        $this->orders->add($order);

        $this->expectExceptionObject(TemplateRefused::orderNotPlaced('R-48', 'cart'));

        $this->production()->produce('R-48');
    }

    public function testAnOrderWithDrawsStillToMakeIsRefusedAndRetryable(): void
    {
        $order = $this->order('R-49', [['LOOT01FOMBLK', 1, []], ['LOOT01FLMBLK', 1, []]]);

        try {
            $this->production()->produce('R-49');
            self::fail('The order is refused.');
        } catch (TemplateRefused $templateRefused) {
            self::assertSame(TemplateRefused::drawsPending('R-49', $order->lines()[1]->reference->value())->getMessage(), $templateRefused->getMessage());
            self::assertTrue($templateRefused->isRetryable());
            self::assertSame([], $this->store->writes, 'nothing is written before every line is checked');
        }
    }

    public function testAProductItsPlanNoLongerGivesIsRefused(): void
    {
        $this->groups->addMember(GroupMember::of('LOOT01FXMBLK', 'LOOT01:tee', ['version' => 'X', 'size' => 'M', 'colour' => 'BLK'], 'F'));
        $order = Order::open('R-50', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'LOOT01FXMBLK'), 'Tee', Shirts::cad(2_500), 'goods'), 1, Shirts::cad(2_500));
        $order->place(new DateTimeImmutable());

        $this->orders->add($order);

        $this->expectExceptionObject(TemplateRefused::notPlanned('LOOT01FXMBLK'));

        $this->production()->produce('R-50');
    }

    public function testADrawThatDoesNotReadAsTheDiceIsRefused(): void
    {
        $order = $this->order('R-51', [['LOOT01FLMBLK', 1, []]]);
        $order->recordDraws($order->lines()[0]->reference, 'loot', ['7 1']);

        $this->expectException(TemplateRefused::class);
        $this->expectExceptionMessageIsOrContains('The draw of the option loot of LOOT01FLMBLK cannot be read: The draw "7 1" gives 7 on die 1, a d6.');

        $this->production()->produce('R-51');
    }

    public function testATextThatDoesNotFitIsRefused(): void
    {
        $this->designs->find('LOOT01')?->declarePrintLayout(PrintElement::text('back', 'full', 'epitaph', new Box(0, 0, 2, 2), maxSize: 40, minSize: 30));
        $this->order('R-52', [['LOOT01FOMBLK', 1, ['epitaph' => OptionValue::chosen('Rest in pieces')]]]);

        $this->expectException(TemplateRefused::class);
        $this->expectExceptionMessageIsOrContains('The print file of the back zone full of LOOT01FOMBLK cannot be composed: ');

        $this->production()->produce('R-52');
    }

    public function testAnUnknownFontIsRefused(): void
    {
        $this->designs->find('LOOT01')?->declarePrintLayout(PrintElement::text('back', 'full', 'epitaph', font: 'Missing'));
        $this->order('R-53', [['LOOT01FOMBLK', 1, ['epitaph' => OptionValue::chosen('Rest in pieces')]]]);

        $this->expectExceptionObject(TemplateRefused::unknownFont('Missing'));

        $this->production()->produce('R-53');
    }

    public function testAnImageTheStoreLacksIsRefused(): void
    {
        unset($this->images->images['objects/sword.png']);
        $this->order('R-54', [['LOOT01FOMBLK', 1, []]]);

        $this->expectExceptionObject(TemplateRefused::missingImage('objects/sword.png'));

        $this->production()->produce('R-54');
    }

    public function testATextElementWithoutItsTextOptionIsRefused(): void
    {
        $this->designs->find('LOOT01')?->declarePrintLayout(PrintElement::text('back', 'full', 'tomb'));
        $this->order('R-55', [['LOOT01FOMBLK', 1, ['tomb' => OptionValue::chosen('cross')]]]);

        $this->expectExceptionObject(TemplateRefused::textOption('tomb', 'the product has no text option of that code'));

        $this->production()->produce('R-55');
    }

    public function testATextWithoutAFontOrARectangleIsRefused(): void
    {
        $this->templates->find('tee')?->declareOptions(TemplateOption::of(OptionDefinition::text('epitaph', 'Epitaph', TextConstraints::of(30))));
        $this->order('R-56', [['LOOT01FOMBLK', 1, ['epitaph' => OptionValue::chosen('Rest in pieces')]]]);

        try {
            $this->production()->produce('R-56');
            self::fail('The text has no font.');
        } catch (TemplateRefused $templateRefused) {
            self::assertEquals(TemplateRefused::textOption('epitaph', 'neither the element nor the option names its font'), $templateRefused);
        }

        $this->designs->find('LOOT01')?->declarePrintLayout(PrintElement::text('back', 'full', 'epitaph', font: LootShirts::FONT));

        $this->expectExceptionObject(TemplateRefused::textOption('epitaph', 'neither the element nor the option gives its rectangle'));

        $this->production()->produce('R-56');
    }

    public function testAnOrderNumberEndingWithANewlineIsNotASafeCharacterName(): void
    {
        self::assertSame('production-orders/hex/'.sodium_bin2hex("R-42\n").'.json', $this->production()->orderPath("R-42\n"));
    }

    public function testADesignThatVanishesBetweenThePlanAndTheLineIsRefused(): void
    {
        $this->order('R-60', [['LOOT01FOMBLK', 1, []]]);

        $this->expectExceptionObject(TemplateRefused::unknownDesign('LOOT01'));

        $this->production(designs: $this->fading(1))->produce('R-60');
    }

    public function testAGroupATemplateOrADesignTheStoreLacksIsRefused(): void
    {
        $this->order('R-61', [['LOOT01FOMBLK', 1, []]]);
        $groups = $this->groups;
        $lacking = new readonly class($groups) implements ProductGroupRepository {
            public function __construct(private ProductGroupRepository $groups)
            {
            }

            public function find(string $id): ?ProductGroup
            {
                return null;
            }

            public function add(ProductGroup $group): void
            {
            }

            public function findMember(string $sku): ?GroupMember
            {
                return $this->groups->findMember($sku);
            }

            public function membersOf(string $groupId): array
            {
                return [];
            }

            public function addMember(GroupMember $member): void
            {
            }
        };

        try {
            $this->production(groups: $lacking)->produce('R-61');
            self::fail('The group is unknown.');
        } catch (TemplateRefused $templateRefused) {
            self::assertEquals(TemplateRefused::unknownGroup('LOOT01:tee'), $templateRefused);
        }

        $templates = new class implements ProductTemplateRepository {
            public function find(string $code): ?ProductTemplate
            {
                return null;
            }

            public function add(ProductTemplate $template): void
            {
            }
        };

        try {
            $this->production(templates: $templates)->produce('R-61');
            self::fail('The template is unknown.');
        } catch (TemplateRefused $templateRefused) {
            self::assertEquals(TemplateRefused::unknownTemplate('tee'), $templateRefused);
        }

        $this->expectExceptionObject(TemplateRefused::unknownDesign('LOOT01'));

        $this->production(designs: $this->fading(0))->produce('R-61');
    }

    public function testATextOptionIsNotAChoice(): void
    {
        $this->designs->find('LOOT01')?->declarePrintLayout();
        $this->order('R-62', [['LOOT01FOMBLK', 1, ['epitaph' => OptionValue::chosen('abc')]]]);
        $plain = $this->production()->produce('R-62')->lines[0]->files[0]->path;

        $this->designs->find('LOOT01')?->declarePrintLayout(PrintElement::image('front', 'chest', 'objects/sword.png', new Box(2, 2, 10, 10), chosen: ['epitaph' => ['abc']]));
        $this->order('R-63', [['LOOT01FOMBLK', 1, ['epitaph' => OptionValue::chosen('abc')]]]);

        self::assertSame($plain, $this->production()->produce('R-63')->lines[0]->files[0]->path, 'only a choice or an ink picks the elements that list choices');
    }

    public function testUnitsThatDifferOnlyByAnOptionNoElementPrintsMakeTwoLines(): void
    {
        $this->designs->find('LOOT01')?->declarePrintLayout();
        $order = $this->order('R-64', [['LOOT01FOMBLK', 2, ['epitaph' => OptionValue::toDraw()]]]);
        $order->recordDraws($order->lines()[0]->reference, 'epitaph', ['Rest in pieces', 'Here lies a gnome']);

        $production = $this->production()->produce('R-64');

        self::assertCount(2, $production->lines);
        self::assertEquals($production->lines[0]->files, $production->lines[1]->files);
        self::assertSame([['epitaph' => 'Rest in pieces'], ['epitaph' => 'Here lies a gnome']], array_map(static fn (ProductionLine $line): array => $line->options, $production->lines));
    }

    public function testAProductWithoutALanguageVersionHasNoLanguage(): void
    {
        $this->designs->add(ProductDesign::create(Design::of('PLAIN', Label::of(['en' => 'Plain']), [], [Artwork::of('front', 'art/chest.png')])));
        new CatalogGeneration($this->templates, $this->designs, $this->groups, new InMemoryProducts(), new VariantPlanner(), 'en')->generate('PLAIN', 'tee');
        $this->order('R-65', [['PLAINOMBLK', 1, []]]);

        $production = $this->production()->produce('R-65');

        self::assertNull($production->lines[0]->language);
        self::assertSame('PLAINOMBLK', $production->lines[0]->sku);
    }

    public function testEachZoneHasItsOwnFileEvenWhenTwoZonesPrintTheSame(): void
    {
        $this->designs->add(ProductDesign::create(Design::of('TWIN', Label::of(['en' => 'Twin']), [], [Artwork::of('front', 'art/chest.png'), Artwork::of('front', 'art/chest.png', 'pocket')])));
        new CatalogGeneration($this->templates, $this->designs, $this->groups, new InMemoryProducts(), new VariantPlanner(), 'en')->generate('TWIN', 'tee');
        $this->order('R-66', [['TWINOMBLK', 1, []]]);

        $files = $this->production()->produce('R-66')->lines[0]->files;

        self::assertSame(['chest', 'pocket'], array_map(static fn (PrintFile $file): string => $file->zone, $files));
        self::assertNotSame($files[0]->path, $files[1]->path);
    }

    public function testAnElementOfAnotherZoneOfTheSameFaceIsNotDrawnHere(): void
    {
        $this->designs->find('LOOT01')?->declarePrintLayout(PrintElement::image('front', 'pocket', 'objects/sword.png', new Box(2, 2, 10, 10)));
        $this->order('R-67', [['LOOT01FOMBLK', 1, []]]);

        $files = $this->production()->produce('R-67')->lines[0]->files;

        self::assertSame([['front', 'chest'], ['front', 'pocket']], array_map(static fn (PrintFile $file): array => [$file->face, $file->zone], $files));
        self::assertNotSame($files[0]->path, $files[1]->path, 'the artwork of the chest is not printed on the pocket, nor the sword on the chest');
        $chest = Raster::fromBytes($this->store->files[$files[0]->path] ?? '');
        self::assertSame([200, 0, 0], [$chest->pixel(20, 20)->red, $chest->pixel(20, 20)->green, $chest->pixel(20, 20)->blue]);
    }

    public function testEveryDrawOptionIsReadWhateverComesBeforeItInTheLayoutAndOnlyOnce(): void
    {
        $this->templates->find('tee')?->declareOptions(...LootShirts::options(), ...[TemplateOption::of(OptionDefinition::draw('luck', 'Luck', 'luck-1d6'), ['version' => ['L']])]);
        $table = static fn (int $table): array => array_combine(range(1, 6), array_map(static fn (int $result): string => \sprintf('objects/t%d-%d.png', $table, $result), range(1, 6)));
        $this->designs->find('LOOT01')?->declarePrintLayout(
            PrintElement::image('front', 'chest', 'objects/purse.png', new Box(2, 2, 10, 10)),
            PrintElement::dice('front', 'chest', 'loot', [DiceSlot::of(new Box(0, 30, 10, 10), 6, $table(1)), DiceSlot::of(new Box(12, 30, 10, 10), 6, $table(2))]),
            PrintElement::dice('front', 'chest', 'loot', [DiceSlot::of(new Box(0, 30, 10, 10), 6, $table(1)), DiceSlot::of(new Box(12, 30, 10, 10), 6, $table(2)), DiceSlot::of(new Box(24, 30, 10, 10), 6, $table(1))]),
            PrintElement::dice('front', 'chest', 'luck', [DiceSlot::of(new Box(24, 30, 10, 10), 6, $table(2))]),
        );
        $order = $this->order('R-68', [['LOOT01FLMBLK', 1, []]]);
        $order->recordDraws($order->lines()[0]->reference, 'loot', ['3 5']);
        $order->recordDraws($order->lines()[0]->reference, 'luck', ['2']);

        $production = $this->production()->produce('R-68');

        self::assertSame(['loot' => '35', 'luck' => '2'], $production->lines[0]->dice);
    }

    public function testAnOptionTheTemplateNoLongerHasIsIgnoredUnlessAnElementPrintsIt(): void
    {
        $this->designs->find('LOOT01')?->declarePrintLayout();
        $this->order('R-69', [['LOOT01FOMBLK', 1, ['epitaph' => OptionValue::chosen('Rest in pieces')]]]);
        $this->templates->find('tee')?->declareOptions();

        self::assertSame(['epitaph' => 'Rest in pieces'], $this->production()->produce('R-69')->lines[0]->options);

        $this->designs->find('LOOT01')?->declarePrintLayout(PrintElement::text('back', 'full', 'epitaph'));
        $this->templates->find('tee')?->declareOptions(...LootShirts::options());
        $this->order('R-70', [['LOOT01FOMBLK', 1, ['epitaph' => OptionValue::chosen('Rest in pieces')]]]);
        $this->templates->find('tee')?->declareOptions();

        $this->expectExceptionObject(TemplateRefused::textOption('epitaph', 'the product has no text option of that code'));

        $this->production()->produce('R-70');
    }

    public function testTheRectangleOfAnElementWinsOverTheOneOfItsOption(): void
    {
        $this->designs->find('LOOT01')?->declarePrintLayout(PrintElement::text('back', 'full', 'epitaph', maxSize: 24, minSize: 2));
        $this->order('R-71', [['LOOT01FOMBLK', 1, ['epitaph' => OptionValue::chosen('Rest')]]]);
        $own = $this->production()->produce('R-71')->lines[0]->files[1]->path;

        $this->designs->find('LOOT01')?->declarePrintLayout(PrintElement::text('back', 'full', 'epitaph', new Box(5, 5, 12, 12), maxSize: 24, minSize: 2));
        $this->order('R-72', [['LOOT01FOMBLK', 1, ['epitaph' => OptionValue::chosen('Rest')]]]);

        self::assertNotSame($own, $this->production()->produce('R-72')->lines[0]->files[1]->path);
    }

    public function testEachImageIsReadOnceAndEachFontIsLoadedOncePerDigest(): void
    {
        $order = $this->order('R-73', [['LOOT01FLMBLK', 2, ['tomb' => OptionValue::chosen('cross'), 'epitaph' => OptionValue::chosen('Rest')]]]);
        $order->recordDraws($order->lines()[0]->reference, 'loot', ['3 5', '3 5']);

        $fonts = new class implements PrintFonts {
            /** @var list<string> */
            public array $loaded = [];

            public function typeFace(string $name): TypeFace
            {
                $this->loaded[] = $name;

                return new ConfiguredFonts([LootShirts::FONT => LootShirts::font()])->typeFace($name);
            }
        };

        $this->production(fonts: $fonts)->produce('R-73');

        self::assertNotSame([], $this->images->reads);
        self::assertSame([], array_filter(array_count_values($this->images->reads), static fn (int $reads): bool => $reads > 1), 'an image is read once in a run');
        self::assertSame([LootShirts::FONT, LootShirts::FONT], $fonts->loaded, 'a font is digested once, and loaded once to compose');
    }

    private function fading(int $finds): ProductDesignRepository
    {
        return new class($this->designs, $finds) implements ProductDesignRepository {
            public function __construct(private readonly InMemoryDesigns $designs, private int $finds)
            {
            }

            public function find(string $code): ?ProductDesign
            {
                return $this->finds-- > 0 ? $this->designs->find($code) : null;
            }

            public function add(ProductDesign $design): void
            {
            }
        };
    }

    /**
     * @param list<array{string, int, array<string, OptionValue>}> $lines
     */
    private function order(string $number, array $lines): Order
    {
        $options = new TemplateOptions($this->groups, $this->templates, $this->designs, 'product');
        $order = Order::open($number, Currency::of('CAD'));

        foreach ($lines as [$sku, $quantity, $values]) {
            $reference = SellableReference::of('product', $sku);
            $order->add(Sellable::of($reference, 'Tee', Shirts::cad(2_500), 'goods', $options->optionsOf($reference, Currency::of('CAD'))), $quantity, Shirts::cad(2_500), LineConfiguration::of($values));
        }

        $order->place(new DateTimeImmutable());
        $this->orders->add($order);

        return $order;
    }

    private function production(?ProductTemplateRepository $templates = null, ?ProductDesignRepository $designs = null, ?ProductGroupRepository $groups = null, ?PrintFonts $fonts = null): PrintProduction
    {
        return new PrintProduction(
            $this->orders,
            $templates ?? $this->templates,
            $designs ?? $this->designs,
            $groups ?? $this->groups,
            new VariantPlanner(),
            new TemplateOptions($this->groups, $this->templates, $this->designs, 'product'),
            $this->images,
            $this->store,
            $fonts ?? new ConfiguredFonts([LootShirts::FONT => LootShirts::font()]),
            new PrintCompositor(),
            'product',
            'en',
            'print-files',
            'production-orders',
        );
    }
}
