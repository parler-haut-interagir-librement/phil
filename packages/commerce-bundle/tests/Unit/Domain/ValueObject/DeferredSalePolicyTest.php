<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\ValueObject;

use Phil\CommerceBundle\Domain\Deferred\BalanceTrigger;
use Phil\CommerceBundle\Domain\Deferred\Fulfilment;
use Phil\CommerceBundle\Domain\Deferred\StockCommitment;
use Phil\CommerceBundle\Domain\ValueObject\DeferredSalePolicy;
use Phil\CommerceBundle\Domain\ValueObject\Deposit;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeferredSalePolicy::class)]
final class DeferredSalePolicyTest extends TestCase
{
    public function testThePreorderIsCalledByTheGoodsAndLeavesOncePaid(): void
    {
        $policy = DeferredSalePolicy::preorder(Deposit::share(1_000));

        self::assertSame([BalanceTrigger::Availability, StockCommitment::OnBalance, Fulfilment::OnBalance, null], [$policy->trigger, $policy->commitment, $policy->fulfilment, $policy->dueAfterDays]);
        self::assertSame(1_000, $policy->deposit->basisPoints);
    }

    public function testAPreorderMayHoldTheStockFromTheOrderUntilAReleaseDate(): void
    {
        $policy = DeferredSalePolicy::preorder(Deposit::none(), BalanceTrigger::Date, StockCommitment::OnOrder, 30);

        self::assertSame([BalanceTrigger::Date, StockCommitment::OnOrder, Fulfilment::OnBalance, 30], [$policy->trigger, $policy->commitment, $policy->fulfilment, $policy->dueAfterDays]);
    }

    public function testInstallmentsLetTheGoodsLeaveOnTheDeposit(): void
    {
        $policy = DeferredSalePolicy::installments(Deposit::share(5_000), 30);

        self::assertSame([BalanceTrigger::Date, StockCommitment::OnOrder, Fulfilment::OnDeposit, 30], [$policy->trigger, $policy->commitment, $policy->fulfilment, $policy->dueAfterDays]);
    }

    public function testALayawayPutsTheGoodsAsideUntilPaid(): void
    {
        $policy = DeferredSalePolicy::layaway(Deposit::share(2_000), 60);

        self::assertSame([BalanceTrigger::Date, StockCommitment::OnOrder, Fulfilment::OnBalance, 60], [$policy->trigger, $policy->commitment, $policy->fulfilment, $policy->dueAfterDays]);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function daysAllowed(): iterable
    {
        yield 'one' => [1];
        yield 'the most' => [DeferredSalePolicy::MAX_DAYS];
    }

    #[DataProvider('daysAllowed')]
    public function testDaysWithinTheBounds(int $days): void
    {
        self::assertSame($days, DeferredSalePolicy::layaway(Deposit::none(), $days)->dueAfterDays);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function daysRefused(): iterable
    {
        yield 'none' => [0];
        yield 'beyond the most' => [DeferredSalePolicy::MAX_DAYS + 1];
    }

    #[DataProvider('daysRefused')]
    public function testDaysOutsideTheBounds(int $days): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A balance is due 1 to 3650 days after the sale opens, not %d.', $days));

        DeferredSalePolicy::installments(Deposit::none(), $days);
    }

    public function testOnlyADateIsCountedInDays(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('Only a balance called by a date is due after a number of days, not one called by shipment.');

        new DeferredSalePolicy(Deposit::none(), BalanceTrigger::Shipment, StockCommitment::OnOrder, Fulfilment::OnBalance, 10);
    }
}
