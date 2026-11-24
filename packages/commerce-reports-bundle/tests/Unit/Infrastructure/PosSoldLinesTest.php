<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Tests\Unit\Infrastructure;

use Closure;
use DateTimeImmutable;
use LogicException;
use Override;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommercePosBundle\Domain\Model\CashierPin;
use Phil\CommercePosBundle\Domain\Model\CashMovement;
use Phil\CommercePosBundle\Domain\Model\CashSession;
use Phil\CommercePosBundle\Domain\Model\HeldSale;
use Phil\CommercePosBundle\Domain\Model\ImportedSale;
use Phil\CommercePosBundle\Domain\Model\Layaway;
use Phil\CommercePosBundle\Domain\Model\Register;
use Phil\CommercePosBundle\Domain\Model\RegisterSale;
use Phil\CommercePosBundle\Domain\Model\SalePreset;
use Phil\CommercePosBundle\Domain\Model\SessionPayment;
use Phil\CommercePosBundle\Domain\Model\Terminal;
use Phil\CommercePosBundle\Domain\Model\TerminalPayment;
use Phil\CommercePosBundle\Domain\Repository\CashSessionRepository;
use Phil\CommercePosBundle\Domain\Repository\RegisterRepository;
use Phil\CommercePosBundle\Domain\Repository\RegisterSaleRepository;
use Phil\CommerceReportsBundle\Domain\ValueObject\SoldLine;
use Phil\CommerceReportsBundle\Domain\ValueObject\StockedSku;
use Phil\CommerceReportsBundle\Infrastructure\Memory\InMemoryStockedSkus;
use Phil\CommerceReportsBundle\Infrastructure\Pos\PosSoldLines;
use Phil\CommerceReportsBundle\Tests\Fixture\Lines;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PosSoldLines::class)]
final class PosSoldLinesTest extends TestCase
{
    /** @var list<array{DateTimeImmutable, DateTimeImmutable}> */
    private array $asked = [];

    public function testTheLinesOfTheSalesRungInThePeriod(): void
    {
        $lines = iterator_to_array($this->adapter(new InMemoryStockedSkus([new StockedSku('BOOK', 'Book', 'books')]))->between(Lines::period()), false);

        self::assertEquals([[Lines::period()->start, Lines::period()->end]], $this->asked);
        self::assertSame(
            [
                ['BOOK', 'Book', 'books', 'store', 'main', 'ann', 2, '21.00', '4.78', '2026-11-03'],
                ['GAME', str_repeat('é', 200), null, 'store', 'main', 'ann', 1, '5.00', '0.42', '2026-11-03'],
                ['BOOK', 'Book', 'books', 'web', 'main', 'ann', 1, '10.00', '0.00', '2026-11-03'],
                ['GIFT', 'A gift with a long name', null, 'web', null, 'bob', 1, '0.00', '0.00', '2026-11-04'],
                ['GAME', 'Game', null, null, null, 'bob', 1, '5.00', '0.00', '2026-11-04'],
            ],
            array_map($this->row(...), $lines),
        );
    }

    public function testWithoutStockedSkusTheLinesHaveNoCategory(): void
    {
        $lines = iterator_to_array($this->adapter(null)->between(Lines::period()), false);

        self::assertSame([null, null, null, null, null], array_map(static fn (SoldLine $line): ?string => $line->category, $lines));
    }

    /**
     * @return list<bool|int|string|null>
     */
    private function row(SoldLine $line): array
    {
        return [$line->sku, $line->product, $line->category, $line->channel, $line->location, $line->cashier, $line->quantity, $line->gross->toDecimal(), $line->discounts->toDecimal(), $line->soldAt->format('Y-m-d')];
    }

    private function adapter(?InMemoryStockedSkus $skus): PosSoldLines
    {
        $cad = Currency::of('CAD');
        $main = Register::of('r1', 'Front', $cad, 1, 'main', 'store');
        $gone = Register::of('gone', 'Retired', $cad);
        [$first] = CashSession::open($main, 'ann', Money::zero($cad), new DateTimeImmutable('2026-11-02T08:00:00Z'));
        [$second] = CashSession::open($gone, 'bob', Money::zero($cad), new DateTimeImmutable('2026-11-04T08:00:00Z'));

        $sales = [
            $first->id => [
                // A sale left out is followed by sales that count: the loop goes on to them.
                RegisterSale::of('O2', $first, 'ann', new DateTimeImmutable('2026-10-31T23:00:00Z')),
                RegisterSale::of('O4', $first, 'ann', new DateTimeImmutable('2026-11-03T12:00:00Z')),
                RegisterSale::of('O3', $first, 'ann', new DateTimeImmutable('2026-11-03T11:00:00Z')),
                RegisterSale::of('O1', $first, 'ann', new DateTimeImmutable('2026-11-03T10:00:00Z')),
                RegisterSale::of('O7', $first, 'ann', new DateTimeImmutable('2026-11-03T13:00:00Z')),
            ],
            $second->id => [
                RegisterSale::of('O5', $second, 'bob', new DateTimeImmutable('2026-11-04T10:00:00Z')),
                RegisterSale::of('O6', $second, 'bob', new DateTimeImmutable('2026-11-04T11:00:00Z')),
                RegisterSale::of('O9', $second, 'bob', new DateTimeImmutable('2026-11-04T12:00:00Z')),
            ],
        ];
        $first->close(Money::zero($cad), 'ann', new DateTimeImmutable('2026-11-03T20:00:00Z'));
        $second->close(Money::zero($cad), 'bob', new DateTimeImmutable('2026-11-04T20:00:00Z'));

        return new PosSoldLines(
            $this->sessions([$first, $second]),
            $this->sales($sales),
            $this->registers([$main]),
            $this->orders($this->ordersOfTheDay($cad)),
            $skus,
        );
    }

    /**
     * @return array<string, Order>
     */
    private function ordersOfTheDay(Currency $cad): array
    {
        $book = SellableReference::of('product', 'BOOK');
        $game = SellableReference::of('product', 'GAME');
        $gift = SellableReference::of('product', 'GIFT');

        $first = Order::open('O1', $cad);
        $first->add(Sellable::of($book, 'Book', Money::of(1000, $cad), 'standard'), 2, Money::of(1000, $cad));
        $first->add(Sellable::of($game, ' '.str_repeat('é', 250), Money::of(500, $cad), 'standard'), 1, Money::of(500, $cad));
        $first->replaceAdjustments(AdjustmentType::Option, [
            Adjustment::of(AdjustmentType::Option, 'Signed', Money::of(200, $cad), $book),
            Adjustment::of(AdjustmentType::Option, 'Shown only', Money::of(999, $cad), $book, true),
        ]);
        $first->replaceAdjustments(AdjustmentType::Promotion, [
            Adjustment::of(AdjustmentType::Promotion, 'Book week', Money::of(-300, $cad), $book),
            Adjustment::of(AdjustmentType::Promotion, 'Cart', Money::of(-120, $cad)),
            Adjustment::of(AdjustmentType::Promotion, 'Shown only', Money::of(-999, $cad), null, true),
            Adjustment::of(AdjustmentType::Promotion, 'Member', Money::of(-100, $cad)),
        ]);
        $first->replaceAdjustments(AdjustmentType::Tax, [
            Adjustment::of(AdjustmentType::Tax, 'Included', Money::of(100, $cad), $book, true),
            Adjustment::of(AdjustmentType::Tax, 'Added', Money::of(130, $cad), $game),
        ]);
        $first->replaceAdjustments(AdjustmentType::Tip, [Adjustment::of(AdjustmentType::Tip, 'Tip', Money::of(300, $cad))]);

        $before = Order::open('O2', $cad);
        $before->add(Sellable::of($book, 'Book', Money::of(1000, $cad), 'standard'), 1, Money::of(1000, $cad));

        $cancelled = Order::open('O4', $cad);
        $cancelled->add(Sellable::of($book, 'Book', Money::of(1000, $cad), 'standard'), 1, Money::of(1000, $cad));
        $cancelled->cancel(new DateTimeImmutable('2026-11-03T12:05:00Z'));

        $odd = Order::open('O5', $cad);
        $odd->add(Sellable::of($gift, '  A gift with a long name  ', Money::of(1000, $cad), 'standard'), 1, Money::of(1000, $cad));
        $odd->changeChannel('web');
        $odd->replaceAdjustments(AdjustmentType::Tax, [Adjustment::of(AdjustmentType::Tax, 'Included', Money::of(1500, $cad), $gift, true)]);
        $odd->replaceAdjustments(AdjustmentType::Promotion, [
            Adjustment::of(AdjustmentType::Promotion, 'Surcharge', Money::of(50, $cad), $gift),
            Adjustment::of(AdjustmentType::Promotion, 'Cart', Money::of(-100, $cad)),
        ]);

        $empty = Order::open('O6', $cad);

        $online = Order::open('O7', $cad);
        $online->add(Sellable::of($book, 'Book', Money::of(1000, $cad), 'standard'), 1, Money::of(1000, $cad));
        $online->changeChannel('web');

        $nowhere = Order::open('O9', $cad);
        $nowhere->add(Sellable::of($game, 'Game', Money::of(500, $cad), 'standard'), 1, Money::of(500, $cad));

        return ['O1' => $first, 'O2' => $before, 'O4' => $cancelled, 'O5' => $odd, 'O6' => $empty, 'O7' => $online, 'O9' => $nowhere];
    }

    /**
     * @param list<CashSession> $closed
     */
    private function sessions(array $closed): CashSessionRepository
    {
        $asked = function (DateTimeImmutable $from, DateTimeImmutable $to): void {
            $this->asked[] = [$from, $to];
        };

        return new readonly class($closed, $asked) implements CashSessionRepository {
            /**
             * @param list<CashSession>                                   $closed
             * @param Closure(DateTimeImmutable, DateTimeImmutable): void $asked
             */
            public function __construct(
                private array $closed,
                private Closure $asked,
            ) {
            }

            #[Override]
            public function findSession(string $id): ?CashSession
            {
                throw new LogicException('Not read.');
            }

            #[Override]
            public function openSessionOf(string $register): ?CashSession
            {
                throw new LogicException('Not read.');
            }

            #[Override]
            public function addSession(CashSession $session): void
            {
                throw new LogicException('Not written.');
            }

            #[Override]
            public function addCashMovement(CashMovement $movement): void
            {
                throw new LogicException('Not written.');
            }

            #[Override]
            public function cashMovementsOf(string $session): array
            {
                throw new LogicException('Not read.');
            }

            #[Override]
            public function cashMovementOf(string $payment): ?CashMovement
            {
                throw new LogicException('Not read.');
            }

            #[Override]
            public function closedBetween(DateTimeImmutable $from, DateTimeImmutable $to, ?string $register = null): array
            {
                ($this->asked)($from, $to);

                return $this->closed;
            }
        };
    }

    /**
     * @param array<string, list<RegisterSale>> $sales
     */
    private function sales(array $sales): RegisterSaleRepository
    {
        return new readonly class($sales) implements RegisterSaleRepository {
            /**
             * @param array<string, list<RegisterSale>> $sales
             */
            public function __construct(
                private array $sales,
            ) {
            }

            #[Override]
            public function addSale(RegisterSale $sale): void
            {
                throw new LogicException('Not written.');
            }

            #[Override]
            public function salesOf(string $session): array
            {
                return $this->sales[$session] ?? [];
            }

            #[Override]
            public function addPayment(SessionPayment $payment): void
            {
                throw new LogicException('Not written.');
            }

            #[Override]
            public function findPayment(string $payment): ?SessionPayment
            {
                throw new LogicException('Not read.');
            }

            #[Override]
            public function paymentsOf(string $session): array
            {
                throw new LogicException('Not read.');
            }

            #[Override]
            public function findHeld(string $orderNumber): ?HeldSale
            {
                throw new LogicException('Not read.');
            }

            #[Override]
            public function hold(HeldSale $sale): void
            {
                throw new LogicException('Not written.');
            }

            #[Override]
            public function release(HeldSale $sale): void
            {
                throw new LogicException('Not written.');
            }

            #[Override]
            public function heldAt(string $register): array
            {
                throw new LogicException('Not read.');
            }

            #[Override]
            public function findLayaway(string $orderNumber): ?Layaway
            {
                throw new LogicException('Not read.');
            }

            #[Override]
            public function addLayaway(Layaway $layaway): void
            {
                throw new LogicException('Not written.');
            }

            #[Override]
            public function layawaysDue(DateTimeImmutable $at): array
            {
                throw new LogicException('Not read.');
            }

            #[Override]
            public function findImported(string $id): ?ImportedSale
            {
                throw new LogicException('Not read.');
            }

            #[Override]
            public function addImported(ImportedSale $sale): void
            {
                throw new LogicException('Not written.');
            }

            #[Override]
            public function findTerminalPayment(string $payment): ?TerminalPayment
            {
                throw new LogicException('Not read.');
            }

            #[Override]
            public function addTerminalPayment(TerminalPayment $payment): void
            {
                throw new LogicException('Not written.');
            }
        };
    }

    /**
     * @param list<Register> $registers
     */
    private function registers(array $registers): RegisterRepository
    {
        return new readonly class($registers) implements RegisterRepository {
            /**
             * @param list<Register> $registers
             */
            public function __construct(
                private array $registers,
            ) {
            }

            #[Override]
            public function findRegister(string $code): ?Register
            {
                foreach ($this->registers as $register) {
                    if ($register->code === $code) {
                        return $register;
                    }
                }

                return null;
            }

            #[Override]
            public function addRegister(Register $register): void
            {
                throw new LogicException('Not written.');
            }

            #[Override]
            public function mainRegisterAt(?string $location): ?Register
            {
                throw new LogicException('Not read.');
            }

            #[Override]
            public function findTerminal(string $code): ?Terminal
            {
                throw new LogicException('Not read.');
            }

            #[Override]
            public function addTerminal(Terminal $terminal): void
            {
                throw new LogicException('Not written.');
            }

            #[Override]
            public function terminalsAt(string $register): array
            {
                throw new LogicException('Not read.');
            }

            #[Override]
            public function findPreset(string $code): ?SalePreset
            {
                throw new LogicException('Not read.');
            }

            #[Override]
            public function addPreset(SalePreset $preset): void
            {
                throw new LogicException('Not written.');
            }

            #[Override]
            public function activePresets(): array
            {
                throw new LogicException('Not read.');
            }

            #[Override]
            public function findCashierPin(string $user): ?CashierPin
            {
                throw new LogicException('Not read.');
            }

            #[Override]
            public function addCashierPin(CashierPin $pin): void
            {
                throw new LogicException('Not written.');
            }

            #[Override]
            public function removeCashierPin(CashierPin $pin): void
            {
                throw new LogicException('Not written.');
            }
        };
    }

    /**
     * @param array<string, Order> $orders
     */
    private function orders(array $orders): OrderRepository
    {
        return new readonly class($orders) implements OrderRepository {
            /**
             * @param array<string, Order> $orders
             */
            public function __construct(
                private array $orders,
            ) {
            }

            #[Override]
            public function find(string $number): ?Order
            {
                return $this->orders[$number] ?? null;
            }

            #[Override]
            public function add(Order $order): void
            {
                throw new LogicException('Not written.');
            }
        };
    }
}
