<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use LogicException;
use Override;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\CommerceCatalogBundle\Domain\Repository\ProductRepository;
use Phil\CommercePromotionsBundle\Application\Promotions;
use Phil\CommercePromotionsBundle\Application\PromotionTexts;
use Phil\CommercePromotionsBundle\Domain\Contract\PromotionRepository;
use Phil\CommercePromotionsBundle\Domain\Exception\PromotionRefused;
use Phil\CommercePromotionsBundle\Domain\Model\Promotion;
use Phil\CommercePromotionsBundle\Domain\Model\PromotionCode;
use Phil\CommercePromotionsBundle\Domain\ValueObject\BundleGroup;
use Phil\CommercePromotionsBundle\Infrastructure\Doctrine\DoctrinePromotionRepository;
use Phil\CommercePromotionsBundle\PhilCommercePromotionsBundle;
use Phil\CommercePromotionsBundle\Tests\Functional\App\TestKernel;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Promotions stored, read back, and plugged into the core: an order built through the container is discounted before
 * the core taxes it, and the uses of its promotions, codes and customers are claimed atomically once it is placed.
 */
#[CoversClass(PhilCommercePromotionsBundle::class)]
#[CoversClass(DoctrinePromotionRepository::class)]
final class PromotionsFlowTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
        $entityManager = $this->entityManager();
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $products = $this->service(ProductRepository::class, ProductRepository::class);
        $products->add(Product::create('DICE', ProductKind::Physical, 'Dice set', Money::of(2_000, Currency::of('CAD')), 'goods'));
        $products->add(Product::create('BOOK', ProductKind::Physical, 'Rulebook', Money::of(3_000, Currency::of('CAD')), 'goods'));

        $promotions = $this->promotions();
        $promotion = Promotion::percentOff('dice-sale', 'Dice sale', 100_000, Currency::of('CAD'))->when(skus: ['DICE'], requiresCode: true);
        $promotions->add($promotion);
        $promotions->addCode(PromotionCode::of('DICE10', $promotion, 1));

        $entityManager->flush();
        $entityManager->clear();
    }

    /**
     * The code takes 10 % off the dice before the core taxes them; once the order is placed and redeemed, the code is
     * used up.
     */
    public function testAPromotionCodeDiscountsAnOrderOnce(): void
    {
        $modifier = $this->modifier();
        $order = Order::open('P', Currency::of('CAD'));
        $modifier->add($order, SellableReference::of('product', 'DICE'), 1, PricingContext::of(1, new DateTimeImmutable()));
        $modifier->changeTaxZone($order, 'QC');
        $modifier->enterCode($order, 'DICE10');

        self::assertSame(1_800 + 90 + 180, $order->total()->minorUnits);

        $entityManager = $this->entityManager();
        $redeemer = $this->service(Promotions::class, Promotions::class);
        $entityManager->wrapInTransaction(static function () use ($modifier, $order, $redeemer): void {
            $modifier->place($order, new DateTimeImmutable());
            $redeemer->redeem($order);
        });
        // A retried job counts nothing more: the order's redemption is stored.
        $entityManager->clear();
        self::assertSame([], $entityManager->wrapInTransaction(static fn (): array => $redeemer->redeem($order)));
        $entityManager->clear();

        $promotions = $this->promotions();
        self::assertSame(1, $promotions->findCode('DICE10')?->uses());
        self::assertSame(1, $promotions->find('dice-sale')?->uses());
        self::assertSame(['dice-sale'], array_map(static fn (Promotion $promotion): string => $promotion->code, $promotions->active()));

        $again = Order::open('Q', Currency::of('CAD'));
        $modifier->add($again, SellableReference::of('product', 'DICE'), 1, PricingContext::of(1, new DateTimeImmutable()));
        $modifier->enterCode($again, 'DICE10');
        self::assertSame(2_000, $again->total()->minorUnits);
    }

    /**
     * Two orders entered the single-use code before either was placed: the second placement is refused when its use
     * is claimed, and rolled back with it.
     */
    public function testTheLastUseOfACodeGoesToOneOrder(): void
    {
        $modifier = $this->modifier();
        $entityManager = $this->entityManager();
        $redeemer = $this->service(Promotions::class, Promotions::class);
        [$first, $second] = array_map(static function (string $number) use ($modifier): Order {
            $order = Order::open($number, Currency::of('CAD'));
            $modifier->add($order, SellableReference::of('product', 'DICE'), 1, PricingContext::of(1, new DateTimeImmutable()));
            $modifier->enterCode($order, 'dice10');
            $modifier->place($order, new DateTimeImmutable());

            return $order;
        }, ['F', 'S']);

        self::assertSame([1_800, 1_800], [$first->total()->minorUnits, $second->total()->minorUnits]);

        $entityManager->wrapInTransaction(static fn (): array => $redeemer->redeem($first));

        try {
            $entityManager->wrapInTransaction(static fn (): array => $redeemer->redeem($second));
            self::fail('The code was used twice.');
        } catch (PromotionRefused $promotionRefused) {
            self::assertSame('The promotion dice-sale or its code DICE10 reached its limit of uses before this order was counted.', $promotionRefused->getMessage());
        }

        $connection = $entityManager->getConnection();
        self::assertSame([1, 1], [$connection->fetchOne("SELECT uses FROM phil_commerce_promotion WHERE code = 'dice-sale'"), $connection->fetchOne("SELECT uses FROM phil_commerce_promotion_code WHERE code = 'DICE10'")]);
        self::assertSame(['F'], $connection->fetchFirstColumn('SELECT order_number FROM phil_commerce_promotion_redemption'));
    }

    /**
     * A code at its limit gives back the use of its promotion claimed just before.
     */
    public function testACodeAtItsLimitGivesThePromotionUseBack(): void
    {
        $entityManager = $this->entityManager();
        $promotions = $this->promotions();

        self::assertSame([true, false], $entityManager->wrapInTransaction(static fn (): array => [$promotions->claimUse('dice-sale', 'DICE10'), $promotions->claimUse('dice-sale', 'DICE10')]));

        $connection = $entityManager->getConnection();
        self::assertSame([1, 1], [$connection->fetchOne("SELECT uses FROM phil_commerce_promotion WHERE code = 'dice-sale'"), $connection->fetchOne("SELECT uses FROM phil_commerce_promotion_code WHERE code = 'DICE10'")]);
        self::assertFalse($entityManager->wrapInTransaction(static fn (): bool => $promotions->claimUse('nowhere', null)));
    }

    /**
     * A customer's uses are counted in one row, created at the first use and claimed under the limit after.
     */
    public function testACustomersUsesAreClaimedUnderTheLimit(): void
    {
        $entityManager = $this->entityManager();
        $promotions = $this->promotions();

        self::assertSame(0, $promotions->customerUses('dice-sale', 'customer-7'));
        self::assertSame([true, true, false], $entityManager->wrapInTransaction(static fn (): array => [
            $promotions->claimCustomerUse('dice-sale', 'customer-7', 2),
            $promotions->claimCustomerUse('dice-sale', 'customer-7', 2),
            $promotions->claimCustomerUse('dice-sale', 'customer-7', 2),
        ]));
        $entityManager->clear();

        self::assertSame(2, $promotions->customerUses('dice-sale', 'customer-7'));
        self::assertSame(0, $promotions->customerUses('dice-sale', 'customer-8'));
        self::assertEquals([2], $entityManager->getConnection()->fetchFirstColumn("SELECT uses FROM phil_commerce_promotion_customer_use WHERE promotion = 'dice-sale' AND customer = 'customer-7'"));
        self::assertTrue($entityManager->wrapInTransaction(static fn (): bool => $promotions->claimCustomerUse('dice-sale', 'customer-7', 3)));
    }

    /**
     * The active promotions only, by code.
     */
    public function testTheActivePromotionsComeByCode(): void
    {
        $entityManager = $this->entityManager();
        $promotions = $this->promotions();
        $promotions->add(Promotion::percentOff('z-last', 'Last', 1, Currency::of('CAD')));
        $promotions->add(Promotion::percentOff('a-first', 'First', 1, Currency::of('CAD'))->when(after: ['z-last']));

        $withdrawn = Promotion::percentOff('m-off', 'Off', 1, Currency::of('CAD'));
        $withdrawn->withdraw();

        $promotions->add($withdrawn);
        $entityManager->flush();
        $entityManager->clear();

        self::assertSame(['a-first', 'dice-sale', 'z-last'], array_map(static fn (Promotion $promotion): string => $promotion->code, $promotions->active()));
    }

    /**
     * A bundle and its groups, a label and its translation, stored and read back.
     */
    public function testABundleAndItsTranslationAreReadBack(): void
    {
        $entityManager = $this->entityManager();
        $promotions = $this->promotions();
        $bundle = Promotion::bundlePrice('starter', 'Starter box', [BundleGroup::of(['BOOK']), BundleGroup::of(['DICE'], 2)], Money::of(6_000, Currency::of('CAD')))->when(excludedSkus: ['GIFT'], perCustomerLimit: 1);
        $promotions->add($bundle);
        $this->service(PromotionTexts::class, PromotionTexts::class)->translate($bundle, 'fr')->relabel('Boîte de départ');
        $entityManager->flush();
        $entityManager->clear();

        $read = $promotions->find('starter');
        self::assertNotNull($read);
        self::assertSame([['BOOK'], ['DICE']], array_map(static fn (BundleGroup $group): array => $group->skus, $read->groups()));
        self::assertSame([1, 2], array_map(static fn (BundleGroup $group): int => $group->quantity, $read->groups()));
        self::assertSame([1, ['GIFT']], [$read->perCustomerLimit, $read->excludedSkus]);
        self::assertSame('Boîte de départ', $this->service(PromotionTexts::class, PromotionTexts::class)->of([$read], 'fr')->label($read));

        $modifier = $this->modifier();
        $order = Order::open('B', Currency::of('CAD'));
        $order->assignCustomer('customer-7');

        $modifier->add($order, SellableReference::of('product', 'BOOK'), 1, PricingContext::of(1, new DateTimeImmutable()));
        $modifier->add($order, SellableReference::of('product', 'DICE'), 2, PricingContext::of(2, new DateTimeImmutable()));
        self::assertSame(7_000 - 1_000, $order->total()->minorUnits);

        $redeemer = $this->service(Promotions::class, Promotions::class);
        $entityManager->wrapInTransaction(static function () use ($modifier, $order, $redeemer): void {
            $modifier->place($order, new DateTimeImmutable());
            $redeemer->redeem($order);
        });
        self::assertSame(1, $promotions->customerUses('starter', 'customer-7'));
    }

    public function testUsesAreClaimedOnlyInsideATransaction(): void
    {
        foreach ([
            fn (): bool => $this->promotions()->claimUse('dice-sale', null),
            fn (): bool => $this->promotions()->claimCustomerUse('dice-sale', 'customer-7', 1),
        ] as $claim) {
            try {
                $claim();
                self::fail('A use was claimed outside a transaction.');
            } catch (LogicException $logicException) {
                self::assertStringContainsString('Uses of promotions are claimed inside the transaction that places the order', $logicException->getMessage());
            }
        }
    }

    private function modifier(): OrderModifier
    {
        return $this->service(OrderModifier::class, OrderModifier::class);
    }

    private function promotions(): PromotionRepository
    {
        return $this->service(PromotionRepository::class, PromotionRepository::class);
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
