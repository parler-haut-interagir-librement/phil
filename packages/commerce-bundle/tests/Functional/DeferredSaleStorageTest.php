<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Phil\CommerceBundle\Application\DeferredSales;
use Phil\CommerceBundle\Domain\Contract\DeferredSaleRepository;
use Phil\CommerceBundle\Domain\Deferred\DeferredSaleState;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\DeferredSalePolicy;
use Phil\CommerceBundle\Domain\ValueObject\Deposit;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineDeferredSaleRepository;
use Phil\CommerceBundle\PhilCommerceBundle;
use Phil\CommerceBundle\Tests\Functional\App\HeardEvents;
use Phil\CommerceBundle\Tests\Functional\App\HeldStock;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Deferred sales through the container: the stock port autoconfigured, the events dispatched, the sale stored in its
 * row and read back, the balances due listed by DBAL.
 */
#[CoversClass(PhilCommerceBundle::class)]
#[CoversClass(DoctrineDeferredSaleRepository::class)]
final class DeferredSaleStorageTest extends FunctionalTestCase
{
    private EntityManagerInterface $entityManager;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        $this->createSchema($this->entityManager);
        $this->at = new DateTimeImmutable('2026-11-11 10:00:00');
    }

    public function testALayawayIsStoredAndItsBalanceCalledWhenDue(): void
    {
        $sales = $this->service(DeferredSales::class, DeferredSales::class);
        $stock = $this->service(HeldStock::class, HeldStock::class);
        $heard = $this->service(HeardEvents::class, HeardEvents::class);

        foreach (['A-1' => 10, 'A-2' => 20, 'A-3' => 5] as $number => $days) {
            $sales->open($this->placed($number), DeferredSalePolicy::layaway(Deposit::none(), $days), $this->at);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(['A-1' => true, 'A-2' => true, 'A-3' => true], $stock->held);
        self::assertSame(['StockCommitted A-1', 'StockCommitted A-2', 'StockCommitted A-3'], $heard->heard);

        $repository = $this->service(DeferredSaleRepository::class, DeferredSaleRepository::class);
        $sale = $repository->findDeferredSale('A-1');
        self::assertNotNull($sale);
        self::assertSame([DeferredSaleState::AwaitingBalance, true, 0, '2026-11-21 10:00:00'], [$sale->state(), $sale->isStockCommitted(), $sale->deposit->minorUnits, $sale->dueAt?->format('Y-m-d H:i:s')]);

        self::assertSame(['A-3', 'A-1'], $repository->dueForBalance($this->at->modify('+15 days'), 10));
        self::assertSame(['A-3'], $repository->dueForBalance($this->at->modify('+15 days'), 1));

        self::assertSame(['A-3', 'A-1'], $sales->callDue($this->at->modify('+15 days')));
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame([], $repository->dueForBalance($this->at->modify('+15 days'), 10));
        self::assertSame(DeferredSaleState::BalanceDue, $repository->findDeferredSale('A-3')?->state());
    }

    private function placed(string $number): Order
    {
        $price = Money::of(1_000, Currency::of('CAD'));
        $order = Order::open($number, Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'A'), 'A', $price, 'standard'), 1, $price);
        $order->place($this->at);

        $this->entityManager->persist($order);

        return $order;
    }
}
