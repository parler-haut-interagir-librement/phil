<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Phil\CommerceBundle\Domain\Model\DeferredSale;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\DeferredSalePolicy;
use Phil\CommerceBundle\Domain\ValueObject\Deposit;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrineDeferredSaleRepository;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctrineDeferredSaleRepository::class)]
final class DoctrineDeferredSaleRepositoryTest extends TestCase
{
    public function testASaleIsAddedThroughTheEntityManager(): void
    {
        $sale = $this->sale();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($sale);

        new DoctrineDeferredSaleRepository($entityManager)->addDeferredSale($sale);
    }

    /**
     * The balances due are the order numbers DBAL reads, in its order, renumbered once what is not a number is left out.
     */
    public function testTheBalancesDueAreTheOrderNumbersReadInOrder(): void
    {
        $at = new DateTimeImmutable('2026-11-11 10:00:00');
        $result = self::createStub(Result::class);
        $result->method('fetchFirstColumn')->willReturn(['A-3', null, 'A-1']);

        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new SQLitePlatform());
        $connection->method('createQueryBuilder')->willReturnCallback(static fn (): QueryBuilder => new QueryBuilder($connection));
        $connection->expects($this->once())
            ->method('executeQuery')
            ->with(
                'SELECT order_number FROM phil_commerce_deferred_sale WHERE (state = :state) AND (balance_trigger = :trigger) AND (due_at <= :at) ORDER BY due_at, order_number LIMIT 2',
                ['state' => 'awaiting_balance', 'trigger' => 'date', 'at' => $at],
                ['state' => ParameterType::STRING, 'trigger' => ParameterType::STRING, 'at' => Types::DATETIME_IMMUTABLE],
            )
            ->willReturn($result);

        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        self::assertSame(['A-3', 'A-1'], new DoctrineDeferredSaleRepository($entityManager)->dueForBalance($at, 2));
    }

    private function sale(): DeferredSale
    {
        $price = Money::of(1_000, Currency::of('CAD'));
        $order = Order::open('1', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'A'), 'A', $price, 'standard'), 1, $price);
        $order->place(new DateTimeImmutable('2026-11-11 10:00:00'));

        return DeferredSale::open($order, DeferredSalePolicy::layaway(Deposit::none(), 10), new DateTimeImmutable('2026-11-11 10:00:00'))[0];
    }
}
