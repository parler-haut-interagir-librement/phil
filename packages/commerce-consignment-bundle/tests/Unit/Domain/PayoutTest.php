<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Unit\Domain;

use Phil\CommerceConsignmentBundle\Domain\Consignor\PayoutState;
use Phil\CommerceConsignmentBundle\Domain\Exception\InvalidValue;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignorPayout;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ConsignorBalance;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\Fixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConsignorPayout::class)]
#[CoversClass(ConsignorBalance::class)]
final class PayoutTest extends TestCase
{
    public function testAPayoutKeepsWhatWasPaidAndItsReversalCancelsIt(): void
    {
        $payout = ConsignorPayout::record('PAY-1', Owner::consignor(Fixtures::AUTHOR), Fixtures::cad(4500), Fixtures::at(9), 'transfer', 'TRX-778', Fixtures::at(10));

        self::assertSame('PAY-1', $payout->idempotencyKey);
        self::assertSame(Fixtures::AUTHOR, $payout->consignor);
        self::assertTrue($payout->owner()->equals(Owner::consignor(Fixtures::AUTHOR)));
        self::assertSame(4500, $payout->amount()->minorUnits);
        self::assertSame('CAD', $payout->amount()->currency->code);
        self::assertSame('2026-11-20 14:00:00 UTC', $payout->paidAt()->format('Y-m-d H:i:s T'));
        self::assertSame('2026-11-20 15:00:00 UTC', $payout->recordedAt()->format('Y-m-d H:i:s T'));
        self::assertSame(['transfer', 'TRX-778', null], [$payout->method, $payout->reference, $payout->reverses]);
        self::assertFalse($payout->isReversal());

        $reversal = $payout->reversal('PAY-1-R', Fixtures::at(11));
        self::assertNotSame($payout->id, $reversal->id);
        self::assertSame(['PAY-1-R', Fixtures::AUTHOR, 'transfer', 'TRX-778', $payout->id], [$reversal->idempotencyKey, $reversal->consignor, $reversal->method, $reversal->reference, $reversal->reverses]);
        self::assertSame(-4500, $reversal->amount()->minorUnits);
        self::assertSame('2026-11-20 16:00:00 UTC', $reversal->paidAt()->format('Y-m-d H:i:s T'));
        self::assertSame('2026-11-20 16:00:00 UTC', $reversal->recordedAt()->format('Y-m-d H:i:s T'));
        self::assertTrue($reversal->isReversal());

        $this->expectExceptionMessageIsOrContains(\sprintf('The payout %s reverses another; record the payout again instead of reversing its reversal.', $reversal->id));
        $reversal->reversal('PAY-1-R-R', Fixtures::at(12));
    }

    public function testAPayoutMayHaveNoReference(): void
    {
        self::assertNull(ConsignorPayout::record('k', Owner::consignor('c'), Fixtures::cad(1), Fixtures::at(9), str_repeat('a', 32), null, Fixtures::at(10))->reference);
    }

    /**
     * @return iterable<string, array{string, int, string, string|null, string, Owner|null}>
     */
    public static function refused(): iterable
    {
        yield 'the shop' => ['k', 100, 'cash', null, 'A payout is made to a consignor, not to the shop.', Owner::shop()];
        yield 'zero' => ['k', 0, 'cash', null, 'A payout is more than zero; 0.00 is not.', null];
        yield 'negative' => ['k', -1, 'cash', null, 'A payout is more than zero; -0.01 is not.', null];
        yield 'an empty method' => ['k', 1, '', null, 'A payout method has 1 to 32 lowercase letters, digits, dots, dashes or underscores; "" does not.', null];
        yield 'a capital' => ['k', 1, 'Cash', null, 'A payout method has 1 to 32 lowercase letters', null];
        yield 'a method from a dash' => ['k', 1, '-cash', null, 'A payout method has 1 to 32 lowercase letters', null];
        yield 'a long method' => ['k', 1, str_repeat('a', 33), null, 'A payout method has 1 to 32 lowercase letters', null];
        yield 'a method and a new line' => ['k', 1, "cash\n", null, 'A payout method has 1 to 32 lowercase letters', null];
        yield 'an empty key' => ['', 1, 'cash', null, 'A payout key has 1 to 64 characters, none of them a control character; "" does not.', null];
        yield 'a long reference' => ['k', 1, 'cash', str_repeat('r', 65), 'A payout reference has 1 to 64 characters', null];
    }

    #[DataProvider('refused')]
    public function testAPayoutIsChecked(string $key, int $cents, string $method, ?string $reference, string $message, ?Owner $owner): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);
        ConsignorPayout::record($key, $owner ?? Owner::consignor(Fixtures::AUTHOR), Fixtures::cad($cents), Fixtures::at(9), $method, $reference, Fixtures::at(10));
    }

    public function testAReversalHasAKeyOfItsOwn(): void
    {
        $payout = ConsignorPayout::record('k', Owner::consignor('c'), Fixtures::cad(1), Fixtures::at(9), 'cash.box_2-b', null, Fixtures::at(10));

        $this->expectException(InvalidValue::class);
        $payout->reversal("\t", Fixtures::at(11));
    }

    /**
     * @return iterable<string, array{int, int, PayoutState, int}>
     */
    public static function balances(): iterable
    {
        yield 'nothing paid' => [5000, 0, PayoutState::Due, 5000];
        yield 'paid in part' => [5000, 1, PayoutState::PartlyPaid, 4999];
        yield 'paid' => [5000, 5000, PayoutState::Paid, 0];
        yield 'a return after the payout' => [3000, 5000, PayoutState::Paid, -2000];
        yield 'nothing owed' => [0, 0, PayoutState::Paid, 0];
        yield 'returns only' => [-1500, 0, PayoutState::Paid, -1500];
        yield 'a payout reversed' => [5000, 0, PayoutState::Due, 5000];
    }

    #[DataProvider('balances')]
    public function testTheBalanceAndItsState(int $owed, int $paid, PayoutState $state, int $balance): void
    {
        $result = ConsignorBalance::of(Owner::consignor(Fixtures::AUTHOR), Fixtures::cad($owed), Fixtures::cad($paid));

        self::assertSame([$owed, $paid, $balance], [$result->owed->minorUnits, $result->paid->minorUnits, $result->balance->minorUnits]);
        self::assertSame($state, $result->state);
        self::assertSame(Fixtures::AUTHOR, $result->consignor->consignor);
    }
}
