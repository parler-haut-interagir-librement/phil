<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Exception\UnknownSellable;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommercePosBundle\Application\Contract\OfflineSaleGate;
use Phil\CommercePosBundle\Application\Layaways;
use Phil\CommercePosBundle\Application\OfflineSale;
use Phil\CommercePosBundle\Application\OfflineSales;
use Phil\CommercePosBundle\Domain\Exception\OfflineSaleForbidden;
use Phil\CommercePosBundle\Domain\Exception\PosRefused;
use Phil\CommercePosBundle\Domain\Layaway\LayawayState;
use Phil\CommercePosBundle\Domain\Model\CashSession;
use Phil\CommercePosBundle\Domain\Model\SessionPayment;
use Phil\CommercePosBundle\Domain\Register\RegisterRole;
use Phil\CommercePosBundle\Tests\Unit\Support\Till;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Layaways::class)]
#[CoversClass(OfflineSale::class)]
#[CoversClass(OfflineSales::class)]
final class LayawayAndOfflineTest extends TestCase
{
    private Till $till;

    private DateTimeImmutable $at;

    private CashSession $session;

    protected function setUp(): void
    {
        $this->till = new Till();
        $this->at = new DateTimeImmutable('2026-11-03 10:00:00');
        $this->session = $this->till->sessions->open('front', 'ada', $this->cad(0), $this->at);
    }

    public function testALayawayOpensOnItsDepositAndCompletesWhenPaid(): void
    {
        $order = $this->placed(['MUG' => 3]);
        self::assertSame(900, $this->till->layaways->depositFor($order)->minorUnits);
        $this->till->registerPayments->recorded($order, $this->session, 'debit', $this->cad(900), null, $this->at);

        $layaway = $this->till->layaways->open($order, 'ada', $this->at->modify('+30 days'), $this->at);

        self::assertSame(['A-1', 'ada', LayawayState::Open], [$layaway->orderNumber, $layaway->customer, $layaway->state()]);
        self::assertFalse($this->till->layaways->settle($order, $this->at));
        self::assertSame(LayawayState::Open, $layaway->state());

        $this->till->registerPayments->recorded($order, $this->session, 'debit', $this->cad(2_700), null, $this->at);
        self::assertTrue($this->till->layaways->settle($order, $this->at));
        $completed = $this->till->pos->findLayaway('A-1');
        self::assertSame([LayawayState::Completed, $this->at], [$completed?->state(), $completed?->endedAt()]);

        $this->expectException(PosRefused::class);
        $this->expectExceptionMessageIsOrContains('The order A-1 is not an open layaway.');

        $this->till->layaways->settle($order, $this->at);
    }

    public function testTheDepositRoundsUp(): void
    {
        self::assertSame(375, $this->till->layaways->depositFor($this->placed(['TEA' => 3]))->minorUnits);
        self::assertSame(0, new Layaways($this->till->payments, $this->till->pos, 0)->depositFor($this->placed(['TEA' => 1], 'A-2'))->minorUnits);
        self::assertSame(499, new Layaways($this->till->payments, $this->till->pos, 1_000_000)->depositFor($this->placed(['TEA' => 1], 'A-3'))->minorUnits);
        self::assertSame(1, new Layaways($this->till->payments, $this->till->pos, 1)->depositFor($this->placed(['TEA' => 1], 'A-4'))->minorUnits);
        self::assertSame(1, new Layaways($this->till->payments, $this->till->pos, 1)->depositFor($this->placed(['PIN' => 1], 'A-5'))->minorUnits);
        self::assertSame(1, new Layaways($this->till->payments, $this->till->pos, 1_000_000)->depositFor($this->placed(['PIN' => 1], 'A-6'))->minorUnits);
    }

    public function testAShortDepositIsRefused(): void
    {
        $order = $this->placed(['MUG' => 3]);
        $this->till->registerPayments->recorded($order, $this->session, 'debit', $this->cad(899), null, $this->at);

        $this->expectException(PosRefused::class);
        $this->expectExceptionMessageIsOrContains('The layaway A-1 needs a deposit of 9.00; 8.99 is paid.');

        $this->till->layaways->open($order, 'ada', $this->at->modify('+30 days'), $this->at);
    }

    public function testTheDepositIsBounded(): void
    {
        foreach ([-1, 1_000_001] as $deposit) {
            try {
                new Layaways($this->till->payments, $this->till->pos, $deposit);
                self::fail('A bad deposit was accepted.');
            } catch (InvalidValue $invalid) {
                self::assertSame(\sprintf('A deposit is 0 to 1000000 parts per million; %d is refused.', $deposit), $invalid->getMessage());
            }
        }
    }

    public function testALayawayIsCancelledWithItsOrder(): void
    {
        $order = $this->placed(['MUG' => 1]);
        $this->till->registerPayments->recorded($order, $this->session, 'debit', $this->cad(300), null, $this->at);
        $layaway = $this->till->layaways->open($order, 'ada', $this->at->modify('+1 day'), $this->at);

        self::assertSame([$layaway], $this->till->layaways->due($this->at->modify('+1 day')));
        self::assertSame([], $this->till->layaways->due($this->at));

        $this->till->layaways->cancel($order, $this->at);

        self::assertSame([LayawayState::Cancelled, OrderState::Cancelled], [$layaway->state(), $order->state()]);
        self::assertSame([], $this->till->layaways->due($this->at->modify('+1 year')));
    }

    public function testOnlyAPlacedOrderIsPutAsideAndOnlyOnce(): void
    {
        $order = $this->placed(['MUG' => 1]);
        $this->till->registerPayments->recorded($order, $this->session, 'debit', $this->cad(300), null, $this->at);
        $this->till->layaways->open($order, 'ada', $this->at->modify('+1 day'), $this->at);

        $cart = $this->till->cart(['MUG' => 1], 'A-2');

        foreach ([[$order, 'The order A-1 is already put aside.'], [$cart, 'The order A-2 is not placed: only a placed order is put aside.']] as [$candidate, $message]) {
            try {
                $this->till->layaways->open($candidate, 'ada', $this->at->modify('+1 day'), $this->at);
                self::fail($message);
            } catch (PosRefused $refused) {
                self::assertSame($message, $refused->getMessage());
            }
        }

        self::assertCount(1, $this->till->pos->layaways);
    }

    public function testAnOrderWithoutLayawayIsSaidSo(): void
    {
        $this->expectException(PosRefused::class);
        $this->expectExceptionMessageIsOrContains('The order A-1 is not an open layaway.');

        $this->till->layaways->cancel($this->placed(['TEA' => 1]), $this->at);
    }

    public function testAnOfflineSaleIsImportedOnce(): void
    {
        $sale = OfflineSale::fromArray($this->sale());

        [$imported, $now] = $this->till->offline->import($sale, $this->at);

        self::assertTrue($now);
        self::assertSame(['sale-1', $this->session->id, 0, true, '2026-11-03 09:30:00', $this->at], [$imported->id, $imported->session, $imported->discrepancy->minorUnits, $imported->agrees(), $imported->rungAt->format('Y-m-d H:i:s'), $imported->importedAt]);
        $order = $this->till->orders->find('sale-1');
        self::assertSame([OrderState::Placed, 1_198, 200], [$order?->state(), $order?->total()->minorUnits, $order?->adjustmentsTotal(AdjustmentType::Tip)->minorUnits]);
        self::assertSame([['cash', 700, null], ['debit', 498, 'A1B2']], array_map(static fn (SessionPayment $payment): array => [$payment->method, $payment->amount->minorUnits, $payment->approval], array_values($this->till->pos->payments)));
        self::assertSame('cashier-7', $this->till->pos->sales[0]->by);
        self::assertSame(700, $this->till->pos->movements[1]->amount->minorUnits);
        self::assertInstanceOf(Order::class, $order);
        self::assertTrue($this->till->payments->summary($order)->payable()->isZero());

        [$again, $nowAgain] = $this->till->offline->import($sale, $this->at->modify('+1 hour'));
        self::assertSame([$imported, false], [$again, $nowAgain]);
        self::assertCount(2, $this->till->paymentStore->payments);
    }

    public function testRepricingKeepsTheDifferenceAndTakesNoMoreThanTheOrder(): void
    {
        $sale = $this->sale(['total' => 1_400, 'payments' => [['method' => 'credit', 'amount' => 0], ['method' => 'cash', 'amount' => 1_000], ['method' => 'debit', 'amount' => 400]]]);

        [$imported] = $this->till->offline->import(OfflineSale::fromArray($sale), $this->at);

        self::assertSame(-202, $imported->discrepancy->minorUnits);
        self::assertSame([['cash', 1_000], ['debit', 198]], array_map(static fn (SessionPayment $payment): array => [$payment->method, $payment->amount->minorUnits], array_values($this->till->pos->payments)));
    }

    public function testCashCappedByRepricingIsTakenRounded(): void
    {
        $sale = $this->sale(['total' => 1_198, 'payments' => [['method' => 'cash', 'amount' => 2_000]]]);

        $this->till->offline->import(OfflineSale::fromArray($sale), $this->at);

        $movement = $this->till->pos->movements[1];
        self::assertSame([1_200, 2], [$movement->amount->minorUnits, $movement->rounding->minorUnits]);
    }

    public function testAnOfflineSaleSellsAtTheRegistersChannel(): void
    {
        $this->till->registers->get('front')->place(null, 'shop');

        $this->till->offline->import(OfflineSale::fromArray($this->sale()), $this->at);

        self::assertSame('shop', $this->till->orders->find('sale-1')?->channel());
    }

    public function testOnlyTheMainRegisterSellsOffline(): void
    {
        $this->till->registers->assign('front', RegisterRole::Mobile);

        try {
            $this->till->offline->import(OfflineSale::fromArray($this->sale()), $this->at);
            self::fail('A mobile register imported an offline sale.');
        } catch (PosRefused $posRefused) {
            self::assertSame('Only a main register sells offline; front is mobile.', $posRefused->getMessage());
        }

        self::assertSame([], $this->till->orders->orders);
        self::assertSame([], $this->till->pos->imported);
    }

    public function testAnOfflineSaleNeverPicksUpASaleStartedElsewhere(): void
    {
        $this->till->cart(['MUG' => 1], 'sale-1');

        try {
            $this->till->offline->import(OfflineSale::fromArray($this->sale()), $this->at);
            self::fail('An offline sale took over an order.');
        } catch (PosRefused $posRefused) {
            self::assertSame('The order sale-1 exists already: an offline sale never picks up a sale started elsewhere.', $posRefused->getMessage());
        }

        self::assertSame(1_200, $this->till->orders->find('sale-1')?->total()->minorUnits);
        self::assertSame([], $this->till->pos->sales);
    }

    public function testAnOfflineSaleNeedsItsSession(): void
    {
        $this->expectException(PosRefused::class);
        $this->expectExceptionMessageIsOrContains('The session nope is closed.');

        $this->till->offline->import(OfflineSale::fromArray($this->sale(['session' => 'nope'])), $this->at);
    }

    public function testAnOfflineSaleOfSomethingNoLongerSoldIsRefused(): void
    {
        $this->expectException(UnknownSellable::class);

        $this->till->offline->import(OfflineSale::fromArray($this->sale(['lines' => [['type' => 'product', 'id' => 'GONE', 'quantity' => 1]]])), $this->at);
    }

    public function testAnOfflineSalePaidByAMethodTheRegisterDoesNotTakeIsRefusedBeforeAnything(): void
    {
        try {
            $this->till->offline->import(OfflineSale::fromArray($this->sale(['payments' => [['method' => 'cash', 'amount' => 700], ['method' => 'gift_card', 'amount' => 498]]])), $this->at);
            self::fail('A gift card was taken offline.');
        } catch (InvalidValue $invalidValue) {
            self::assertSame('An offline sale is paid in cash or a method the register records; "gift_card" is neither.', $invalidValue->getMessage());
        }

        self::assertNull($this->till->orders->find('sale-1'));
        self::assertSame([[], []], [$this->till->pos->sales, $this->till->pos->payments]);
    }

    public function testTheGateDecidesWhoImportsASale(): void
    {
        $gate = new class implements OfflineSaleGate {
            /** @var list<array{string, string}> */
            public array $seen = [];

            #[Override]
            public function allows(OfflineSale $sale, CashSession $session): bool
            {
                $this->seen[] = [$sale->by, $session->id];

                return 'cashier-7' === $sale->by;
            }
        };
        $till = new Till([], $gate);
        $session = $till->sessions->open('front', 'ada', $this->cad(0), $this->at);

        [, $now] = $till->offline->import(OfflineSale::fromArray(['session' => $session->id] + $this->sale()), $this->at);
        self::assertTrue($now);

        try {
            $till->offline->import(OfflineSale::fromArray(['session' => $session->id, 'by' => 'mallory'] + $this->sale()), $this->at);
            self::fail('The gate was not asked.');
        } catch (OfflineSaleForbidden $offlineSaleForbidden) {
            self::assertSame(\sprintf('The offline sale sale-1 may not be imported into the session %s by whoever sent it.', $session->id), $offlineSaleForbidden->getMessage());
        }

        self::assertSame([['cashier-7', $session->id], ['mallory', $session->id]], $gate->seen);
    }

    public function testAnOfflineSaleIsRead(): void
    {
        $sale = OfflineSale::fromArray($this->sale(['tip' => 0, 'payments' => []]));

        self::assertSame(['sale-1', $this->session->id, 'cashier-7', '2026-11-03T09:30:00-05:00', 1_198, 0, [['type' => 'product', 'id' => 'TEA', 'quantity' => 2]], []], [
            $sale->id, $sale->session, $sale->by, $sale->rungAt->format(\DATE_ATOM), $sale->total, $sale->tip, $sale->lines, $sale->payments,
        ]);

        $data = $this->sale();
        unset($data['tip'], $data['payments']);
        self::assertSame([0, []], [OfflineSale::fromArray($data)->tip, OfflineSale::fromArray($data)->payments]);
        self::assertSame(str_repeat('s', 64), OfflineSale::fromArray($this->sale(['id' => str_repeat('s', 64)]))->id);
        $bounds = OfflineSale::fromArray($this->sale(['total' => 1_000_000_000_000, 'tip' => 1_000_000_000_000, 'lines' => [['type' => 'product', 'id' => 'TEA', 'quantity' => 100_000]], 'payments' => [['method' => 'cash', 'amount' => 1_000_000_000_000]]]));
        self::assertSame([1_000_000_000_000, 1_000_000_000_000, 100_000, 1_000_000_000_000], [$bounds->total, $bounds->tip, $bounds->lines[0]['quantity'], $bounds->payments[0]['amount']]);
        self::assertSame(0, OfflineSale::fromArray($this->sale(['total' => 0]))->total);
    }

    /**
     * What JavaScript's `toISOString()` sends is read, to the microsecond; an offset is kept.
     */
    public function testAnOfflineSalesMomentIsRfc3339(): void
    {
        foreach ([
            '2026-11-03T14:30:00.000Z' => '2026-11-03T14:30:00.000000+00:00',
            '2026-11-03T14:30:00Z' => '2026-11-03T14:30:00.000000+00:00',
            '2026-11-03T09:30:00.5-05:00' => '2026-11-03T09:30:00.500000-05:00',
            '2028-02-29T23:59:59.123456+01:00' => '2028-02-29T23:59:59.123456+01:00',
        ] as $sent => $read) {
            self::assertSame($read, OfflineSale::fromArray($this->sale(['rung_at' => $sent]))->rungAt->format('Y-m-d\\TH:i:s.uP'), $sent);
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function unreadableSales(): iterable
    {
        yield 'no id' => [['id' => ''], 'An offline sale\'s "id" is a text.'];
        yield 'a long id' => [['id' => str_repeat('s', 65)], "An offline sale's id is 1 to 64 visible ASCII characters."];
        yield 'an id and a newline' => [['id' => "sale-1\n"], "An offline sale's id is 1 to 64 visible ASCII characters."];
        yield 'a spaced id' => [['id' => 'sale 1'], "An offline sale's id is 1 to 64 visible ASCII characters."];
        yield 'a numeric id' => [['id' => 7], 'An offline sale\'s "id" is a text.'];
        yield 'a vague moment' => [['rung_at' => '2026-11-03'], 'An offline sale\'s "rung_at" is an RFC 3339 moment: 2026-11-03T14:05:00-05:00, or 2026-11-03T19:05:00.000Z.'];
        yield 'a moment without its offset' => [['rung_at' => '2026-11-03T09:30:00'], 'An offline sale\'s "rung_at" is an RFC 3339 moment: 2026-11-03T14:05:00-05:00, or 2026-11-03T19:05:00.000Z.'];
        yield 'a moment without its seconds' => [['rung_at' => '2026-11-03T09:30-05:00'], 'An offline sale\'s "rung_at" is an RFC 3339 moment: 2026-11-03T14:05:00-05:00, or 2026-11-03T19:05:00.000Z.'];
        yield 'the 30th of February' => [['rung_at' => '2026-02-30T09:30:00Z'], 'An offline sale\'s "rung_at" is an RFC 3339 moment: 2026-11-03T14:05:00-05:00, or 2026-11-03T19:05:00.000Z.'];
        yield 'an hour of 24' => [['rung_at' => '2026-11-03T24:00:00Z'], 'An offline sale\'s "rung_at" is an RFC 3339 moment: 2026-11-03T14:05:00-05:00, or 2026-11-03T19:05:00.000Z.'];
        yield 'a 61st minute' => [['rung_at' => '2026-11-03T09:61:00Z'], 'An offline sale\'s "rung_at" is an RFC 3339 moment: 2026-11-03T14:05:00-05:00, or 2026-11-03T19:05:00.000Z.'];
        yield 'a moment and a newline' => [['rung_at' => "2026-11-03T09:30:00Z\n"], 'An offline sale\'s "rung_at" is an RFC 3339 moment: 2026-11-03T14:05:00-05:00, or 2026-11-03T19:05:00.000Z.'];
        yield 'a negative tip' => [['tip' => -1], 'An offline sale\'s "tip" is 0 to 1000000000000.'];
        yield 'a decimal tip' => [['tip' => '2.00'], 'An offline sale\'s "tip" is 0 to 1000000000000.'];
        yield 'a tip out of bounds' => [['tip' => 1_000_000_000_001], 'An offline sale\'s "tip" is 0 to 1000000000000.'];
        yield 'a negative total' => [['total' => -1], 'An offline sale\'s "total" is 0 to 1000000000000.'];
        yield 'a total out of bounds' => [['total' => 1_000_000_000_001], 'An offline sale\'s "total" is 0 to 1000000000000.'];
        yield 'no lines' => [['lines' => []], 'An offline sale has lines.'];
        yield 'lines as an object' => [['lines' => ['a' => []]], 'An offline sale\'s "lines" is a list.'];
        yield 'a line as a text' => [['lines' => ['TEA']], 'An offline sale\'s "lines" holds objects.'];
        yield 'no unit' => [['lines' => [['type' => 'product', 'id' => 'TEA', 'quantity' => 0]]], "An offline sale's line holds 1 to 100000 units."];
        yield 'too many units' => [['lines' => [['type' => 'product', 'id' => 'TEA', 'quantity' => 100_001]]], "An offline sale's line holds 1 to 100000 units."];
        yield 'a decimal quantity' => [['lines' => [['type' => 'product', 'id' => 'TEA', 'quantity' => 1.5]]], 'An offline sale\'s "quantity" is a whole number.'];
        yield 'no total' => [['total' => null], 'An offline sale\'s "total" is a whole number.'];
        yield 'payments as a text' => [['payments' => 'cash'], 'An offline sale\'s "payments" is a list.'];
        yield 'a negative payment' => [['payments' => [['method' => 'cash', 'amount' => -1]]], 'An offline sale\'s payment has an amount of 0 to 1000000000000, and a text "approval".'];
        yield 'a payment out of bounds' => [['payments' => [['method' => 'cash', 'amount' => 1_000_000_000_001]]], 'An offline sale\'s payment has an amount of 0 to 1000000000000, and a text "approval".'];
        yield 'a numeric approval' => [['payments' => [['method' => 'debit', 'amount' => 1, 'approval' => 7]]], 'An offline sale\'s payment has an amount of 0 to 1000000000000, and a text "approval".'];
        yield 'no method' => [['payments' => [['amount' => 1]]], 'An offline sale\'s "method" is a text.'];
    }

    /**
     * @param array<string, mixed> $change
     */
    #[DataProvider('unreadableSales')]
    public function testAnUnreadableSaleIsRefused(array $change, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        OfflineSale::fromArray($this->sale($change));
    }

    /**
     * @param array<string, mixed> $change
     *
     * @return array<string, mixed>
     */
    private function sale(array $change = []): array
    {
        return $change + [
            'id' => 'sale-1',
            'session' => $this->session->id,
            'by' => 'cashier-7',
            'rung_at' => '2026-11-03T09:30:00-05:00',
            'total' => 1_198,
            'tip' => 200,
            'lines' => [['type' => 'product', 'id' => 'TEA', 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 700], ['method' => 'debit', 'amount' => 600, 'approval' => 'A1B2']],
        ];
    }

    /**
     * @param array<string, int> $lines
     */
    private function placed(array $lines, string $number = 'A-1'): Order
    {
        $order = $this->till->cart($lines, $number);
        $this->till->checkout->ring($order, $this->session, 'ada', $this->at);

        return $order;
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }
}
