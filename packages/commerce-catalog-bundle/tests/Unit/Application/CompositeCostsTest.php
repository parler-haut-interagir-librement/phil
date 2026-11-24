<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit\Application;

use Override;
use Phil\CommerceCatalogBundle\Application\CompositeCosts;
use Phil\CommerceCatalogBundle\Application\Contract\UnitCosts;
use Phil\CommerceCatalogBundle\Domain\Exception\CatalogRefused;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\CommerceCatalogBundle\Domain\ValueObject\ProductComponent;
use Phil\CommerceCatalogBundle\Tests\Unit\Support\InMemoryProducts;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The cost of a product: a composite's the sum of its parts', each times its units, unknown while one part's is; any
 * other product's what the source of unit costs says; nothing without a source.
 */
#[CoversClass(CompositeCosts::class)]
final class CompositeCostsTest extends TestCase
{
    private InMemoryProducts $products;

    /** @var array<string, Money> */
    private array $known = [];

    /** @var list<string> */
    private array $asked = [];

    #[Override]
    protected function setUp(): void
    {
        $box = $this->product('BOX');
        $box->composeOf([ProductComponent::of('BOOK'), ProductComponent::of('DICE', 3)]);

        $shirt = $this->product('SHIRT');
        $shirt->defineAxes(['size']);

        $this->products = new InMemoryProducts($box, $this->product('BOOK'), $this->product('DICE'), $shirt);
        $this->known = ['BOOK' => $this->cad(1_250), 'DICE' => $this->cad(333), 'BOX' => $this->cad(9_999), 'SHIRT' => $this->cad(500)];
    }

    public function testACompositeCostsTheSumOfItsParts(): void
    {
        self::assertSame([2_249, 'CAD'], [$this->costs()->costOf('BOX')?->minorUnits, $this->costs()->costOf('BOX')?->currency->code]);
        self::assertSame(['BOOK', 'DICE', 'BOOK', 'DICE'], $this->asked);
    }

    public function testAnyOtherProductCostsWhatTheSourceSays(): void
    {
        self::assertSame(1_250, $this->costs()->costOf('BOOK')?->minorUnits);
        self::assertSame(['BOOK'], $this->asked);
        self::assertNull($this->costs()->costOf('NOWHERE'));
        $this->known['ELSEWHERE'] = $this->cad(7);
        self::assertSame(7, $this->costs()->costOf('ELSEWHERE')?->minorUnits);
    }

    public function testACompositeWhosePartCostIsUnknownHasNoCost(): void
    {
        unset($this->known['BOOK']);

        self::assertNull($this->costs()->costOf('BOX'));
        self::assertSame(['BOOK'], $this->asked);
    }

    public function testAFamilyHasNoCost(): void
    {
        self::assertNull($this->costs()->costOf('SHIRT'));
        self::assertSame([], $this->asked);
    }

    public function testWithoutASourceNoCostIsKnown(): void
    {
        $costs = new CompositeCosts($this->products);

        self::assertNull($costs->costOf('BOX'));
        self::assertNull($costs->costOf('BOOK'));
    }

    public function testThePartsCostInOneCurrency(): void
    {
        $this->known['DICE'] = Money::of(333, Currency::of('USD'));

        $this->expectExceptionObject(CatalogRefused::currency('The parts of BOX', 'CAD', 'USD'));

        $this->costs()->costOf('BOX');
    }

    private function costs(): CompositeCosts
    {
        $units = new readonly class($this) implements UnitCosts {
            public function __construct(private CompositeCostsTest $test)
            {
            }

            #[Override]
            public function unitCost(string $sku): ?Money
            {
                return $this->test->ask($sku);
            }
        };

        return new CompositeCosts($this->products, $units);
    }

    /**
     * @internal the source of unit costs of the tests
     */
    public function ask(string $sku): ?Money
    {
        $this->asked[] = $sku;

        return $this->known[$sku] ?? null;
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }

    private function product(string $sku): Product
    {
        return Product::create($sku, ProductKind::Physical, $sku, $this->cad(1_000), 'goods');
    }
}
