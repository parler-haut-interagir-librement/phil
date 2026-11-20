<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Phil\CommerceWholesaleBundle\Domain\Exception\WholesaleRefused;
use Phil\CommerceWholesaleBundle\Domain\Model\CreditAccount;
use Phil\CommerceWholesaleBundle\Domain\Model\Receivable;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreditAccount::class)]
#[CoversClass(Receivable::class)]
#[CoversClass(WholesaleRefused::class)]
final class CreditTest extends TestCase
{
    public function testAnAccountIsChargedWithinItsLimit(): void
    {
        $account = CreditAccount::open(str_repeat('é', 255), self::cad(10_000), 30);

        self::assertSame([str_repeat('é', 255), 'CAD', 10_000, 0, 30, true, 10_000, 1], [$account->buyer, $account->currency()->code, $account->limit->minorUnits, $account->owed->minorUnits, $account->termsDays(), $account->isActive(), $account->available()->minorUnits, $account->version()]);

        $account->charge(self::cad(4_000));
        $account->charge(self::cad(6_000));
        self::assertSame([10_000, 0], [$account->owed->minorUnits, $account->available()->minorUnits]);

        try {
            $account->charge(self::cad(1));
            self::fail('The limit was passed.');
        } catch (WholesaleRefused $wholesaleRefused) {
            self::assertSame(\sprintf('0.01 on account goes beyond the credit of %s: 0.00 is available.', str_repeat('é', 255)), $wholesaleRefused->getMessage());
        }

        $account->reduce(self::cad(2_500));
        self::assertSame(7_500, $account->owed->minorUnits);
        $account->reduce(self::cad(9_000));
        self::assertSame(0, $account->owed->minorUnits);
    }

    public function testALoweredLimitLeavesNothingAvailable(): void
    {
        $account = CreditAccount::open('ada', self::cad(10_000), 30);
        $account->charge(self::cad(8_000));

        $account->change(self::cad(5_000), 0);

        self::assertSame([5_000, 0, 0], [$account->limit->minorUnits, $account->termsDays(), $account->available()->minorUnits]);

        $account->change(self::cad(0), 365);
        self::assertSame([0, 365], [$account->limit->minorUnits, $account->termsDays()]);
    }

    public function testASuspendedAccountTakesNoPurchase(): void
    {
        $account = CreditAccount::open('ada', self::cad(10_000), 30);
        $account->suspend();
        self::assertFalse($account->isActive());

        try {
            $account->charge(self::cad(1));
            self::fail('A suspended account was charged.');
        } catch (WholesaleRefused $wholesaleRefused) {
            self::assertSame('ada has no open credit account.', $wholesaleRefused->getMessage());
        }

        $account->reduce(self::cad(1));
        $account->resume();
        $account->charge(self::cad(1));
        self::assertSame(1, $account->owed->minorUnits);
    }

    /**
     * @return iterable<string, array{callable(CreditAccount): void, string}>
     */
    public static function badChanges(): iterable
    {
        yield 'a negative limit' => [static fn (CreditAccount $account) => $account->change(self::cad(-1), 30), 'A credit limit is zero or more.'];
        yield 'negative terms' => [static fn (CreditAccount $account) => $account->change(self::cad(1), -1), 'Credit terms are 0 to 365 days; -1 is refused.'];
        yield 'long terms' => [static fn (CreditAccount $account) => $account->change(self::cad(1), 366), 'Credit terms are 0 to 365 days; 366 is refused.'];
        yield 'another currency' => [static fn (CreditAccount $account) => $account->change(Money::of(1, Currency::of('USD')), 30), 'The credit of ada is in CAD.'];
        yield 'a zero charge' => [static fn (CreditAccount $account) => $account->charge(self::cad(0)), 'An amount on the account of ada is positive, in CAD.'];
        yield 'a charge in dollars' => [static fn (CreditAccount $account) => $account->charge(Money::of(1, Currency::of('USD'))), 'An amount on the account of ada is positive, in CAD.'];
        yield 'a negative reduction' => [static fn (CreditAccount $account) => $account->reduce(self::cad(-1)), 'An amount on the account of ada is positive, in CAD.'];
    }

    /**
     * @param callable(CreditAccount): void $change
     */
    #[DataProvider('badChanges')]
    public function testAnAccountIsChecked(callable $change, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        $change(CreditAccount::open('ada', self::cad(10_000), 30));
    }

    public function testAnAccountIsOpenedForABuyer(): void
    {
        foreach ([' ', str_repeat('é', 256)] as $buyer) {
            try {
                CreditAccount::open($buyer, self::cad(1), 30);
                self::fail('A bad buyer was accepted.');
            } catch (InvalidValue $invalid) {
                self::assertSame('A credit account belongs to a buyer, named in 1 to 255 characters.', $invalid->getMessage());
            }
        }

        self::assertSame(0, CreditAccount::open('ada', self::cad(0), 0)->limit->minorUnits);
    }

    public function testAReceivableIsPaidAndCreditedUpToWhatIsOutstanding(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $receivable = Receivable::of(CreditAccount::open('ada', self::cad(10_000), 30), 'A-1', 'p-1', self::cad(5_000), $at);

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $receivable->id);
        self::assertSame(['ada', 'A-1', 'p-1', 5_000, 'CAD', $at, '2026-12-03 10:00:00', 0, 0, 5_000], [$receivable->buyer, $receivable->orderNumber, $receivable->payment, $receivable->amount->minorUnits, $receivable->amount->currency->code, $receivable->issuedAt, $receivable->dueAt->format('Y-m-d H:i:s'), $receivable->paid()->minorUnits, $receivable->credited()->minorUnits, $receivable->outstanding()->minorUnits]);
        self::assertFalse($receivable->isOverdue($receivable->dueAt));
        self::assertTrue($receivable->isOverdue($receivable->dueAt->modify('+1 second')));

        self::assertSame(1_000, $receivable->credit(self::cad(1_000))->minorUnits);
        self::assertSame(3_000, $receivable->pay(self::cad(3_000))->minorUnits);
        self::assertSame(1_000, $receivable->pay(self::cad(5_000))->minorUnits);
        self::assertSame([4_000, 1_000, 0], [$receivable->paid()->minorUnits, $receivable->credited()->minorUnits, $receivable->outstanding()->minorUnits]);
        self::assertSame(0, $receivable->credit(self::cad(1))->minorUnits);
        self::assertFalse($receivable->isOverdue($receivable->dueAt->modify('+1 year')));

        foreach ([self::cad(0), Money::of(1, Currency::of('USD'))] as $amount) {
            try {
                $receivable->pay($amount);
                self::fail('A bad amount was accepted.');
            } catch (InvalidValue $invalid) {
                self::assertSame(\sprintf('An amount on the receivable %s is positive, in CAD.', $receivable->id), $invalid->getMessage());
            }
        }
    }

    public function testARefundCreditsWhatIsOutstandingAndOwesBackTheRest(): void
    {
        $receivable = Receivable::of(CreditAccount::open('ada', self::cad(10_000), 30), 'A-1', 'p-1', self::cad(5_000), new DateTimeImmutable('2026-11-03 10:00:00'));
        $receivable->pay(self::cad(4_000));

        self::assertSame(1_000, $receivable->refund(self::cad(3_000))->minorUnits);
        self::assertSame([1_000, 0, 2_000], [$receivable->credited()->minorUnits, $receivable->outstanding()->minorUnits, $receivable->refundDue()->minorUnits]);
        self::assertSame(0, $receivable->refund(self::cad(500))->minorUnits);
        self::assertSame(2_500, $receivable->refundDue()->minorUnits);
        self::assertSame(2_500, $receivable->settleRefund(self::cad(9_000))->minorUnits);
        self::assertSame(0, $receivable->refundDue()->minorUnits);

        foreach ([static fn (): Money => $receivable->refund(self::cad(0)), static fn (): Money => $receivable->settleRefund(Money::of(1, Currency::of('USD')))] as $move) {
            try {
                $move();
                self::fail('A bad amount was accepted.');
            } catch (InvalidValue $invalid) {
                self::assertSame(\sprintf('An amount on the receivable %s is positive, in CAD.', $receivable->id), $invalid->getMessage());
            }
        }
    }

    public function testTheRefusalsSayWhy(): void
    {
        self::assertSame('No receivable is known as r-1.', WholesaleRefused::unknownReceivable('r-1')->getMessage());
    }

    public function testTheTables(): void
    {
        self::assertSame(['phil_commerce_wholesale_credit_account', ['buyer'], [
            'owedMinorUnits' => ['owed', 'bigint', null, false, null],
            'active' => ['active', 'boolean', null, false, null],
            'version' => ['version', 'integer', null, false, null],
            'buyer' => ['buyer', 'string', 255, false, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
            'limitMinorUnits' => ['credit_limit', 'bigint', null, false, null],
            'termsDays' => ['terms_days', 'smallint', null, false, null],
        ]], $this->table(CreditAccount::class));
        self::assertTrue($this->metadata(CreditAccount::class)->isVersioned);

        self::assertSame(['phil_commerce_wholesale_receivable', ['id'], [
            'paidMinorUnits' => ['paid', 'bigint', null, false, null],
            'creditedMinorUnits' => ['credited', 'bigint', null, false, null],
            'refundDueMinorUnits' => ['refund_due', 'bigint', null, false, null],
            'id' => ['id', 'string', 36, false, true],
            'buyer' => ['buyer', 'string', 255, false, null],
            'orderNumber' => ['order_number', 'string', 64, false, null],
            'payment' => ['payment', 'string', 36, false, true],
            'amountMinorUnits' => ['amount', 'bigint', null, false, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
            'issuedAt' => ['issued_at', 'datetime_immutable', null, false, null],
            'dueAt' => ['due_at', 'datetime_immutable', null, false, null],
        ]], $this->table(Receivable::class));
        self::assertSame([['columns' => ['buyer']], ['columns' => ['payment']]], array_values($this->metadata(Receivable::class)->table['indexes'] ?? []));

        $receivable = Receivable::of(CreditAccount::open(str_repeat('b', 255), self::cad(1), 0), str_repeat('o', 64), str_repeat('p', 36), self::cad(1), new DateTimeImmutable());
        foreach (['id' => $receivable->id, 'buyer' => $receivable->buyer, 'orderNumber' => $receivable->orderNumber, 'payment' => $receivable->payment] as $field => $value) {
            self::assertSame($this->metadata(Receivable::class)->fieldMappings[$field]->length, \strlen($value), $field);
        }

        self::assertSame($this->metadata(CreditAccount::class)->fieldMappings['buyer']->length, \strlen(CreditAccount::open(str_repeat('b', 255), self::cad(1), 0)->buyer));
    }

    /**
     * @param class-string $class
     *
     * @return array{string, array<int, string>, array<string, array{string, string, ?int, ?bool, mixed}>}
     */
    private function table(string $class): array
    {
        $metadata = $this->metadata($class);
        $columns = [];

        foreach ($metadata->fieldMappings as $field => $mapping) {
            $columns[$field] = [$mapping->columnName, $mapping->type, $mapping->length, $mapping->nullable, $mapping->options['fixed'] ?? null];
        }

        return [$metadata->getTableName(), $metadata->getIdentifierFieldNames(), $columns];
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return ClassMetadata<T>
     */
    private function metadata(string $class): ClassMetadata
    {
        $metadata = new ClassMetadata($class);
        $metadata->initializeReflection(new RuntimeReflectionService());
        new AttributeDriver([\dirname(__DIR__, 3).'/src/Domain/Model'])->loadMetadataForClass($class, $metadata);

        return $metadata;
    }

    private static function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }
}
