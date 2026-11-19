<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\CommercePosBundle\Domain\Exception\PosRefused;
use Phil\CommercePosBundle\Domain\Model\CashMovement;
use Phil\CommercePosBundle\Domain\Model\CashSession;
use Phil\CommercePosBundle\Domain\Model\Register;
use Phil\CommercePosBundle\Domain\Session\CashMovementKind;
use Phil\CommercePosBundle\Domain\Session\SessionState;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CashSession::class)]
#[CoversClass(CashMovement::class)]
#[CoversClass(CashMovementKind::class)]
final class CashSessionTest extends TestCase
{
    private const string UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';

    public function testASessionOpensOnItsFloat(): void
    {
        $at = new DateTimeImmutable('2026-11-03 08:00:00');
        [$session, $float] = CashSession::open(self::register(), 'ada', self::cad(10_000), $at);

        self::assertMatchesRegularExpression(self::UUID, $session->id);
        self::assertFalse($session->taxIncluded);
        self::assertTrue(CashSession::open(self::register(), 'ada', self::cad(0), $at, true)[0]->taxIncluded);
        self::assertSame(['front', 'CAD', 'ada', $at, SessionState::Open, true, 10_000, null, null, null, null, 1], [
            $session->register, $session->currency()->code, $session->openedBy, $session->openedAt, $session->state(), $session->isOpen(), $session->expected->minorUnits, $session->counted, $session->closedBy(), $session->closedAt(), $session->overShort(), $session->version(),
        ]);
        self::assertMatchesRegularExpression(self::UUID, $float->id);
        self::assertSame([$session->id, CashMovementKind::Float, 10_000, 0, 'CAD', 'ada', $at, null, null], [
            $float->session, $float->kind, $float->amount->minorUnits, $float->rounding->minorUnits, $float->amount->currency->code, $float->by, $float->occurredAt, $float->payment, $float->note,
        ]);
    }

    public function testAnEmptyDrawerMayOpen(): void
    {
        [$session] = CashSession::open(self::register(), 'ada', self::cad(0), new DateTimeImmutable());

        self::assertSame(0, $session->expected->minorUnits);
    }

    public function testAFloatIsZeroOrMore(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A float is zero or more.');

        CashSession::open(self::register(), 'ada', self::cad(-1), new DateTimeImmutable());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badPeople(): iterable
    {
        yield 'blank' => [' '];
        yield 'too long' => [str_repeat('é', 256)];
    }

    #[DataProvider('badPeople')]
    public function testSomeoneIsAtTheRegister(string $by): void
    {
        $message = 'A person at the register is named in 1 to 255 characters.';
        [$session] = CashSession::open(self::register(), 'ada', self::cad(0), new DateTimeImmutable());

        foreach ([static fn (): array => CashSession::open(self::register(), $by, self::cad(0), new DateTimeImmutable()), static fn () => $session->move(CashMovementKind::PaidIn, self::cad(1), self::cad(0), $by, new DateTimeImmutable()), static fn () => $session->close(self::cad(0), $by, new DateTimeImmutable())] as $operation) {
            try {
                $operation();
                self::fail('Nobody was accepted.');
            } catch (InvalidValue $invalid) {
                self::assertSame($message, $invalid->getMessage());
            }
        }

        self::assertTrue($session->isOpen());
        self::assertSame(str_repeat('é', 255), CashSession::open(self::register(), str_repeat('é', 255), self::cad(0), new DateTimeImmutable())[0]->openedBy);
    }

    public function testEachMovementChangesWhatTheDrawerShouldHold(): void
    {
        $at = new DateTimeImmutable('2026-11-03 09:00:00');
        [$session] = CashSession::open(self::register(), 'ada', self::cad(10_000), $at);

        $sale = $session->move(CashMovementKind::Sale, self::cad(2_415), self::cad(-2), 'ada', $at, 'p-1', str_repeat('é', 300));
        $refund = $session->move(CashMovementKind::Refund, self::cad(500), self::cad(1), 'bob', $at, 'p-1');
        $session->move(CashMovementKind::PaidIn, self::cad(2_000), self::cad(0), 'ada', $at);
        $session->move(CashMovementKind::PaidOut, self::cad(1_000), self::cad(0), 'ada', $at);
        $session->move(CashMovementKind::Drop, self::cad(5_000), self::cad(0), 'ada', $at);

        self::assertSame([2_415, -2, 'p-1', str_repeat('é', 255)], [$sale->amount->minorUnits, $sale->rounding->minorUnits, $sale->payment, $sale->note]);
        self::assertSame([-500, 1, 'bob'], [$refund->amount->minorUnits, $refund->rounding->minorUnits, $refund->by]);
        self::assertSame(10_000 + 2_415 - 500 + 2_000 - 1_000 - 5_000, $session->expected->minorUnits);
        self::assertNotSame($sale->id, $refund->id);
    }

    public function testWhichMovementsPutCashIn(): void
    {
        self::assertSame([true, true, false, true, false, false], array_map(static fn (CashMovementKind $kind): bool => $kind->addsCash(), CashMovementKind::cases()));
    }

    /**
     * @return iterable<string, array{Money, Money}>
     */
    public static function badMovements(): iterable
    {
        yield 'negative' => [self::cad(-1), self::cad(0)];
        yield 'another currency' => [Money::of(1, Currency::of('USD')), self::cad(0)];
        yield 'a rounding in another currency' => [self::cad(1), Money::of(0, Currency::of('USD'))];
    }

    #[DataProvider('badMovements')]
    public function testAMovementIsZeroOrMoreInTheDrawersCurrency(Money $amount, Money $rounding): void
    {
        [$session] = CashSession::open(self::register(), 'ada', self::cad(0), new DateTimeImmutable());

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A cash movement of the session %s is zero or more, in CAD.', $session->id));

        $session->move(CashMovementKind::PaidIn, $amount, $rounding, 'ada', new DateTimeImmutable());
    }

    public function testTheCloseTellsWhatWasOverOrShort(): void
    {
        $at = new DateTimeImmutable('2026-11-03 17:00:00');
        [$short] = CashSession::open(self::register(), 'ada', self::cad(10_000), $at);
        [$over] = CashSession::open(self::register(), 'ada', self::cad(10_000), $at);
        [$even] = CashSession::open(self::register(), 'ada', self::cad(10_000), $at);

        $short->close(self::cad(9_950), 'bob', $at);
        $over->close(self::cad(10_025), 'bob', $at);
        $even->close(self::cad(10_000), 'bob', $at);

        self::assertSame([SessionState::Closed, false, 9_950, 'bob', $at, -50], [$short->state(), $short->isOpen(), $short->counted?->minorUnits, $short->closedBy(), $short->closedAt(), $short->overShort()?->minorUnits]);
        self::assertSame(25, $over->overShort()?->minorUnits);
        self::assertSame(0, $even->overShort()?->minorUnits);
    }

    public function testAClosedSessionTakesNothingMore(): void
    {
        $at = new DateTimeImmutable();
        [$session] = CashSession::open(self::register(), 'ada', self::cad(0), $at);
        $session->close(self::cad(0), 'ada', $at);

        foreach ([static fn () => $session->move(CashMovementKind::PaidIn, self::cad(1), self::cad(0), 'ada', $at), static fn () => $session->close(self::cad(0), 'ada', $at)] as $operation) {
            try {
                $operation();
                self::fail('A closed session moved.');
            } catch (PosRefused $refused) {
                self::assertSame(\sprintf('The session %s is closed.', $session->id), $refused->getMessage());
            }
        }
    }

    /**
     * @return iterable<string, array{Money}>
     */
    public static function badCounts(): iterable
    {
        yield 'negative' => [self::cad(-1)];
        yield 'another currency' => [Money::of(0, Currency::of('USD'))];
    }

    #[DataProvider('badCounts')]
    public function testACountIsZeroOrMoreInTheDrawersCurrency(Money $counted): void
    {
        [$session] = CashSession::open(self::register(), 'ada', self::cad(0), new DateTimeImmutable());

        try {
            $session->close($counted, 'ada', new DateTimeImmutable());
            self::fail('A bad count was accepted.');
        } catch (InvalidValue $invalidValue) {
            self::assertSame(\sprintf('The cash counted in the session %s is zero or more, in CAD.', $session->id), $invalidValue->getMessage());
        }

        self::assertTrue($session->isOpen());
    }

    private static function register(): Register
    {
        return Register::of('front', 'Front', Currency::of('CAD'), 5);
    }

    private static function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }
}
