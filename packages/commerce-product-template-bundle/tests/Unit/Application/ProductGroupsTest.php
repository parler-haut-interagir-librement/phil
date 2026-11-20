<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Tests\Unit\Application;

use Override;
use Phil\CommerceProductTemplate\VariantPlanner;
use Phil\CommerceProductTemplateBundle\Application\CatalogGeneration;
use Phil\CommerceProductTemplateBundle\Application\GroupPage;
use Phil\CommerceProductTemplateBundle\Application\ProductGroups;
use Phil\CommerceProductTemplateBundle\Domain\Exception\TemplateRefused;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryDesigns;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryGroups;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryProducts;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryTemplates;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\Shirts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProductGroups::class)]
#[CoversClass(GroupPage::class)]
final class ProductGroupsTest extends TestCase
{
    private InMemoryTemplates $templates;

    private InMemoryDesigns $designs;

    private InMemoryGroups $groups;

    private CatalogGeneration $generation;

    #[Override]
    protected function setUp(): void
    {
        $this->templates = new InMemoryTemplates(Shirts::productTemplate(Shirts::template(['BK', 'PK', 'WH'])));
        $this->designs = new InMemoryDesigns(Shirts::productDesign(Shirts::design(['F'])));
        $this->groups = new InMemoryGroups();
        $this->generation = new CatalogGeneration($this->templates, $this->designs, $this->groups, new InMemoryProducts(), new VariantPlanner(), 'en');
        $this->generation->generate('NAQ001', 'unisex');
    }

    public function testAPageHasASelectorPerAxisWithTheValuesSold(): void
    {
        $this->groups->findMember('NAQ001FOMPK')?->withdraw();
        $this->groups->findMember('NAQ001FCMPK')?->withdraw();
        $page = new ProductGroups($this->groups, $this->templates, $this->designs)->page('NAQ001', 'unisex', 'fr');

        self::assertInstanceOf(GroupPage::class, $page);
        self::assertSame('NAQ001:unisex', $page->id);
        self::assertSame('Le gnome – T-shirt unisexe', $page->name);
        self::assertSame([
            ['code' => 'version', 'label' => 'Version', 'values' => [['code' => 'O', 'label' => 'Regular', 'hex' => null, 'photo' => null], ['code' => 'C', 'label' => 'Convention', 'hex' => null, 'photo' => null]]],
            ['code' => 'size', 'label' => 'Taille', 'values' => [['code' => 'M', 'label' => 'M', 'hex' => null, 'photo' => null], ['code' => '2X', 'label' => '2XL', 'hex' => null, 'photo' => null]]],
            ['code' => 'colour', 'label' => 'Couleur', 'values' => [['code' => 'BK', 'label' => 'Noir', 'hex' => '#1A1A1A', 'photo' => null], ['code' => 'WH', 'label' => 'Blanc', 'hex' => null, 'photo' => null]]],
        ], $page->axes);
        self::assertSame(range(0, 7), array_keys($page->members));
        self::assertSame(['sku' => 'NAQ001FC2XBK', 'values' => ['version' => 'C', 'size' => '2X', 'colour' => 'BK'], 'language' => 'F', 'mockups' => []], $page->members[0]);
    }

    public function testAPageFindsTheSkuOfTheValuesChosen(): void
    {
        $page = new ProductGroups($this->groups, $this->templates, $this->designs)->page('NAQ001', 'unisex', 'en');
        self::assertNotNull($page);

        self::assertSame('NAQ001FO2XBK', $page->skuFor(['size' => '2X', 'version' => 'O', 'colour' => 'BK'], 'F'));
        self::assertSame('NAQ001FO2XBK', $page->skuFor(['colour' => 'BK', 'size' => '2X', 'version' => 'O'], 'F'));
        self::assertNull($page->skuFor(['colour' => 'BK', 'size' => '2X', 'version' => 'O']));
        self::assertNull($page->skuFor(['colour' => 'PK', 'size' => '2X', 'version' => 'O'], 'F'));
        self::assertNull($page->skuFor(['colour' => 'BK', 'size' => '2X'], 'F'));
    }

    public function testAWithdrawnGroupHasNoPage(): void
    {
        $this->generation->withdraw('NAQ001', 'unisex');

        self::assertNull(new ProductGroups($this->groups, $this->templates, $this->designs)->page('NAQ001', 'unisex', 'en'));
    }

    public function testAnUnknownGroupTemplateOrDesignIsRefused(): void
    {
        foreach ([
            [new ProductGroups($this->groups, $this->templates, $this->designs), 'NAQ002', 'No product group has the identifier NAQ002:unisex.'],
            [new ProductGroups($this->groups, new InMemoryTemplates(), $this->designs), 'NAQ001', 'No product template has the code unisex.'],
            [new ProductGroups($this->groups, $this->templates, new InMemoryDesigns()), 'NAQ001', 'No design has the code NAQ001.'],
        ] as [$groups, $design, $message]) {
            try {
                $groups->page($design, 'unisex', 'en');
                self::fail($message);
            } catch (TemplateRefused $refused) {
                self::assertSame($message, $refused->getMessage());
            }
        }
    }
}
