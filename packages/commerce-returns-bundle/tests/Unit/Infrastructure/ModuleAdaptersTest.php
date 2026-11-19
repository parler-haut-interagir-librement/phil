<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceCatalogBundle\Application\CompositeProducts;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\CommerceCatalogBundle\Domain\Repository\ProductRepository;
use Phil\CommerceCatalogBundle\Domain\ValueObject\ProductComponent;
use Phil\CommerceLogisticsBundle\Domain\Model\Shipment;
use Phil\CommerceLogisticsBundle\Domain\Repository\ShipmentRepository;
use Phil\CommercePromotionsBundle\Domain\Contract\PromotionRepository;
use Phil\CommercePromotionsBundle\Domain\Model\Promotion;
use Phil\CommercePromotionsBundle\Domain\Model\PromotionCode;
use Phil\CommercePromotionsBundle\Domain\Model\PromotionRedemption;
use Phil\CommercePromotionsBundle\Domain\ValueObject\BundleGroup;
use Phil\CommerceReturnsBundle\Infrastructure\Catalog\CatalogSetParts;
use Phil\CommerceReturnsBundle\Infrastructure\Logistics\LogisticsShipmentDates;
use Phil\CommerceReturnsBundle\Infrastructure\Promotions\PromotionSpecials;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CatalogSetParts::class)]
#[CoversClass(PromotionSpecials::class)]
#[CoversClass(LogisticsShipmentDates::class)]
final class ModuleAdaptersTest extends TestCase
{
    public function testASetIsACompositeProductOfTheCatalogue(): void
    {
        $cad = Currency::of('CAD');
        $box = Product::create('BOX', ProductKind::Physical, 'Box', Money::of(5_000, $cad), 'goods');
        $box->composeOf([ProductComponent::of('RULES'), ProductComponent::of('DICE', 2)]);

        $products = new readonly class([$box, Product::create('DICE', ProductKind::Physical, 'Dice', Money::of(500, $cad), 'goods')]) implements ProductRepository {
            /**
             * @param list<Product> $products
             */
            public function __construct(private array $products)
            {
            }

            #[Override]
            public function find(string $sku): ?Product
            {
                return array_find($this->products, static fn (Product $product): bool => $product->sku === $sku);
            }

            #[Override]
            public function add(Product $product): void
            {
            }
        };
        $sets = new CatalogSetParts(new CompositeProducts($products));

        self::assertSame(['product:RULES', 'product:DICE'], $sets->partsOf(SellableReference::of('product', 'BOX')));
        self::assertSame([], $sets->partsOf(SellableReference::of('product', 'DICE')));
        self::assertSame([], $sets->partsOf(SellableReference::of('offer', 'BOX')));
    }

    public function testASpecialIsABundlePrice(): void
    {
        $cad = Currency::of('CAD');
        $promotions = $this->promotions([
            Promotion::bundlePrice('duo', 'Duo', [BundleGroup::of(['BOOK'], 1), BundleGroup::of(['DICE'], 1)], Money::of(4_000, $cad)),
            Promotion::percentOff('ten', '10 %', 100_000, $cad),
        ]);
        $specials = new PromotionSpecials($promotions);
        $line = SellableReference::of('product', 'BOOK');

        self::assertTrue($specials->isSpecial(Adjustment::of(AdjustmentType::Promotion, 'Duo', Money::of(-500, $cad), $line, origin: 'promotion:duo')));
        self::assertTrue($specials->isSpecial(Adjustment::of(AdjustmentType::Promotion, 'Duo', Money::of(-500, $cad), $line, origin: 'promotion:duo:CODE')));
        self::assertFalse($specials->isSpecial(Adjustment::of(AdjustmentType::Promotion, '10 %', Money::of(-500, $cad), $line, origin: 'promotion:ten')));
        self::assertFalse($specials->isSpecial(Adjustment::of(AdjustmentType::Promotion, 'Gone', Money::of(-500, $cad), $line, origin: 'promotion:gone')));
        self::assertFalse($specials->isSpecial(Adjustment::of(AdjustmentType::Promotion, 'Manual', Money::of(-500, $cad), $line, origin: 'manual:duo')));
        self::assertFalse($specials->isSpecial(Adjustment::of(AdjustmentType::Promotion, 'None', Money::of(-500, $cad), $line)));
    }

    public function testTheLastShipmentOfAnOrderCounts(): void
    {
        $shipments = new class implements ShipmentRepository {
            #[Override]
            public function addShipment(Shipment $shipment): void
            {
            }

            #[Override]
            public function shipmentsOf(string $orderNumber): array
            {
                return 'A-1' === $orderNumber ? [
                    Shipment::of('A-1', ['product:A' => 1], new DateTimeImmutable('2026-11-05')),
                    Shipment::of('A-1', ['product:B' => 1], new DateTimeImmutable('2026-11-09')),
                    Shipment::of('A-1', ['product:C' => 1], new DateTimeImmutable('2026-11-07')),
                ] : [];
            }
        };
        $dates = new LogisticsShipmentDates($shipments);

        self::assertEquals(new DateTimeImmutable('2026-11-09'), $dates->lastShippedAt('A-1'));
        self::assertNull($dates->lastShippedAt('B-1'));
    }

    public function testOfTwoShipmentsAtTheSameMomentTheFirstIsKept(): void
    {
        $first = new DateTimeImmutable('2026-11-05');
        $shipments = new readonly class($first) implements ShipmentRepository {
            public function __construct(private DateTimeImmutable $first)
            {
            }

            #[Override]
            public function addShipment(Shipment $shipment): void
            {
            }

            #[Override]
            public function shipmentsOf(string $orderNumber): array
            {
                return [Shipment::of('A-1', ['product:A' => 1], $this->first), Shipment::of('A-1', ['product:B' => 1], new DateTimeImmutable('2026-11-05'))];
            }
        };

        self::assertSame($first, new LogisticsShipmentDates($shipments)->lastShippedAt('A-1'));
    }

    /**
     * @param list<Promotion> $promotions
     */
    private function promotions(array $promotions): PromotionRepository
    {
        return new readonly class($promotions) implements PromotionRepository {
            /**
             * @param list<Promotion> $promotions
             */
            public function __construct(private array $promotions)
            {
            }

            #[Override]
            public function find(string $code): ?Promotion
            {
                return array_find($this->promotions, static fn (Promotion $promotion): bool => $promotion->code === $code);
            }

            #[Override]
            public function active(): array
            {
                return $this->promotions;
            }

            #[Override]
            public function add(Promotion $promotion): void
            {
            }

            #[Override]
            public function findCode(string $code): ?PromotionCode
            {
                return null;
            }

            #[Override]
            public function addCode(PromotionCode $code): void
            {
            }

            #[Override]
            public function claimUse(string $promotion, ?string $code): bool
            {
                return true;
            }

            #[Override]
            public function customerUses(string $promotion, string $customer): int
            {
                return 0;
            }

            #[Override]
            public function claimCustomerUse(string $promotion, string $customer, int $limit): bool
            {
                return true;
            }

            #[Override]
            public function findRedemption(string $orderNumber, string $promotion): ?PromotionRedemption
            {
                return null;
            }

            #[Override]
            public function addRedemption(PromotionRedemption $redemption): void
            {
            }
        };
    }
}
