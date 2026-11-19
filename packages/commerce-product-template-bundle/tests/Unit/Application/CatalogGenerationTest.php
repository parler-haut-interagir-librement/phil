<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Tests\Unit\Application;

use Override;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\CommerceProductTemplate\Design;
use Phil\CommerceProductTemplate\Label;
use Phil\CommerceProductTemplate\Pricing;
use Phil\CommerceProductTemplate\Template;
use Phil\CommerceProductTemplate\VariantPlanner;
use Phil\CommerceProductTemplateBundle\Application\CatalogGeneration;
use Phil\CommerceProductTemplateBundle\Domain\Exception\TemplateRefused;
use Phil\CommerceProductTemplateBundle\Domain\Model\GroupMember;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductGroup;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\GenerationReport;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryDesigns;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryGroups;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryProducts;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryTemplates;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\Shirts;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CatalogGeneration::class)]
#[CoversClass(GenerationReport::class)]
#[CoversClass(TemplateRefused::class)]
final class CatalogGenerationTest extends TestCase
{
    private InMemoryTemplates $templates;

    private InMemoryDesigns $designs;

    private InMemoryGroups $groups;

    private InMemoryProducts $products;

    private CatalogGeneration $generation;

    #[Override]
    protected function setUp(): void
    {
        $this->templates = new InMemoryTemplates(Shirts::productTemplate());
        $this->designs = new InMemoryDesigns(Shirts::productDesign());
        $this->groups = new InMemoryGroups();
        $this->products = new InMemoryProducts();
        $this->generation = new CatalogGeneration($this->templates, $this->designs, $this->groups, $this->products, new VariantPlanner(), 'en');
    }

    public function testAGenerationCreatesEveryPlannedProductInTheCatalogue(): void
    {
        $report = $this->generation->generate('NAQ001', 'unisex');

        self::assertSame(['NAQ001FOMBK', 'NAQ001FOMPK', 'NAQ001FO2XBK', 'NAQ001FCMBK', 'NAQ001FCMPK', 'NAQ001FC2XBK', 'NAQ001EOMBK', 'NAQ001EOMPK', 'NAQ001EO2XBK', 'NAQ001ECMBK', 'NAQ001ECMPK', 'NAQ001EC2XBK'], $report->created);
        self::assertSame([[], [], [], []], [$report->updated, $report->restored, $report->unchanged, $report->withdrawn]);
        self::assertFalse($report->changedNothing());

        $product = $this->products->find('NAQ001FC2XBK');
        self::assertInstanceOf(Product::class, $product);
        self::assertSame(ProductKind::Physical, $product->kind);
        self::assertSame('Le gnome – T-shirt unisexe (Convention, 2XL, Noir)', $product->name());
        self::assertSame(3_800, $product->price->minorUnits);
        self::assertSame('goods', $product->taxCategory());
        self::assertSame(['colour' => 'BK', 'design' => 'NAQ001', 'language' => 'F', 'size' => '2X', 'template' => 'unisex', 'version' => 'C'], $product->attributes());
        self::assertSame(['convention'], $product->channels());
        self::assertTrue($product->isActive());
        self::assertSame([], $this->product('NAQ001EOMBK')->channels());

        $group = $this->groups->find('NAQ001:unisex');
        self::assertInstanceOf(ProductGroup::class, $group);
        self::assertSame(['NAQ001', 'unisex', true], [$group->design, $group->template, $group->isActive()]);

        $member = $this->groups->findMember('NAQ001FC2XBK');
        self::assertInstanceOf(GroupMember::class, $member);
        self::assertSame(['NAQ001:unisex', ['version' => 'C', 'size' => '2X', 'colour' => 'BK'], 'F', true], [$member->groupId, $member->values(), $member->language(), $member->isActive()]);
        self::assertCount(12, $this->groups->membersOf('NAQ001:unisex'));
    }

    public function testAGenerationRunAgainChangesNothing(): void
    {
        $this->generation->generate('NAQ001', 'unisex');
        $report = $this->generation->generate('NAQ001', 'unisex');

        self::assertCount(12, $report->unchanged);
        self::assertSame([[], [], [], []], [$report->created, $report->updated, $report->restored, $report->withdrawn]);
        self::assertTrue($report->changedNothing());
        self::assertCount(12, $this->products->products);
    }

    public function testAGenerationUpdatesWhatTheTemplateChanged(): void
    {
        $this->generation->generate('NAQ001', 'unisex');
        $this->product('NAQ001FOMBK')->describe('stale', 'x');
        $template = $this->templates->find('unisex');
        self::assertNotNull($template);
        $template->redefine(Shirts::template(base: 2_600));

        $report = $this->generation->generate('NAQ001', 'unisex');

        self::assertSame(['NAQ001FOMBK', 'NAQ001FOMPK', 'NAQ001FCMBK', 'NAQ001FCMPK', 'NAQ001EOMBK', 'NAQ001EOMPK', 'NAQ001ECMBK', 'NAQ001ECMPK'], $report->updated);
        self::assertSame(['NAQ001FO2XBK', 'NAQ001FC2XBK', 'NAQ001EO2XBK', 'NAQ001EC2XBK'], $report->unchanged);
        self::assertSame(2_600, $this->product('NAQ001FOMBK')->price->minorUnits);
        self::assertNull($this->product('NAQ001FOMBK')->attribute('stale'));
    }

    public function testATaxCategoryOrANameChangeIsAnUpdate(): void
    {
        $this->generation->generate('NAQ001', 'unisex');
        $this->templates->find('unisex')?->classify(ProductKind::Physical, 'clothing');

        self::assertCount(12, $this->generation->generate('NAQ001', 'unisex')->updated);
        self::assertSame('clothing', $this->product('NAQ001FOMBK')->taxCategory());

        $this->product('NAQ001FOMBK')->rename('Renamed');
        self::assertSame(['NAQ001FOMBK'], $this->generation->generate('NAQ001', 'unisex')->updated);

        $this->product('NAQ001FOMBK')->describe('stale', 'x');
        self::assertSame(['NAQ001FOMBK'], $this->generation->generate('NAQ001', 'unisex')->updated);
        self::assertNull($this->product('NAQ001FOMBK')->attribute('stale'));

        $this->product('NAQ001FOMBK')->sellThrough(['web']);
        self::assertSame(['NAQ001FOMBK'], $this->generation->generate('NAQ001', 'unisex')->updated);
        self::assertSame([], $this->product('NAQ001FOMBK')->channels());
    }

    public function testWhatThePlanNoLongerHasIsWithdrawnNeverDeleted(): void
    {
        $this->generation->generate('NAQ001', 'unisex');
        $this->designs->find('NAQ001')?->redefine(Shirts::design(['F']));

        $report = $this->generation->generate('NAQ001', 'unisex');

        self::assertSame(['NAQ001EC2XBK', 'NAQ001ECMBK', 'NAQ001ECMPK', 'NAQ001EO2XBK', 'NAQ001EOMBK', 'NAQ001EOMPK'], $report->withdrawn);
        self::assertCount(6, $report->unchanged);
        self::assertCount(12, $this->products->products);
        self::assertFalse($this->product('NAQ001EOMBK')->isActive());
        self::assertFalse($this->groups->findMember('NAQ001EOMBK')?->isActive());

        self::assertSame([], $this->generation->generate('NAQ001', 'unisex')->withdrawn);

        $this->designs->find('NAQ001')?->redefine(Shirts::design());
        $restored = $this->generation->generate('NAQ001', 'unisex');

        self::assertSame(['NAQ001EOMBK', 'NAQ001EOMPK', 'NAQ001EO2XBK', 'NAQ001ECMBK', 'NAQ001ECMPK', 'NAQ001EC2XBK'], $restored->restored);
        self::assertTrue($this->product('NAQ001EOMBK')->isActive());
        self::assertTrue($this->groups->findMember('NAQ001EOMBK')?->isActive());
    }

    public function testAMemberPlacedAgainTakesItsNewValues(): void
    {
        $this->generation->generate('NAQ001', 'unisex');
        $member = $this->groups->findMember('NAQ001FOMBK');
        self::assertNotNull($member);
        $member->place(['version' => 'X'], null);

        $this->generation->generate('NAQ001', 'unisex');

        self::assertSame(['version' => 'O', 'size' => 'M', 'colour' => 'BK'], $member->values());
        self::assertSame('F', $member->language());
    }

    public function testAWithdrawnGroupWithdrawsItsProductsAndComesBackWhenGeneratedAgain(): void
    {
        $this->generation->generate('NAQ001', 'unisex');
        $this->groups->findMember('NAQ001FOMBK')?->withdraw();

        $report = $this->generation->withdraw('NAQ001', 'unisex');

        self::assertCount(11, $report->withdrawn);
        self::assertNotContains('NAQ001FOMBK', $report->withdrawn);
        self::assertFalse($this->groups->find('NAQ001:unisex')?->isActive());
        self::assertFalse($this->product('NAQ001FOMPK')->isActive());

        $this->generation->generate('NAQ001', 'unisex');

        self::assertTrue($this->groups->find('NAQ001:unisex')?->isActive());
        self::assertTrue($this->product('NAQ001FOMPK')->isActive());
    }

    public function testAWithdrawnMemberWhoseProductIsGoneIsStillWithdrawn(): void
    {
        $this->groups->add(ProductGroup::of('NAQ001', 'unisex'));
        $this->groups->addMember(GroupMember::of('ORPHAN', 'NAQ001:unisex', ['size' => 'M'], null));

        self::assertSame(['ORPHAN'], $this->generation->withdraw('NAQ001', 'unisex')->withdrawn);
    }

    public function testAnUnknownTemplateDesignOrGroupIsRefused(): void
    {
        foreach ([
            [fn (): GenerationReport => $this->generation->generate('NAQ001', 'fitted'), 'No product template has the code fitted.'],
            [fn (): GenerationReport => $this->generation->generate('NAQ002', 'unisex'), 'No design has the code NAQ002.'],
            [fn (): GenerationReport => $this->generation->withdraw('NAQ001', 'unisex'), 'No product group has the identifier NAQ001:unisex.'],
        ] as [$call, $message]) {
            try {
                $call();
                self::fail($message);
            } catch (TemplateRefused $refused) {
                self::assertSame($message, $refused->getMessage());
            }
        }
    }

    public function testAPlanRefusedByTheLibraryIsRefused(): void
    {
        $this->designs->add(Shirts::productDesign(Design::of('ONLY', Label::of(['en' => 'Only']), templates: ['fitted'])));

        $this->expectExceptionObject(new TemplateRefused('The design ONLY is not made on the template unisex.'));

        $this->generation->generate('ONLY', 'unisex');
    }

    public function testASkuOfAnotherGroupIsNeverTakenOver(): void
    {
        $this->groups->addMember(GroupMember::of('NAQ001EC2XBK', 'NAQ001:fitted', ['size' => '2X'], 'E'));

        $this->expectExceptionObject(TemplateRefused::foreignSku('NAQ001EC2XBK', 'NAQ001:fitted'));

        try {
            $this->generation->generate('NAQ001', 'unisex');
        } finally {
            self::assertSame([], $this->products->products);
            self::assertNull($this->groups->find('NAQ001:unisex'));
        }
    }

    public function testAProductOfTheCatalogueOutsideAnyGroupIsNeverTakenOver(): void
    {
        $this->products->add(Product::create('NAQ001FOMBK', ProductKind::Physical, 'Hand made', Shirts::cad(1), 'goods'));

        $this->expectExceptionObject(new TemplateRefused('The SKU NAQ001FOMBK names a product of the catalogue outside any group: it is not taken over.'));

        $this->generation->generate('NAQ001', 'unisex');
    }

    public function testAProductNeverChangesItsKind(): void
    {
        $this->generation->generate('NAQ001', 'unisex');
        $this->templates->find('unisex')?->classify(ProductKind::Digital, 'goods');

        $this->expectExceptionObject(new TemplateRefused('The product NAQ001FOMBK is physical: a product never changes its kind.'));

        $this->generation->generate('NAQ001', 'unisex');
    }

    public function testAProductNeverChangesItsCurrency(): void
    {
        $this->generation->generate('NAQ001', 'unisex');
        $this->templates->find('unisex')?->redefine(Template::of(
            'unisex',
            Label::of(['en' => 'Unisex shirt']),
            Shirts::template()->skuPattern,
            Pricing::of(Money::of(2_500, Currency::of('USD'))),
            Shirts::template()->axes(),
            Shirts::template()->faces(),
            Shirts::template()->exclusions,
        ));

        $this->expectExceptionObject(new TemplateRefused('The product NAQ001FOMBK counts in CAD; USD is refused.'));

        $this->generation->generate('NAQ001', 'unisex');
    }

    public function testADesignWithoutLanguageIsNamedInTheDefaultLocale(): void
    {
        $this->designs->find('NAQ001')?->redefine(Shirts::design([]));
        $generation = new CatalogGeneration($this->templates, $this->designs, $this->groups, $this->products, new VariantPlanner(), 'fr');
        $generation->generate('NAQ001', 'unisex');

        self::assertSame('Le gnome – T-shirt unisexe (Regular, M, Noir)', $this->product('NAQ001OMBK')->name());
        self::assertNull($this->groups->findMember('NAQ001OMBK')?->language());
        self::assertArrayNotHasKey('language', $this->product('NAQ001OMBK')->attributes());
    }

    private function product(string $sku): Product
    {
        $product = $this->products->find($sku);
        self::assertInstanceOf(Product::class, $product);

        return $product;
    }
}
