<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Unit\Domain;

use Closure;
use Phil\CommerceConsignmentBundle\Domain\Consignor\StatementFrequency;
use Phil\CommerceConsignmentBundle\Domain\Consignor\UnsoldPolicy;
use Phil\CommerceConsignmentBundle\Domain\Exception\InvalidValue;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignmentAgreement;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\StatementPeriod;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\Fixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConsignmentAgreement::class)]
#[CoversClass(StatementFrequency::class)]
#[CoversClass(StatementPeriod::class)]
final class AgreementTest extends TestCase
{
    public function testAVersionKeepsItsTermsInUtc(): void
    {
        $agreement = ConsignmentAgreement::version(Owner::consignor(Fixtures::AUTHOR), Fixtures::at(9), 90, UnsoldPolicy::ReturnToConsignor, StatementFrequency::Quarterly, Fixtures::at(8));

        self::assertSame(Fixtures::AUTHOR, $agreement->consignor);
        self::assertSame(90, $agreement->termDays);
        self::assertNull($agreement->extensionDays);
        self::assertSame(0, $agreement->extensions);
        self::assertSame(UnsoldPolicy::ReturnToConsignor, $agreement->unsoldPolicy);
        self::assertSame(StatementFrequency::Quarterly, $agreement->frequency);
        self::assertSame('2026-11-20 14:00:00 UTC', $agreement->effectiveFrom()->format('Y-m-d H:i:s T'));
        self::assertSame('2026-11-20 13:00:00 UTC', $agreement->recordedAt()->format('Y-m-d H:i:s T'));
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $agreement->id);
        self::assertSame('2027-02-18 14:00:00 UTC', $agreement->dueAt(Fixtures::at(9))?->format('Y-m-d H:i:s T'));
        self::assertSame(UnsoldPolicy::ReturnToConsignor, $agreement->actionWhenDue());
    }

    public function testExtensionsPushTheDueDateAndEndWithAReturn(): void
    {
        $agreement = ConsignmentAgreement::version(Owner::consignor(Fixtures::AUTHOR), Fixtures::at(9), 30, UnsoldPolicy::Extend, StatementFrequency::Monthly, Fixtures::at(8), 15, 2);

        self::assertSame(15, $agreement->extensionDays);
        self::assertSame(2, $agreement->extensions);
        self::assertSame('2027-01-19', $agreement->dueAt(Fixtures::utc('2026-11-20 00:00:00'))?->format('Y-m-d'));
        self::assertSame(UnsoldPolicy::ReturnToConsignor, $agreement->actionWhenDue());

        $once = ConsignmentAgreement::version(Owner::consignor(Fixtures::AUTHOR), Fixtures::at(9), 30, UnsoldPolicy::Extend, StatementFrequency::Monthly, Fixtures::at(8), 10);
        self::assertSame(1, $once->extensions);
        self::assertSame('2027-01-29', $once->dueAt(Fixtures::utc('2026-12-20 00:00:00'))?->format('Y-m-d'));

        $longest = ConsignmentAgreement::version(Owner::consignor(Fixtures::AUTHOR), Fixtures::at(9), 3638, UnsoldPolicy::Extend, StatementFrequency::Monthly, Fixtures::at(8), 1, 12);
        self::assertSame(12, $longest->extensions);
    }

    public function testABuyOutAndAnEndlessConsignment(): void
    {
        $buyOut = ConsignmentAgreement::version(Owner::consignor(Fixtures::AUTHOR), Fixtures::at(9), 1, UnsoldPolicy::BuyOut, StatementFrequency::Weekly, Fixtures::at(8));
        self::assertSame(UnsoldPolicy::BuyOut, $buyOut->actionWhenDue());
        self::assertSame('2026-11-21 14:00', $buyOut->dueAt(Fixtures::at(9))?->format('Y-m-d H:i'));

        $endless = ConsignmentAgreement::version(Owner::consignor(Fixtures::AUTHOR), Fixtures::at(9), null, UnsoldPolicy::ReturnToConsignor, StatementFrequency::Weekly, Fixtures::at(8));
        self::assertNull($endless->dueAt(Fixtures::at(9)));

        self::assertSame(3650, ConsignmentAgreement::version(Owner::consignor(Fixtures::AUTHOR), Fixtures::at(9), 3650, UnsoldPolicy::ReturnToConsignor, StatementFrequency::Weekly, Fixtures::at(8))->termDays);
    }

    /**
     * @return iterable<string, array{string, Closure(): ConsignmentAgreement}>
     */
    public static function refused(): iterable
    {
        $author = Owner::consignor(Fixtures::AUTHOR);
        $make = static fn (?int $term, UnsoldPolicy $policy, ?int $extension = null, int $extensions = 1, ?Owner $owner = null): Closure => static fn (): ConsignmentAgreement => ConsignmentAgreement::version($owner ?? $author, Fixtures::at(9), $term, $policy, StatementFrequency::Monthly, Fixtures::at(8), $extension, $extensions);

        yield 'the shop' => ['An agreement is made with a consignor, not with the shop.', $make(30, UnsoldPolicy::ReturnToConsignor, owner: Owner::shop())];
        yield 'no day' => ['A term lasts 1 to 3650 days; 0 does not.', $make(0, UnsoldPolicy::ReturnToConsignor)];
        yield 'too long' => ['A term lasts 1 to 3650 days; 3651 does not.', $make(3651, UnsoldPolicy::ReturnToConsignor)];
        yield 'an extension without term' => ['An agreement that extends a consignment has a term and the days each extension adds.', $make(null, UnsoldPolicy::Extend, 10)];
        yield 'an extension without days' => ['An agreement that extends a consignment has a term and the days each extension adds.', $make(30, UnsoldPolicy::Extend)];
        yield 'an empty extension' => ['An extension lasts 1 to 3650 days; 0 does not.', $make(30, UnsoldPolicy::Extend, 0)];
        yield 'no extension' => ['An agreement grants 1 to 12 extensions, within 3650 days in all; 0 of 10 days do not fit.', $make(30, UnsoldPolicy::Extend, 10, 0)];
        yield 'too many extensions' => ['An agreement grants 1 to 12 extensions, within 3650 days in all; 13 of 10 days do not fit.', $make(30, UnsoldPolicy::Extend, 10, 13)];
        yield 'too long with extensions' => ['An agreement grants 1 to 12 extensions, within 3650 days in all; 2 of 30 days do not fit.', $make(3600, UnsoldPolicy::Extend, 30, 2)];
        yield 'extension days without extending' => ['Only an agreement that extends a consignment adds extension days.', $make(30, UnsoldPolicy::BuyOut, 10)];
    }

    /**
     * @param Closure(): ConsignmentAgreement $make
     */
    #[DataProvider('refused')]
    public function testAnAgreementIsChecked(string $message, Closure $make): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);
        $make();
    }

    /**
     * @return iterable<string, array{StatementFrequency, string, string, string}>
     */
    public static function periods(): iterable
    {
        yield 'a week from a Wednesday' => [StatementFrequency::Weekly, '2026-11-18 10:00:00', '2026-11-09 00:00', '2026-11-16 00:00'];
        yield 'a week from a Monday at midnight' => [StatementFrequency::Weekly, '2026-11-16 00:00:00', '2026-11-09 00:00', '2026-11-16 00:00'];
        yield 'a week from a Sunday' => [StatementFrequency::Weekly, '2026-11-22 23:59:59', '2026-11-09 00:00', '2026-11-16 00:00'];
        yield 'a month' => [StatementFrequency::Monthly, '2026-11-20 10:00:00', '2026-10-01 00:00', '2026-11-01 00:00'];
        yield 'a month in January' => [StatementFrequency::Monthly, '2027-01-31 23:00:00', '2026-12-01 00:00', '2027-01-01 00:00'];
        yield 'a quarter' => [StatementFrequency::Quarterly, '2026-11-20 10:00:00', '2026-07-01 00:00', '2026-10-01 00:00'];
        yield 'a quarter in its first month' => [StatementFrequency::Quarterly, '2026-10-01 00:00:00', '2026-07-01 00:00', '2026-10-01 00:00'];
        yield 'a quarter in its last month' => [StatementFrequency::Quarterly, '2026-12-31 12:00:00', '2026-07-01 00:00', '2026-10-01 00:00'];
        yield 'the first quarter' => [StatementFrequency::Quarterly, '2027-02-10 12:00:00', '2026-10-01 00:00', '2027-01-01 00:00'];
        yield 'the second quarter' => [StatementFrequency::Quarterly, '2027-05-10 12:00:00', '2027-01-01 00:00', '2027-04-01 00:00'];
    }

    #[DataProvider('periods')]
    public function testTheLastClosedPeriod(StatementFrequency $frequency, string $at, string $start, string $end): void
    {
        $period = $frequency->lastClosedPeriod(Fixtures::utc($at));

        self::assertSame([$start, $end], [$period->start->format('Y-m-d H:i'), $period->end->format('Y-m-d H:i')]);
    }

    public function testThePeriodIsTakenInUtc(): void
    {
        // 20:30 in Toronto on the last day of October is already November in UTC.
        $period = StatementFrequency::Monthly->lastClosedPeriod(Fixtures::at(20, 30)->setDate(2026, 10, 31));

        self::assertSame('2026-10-01 00:00 UTC', $period->start->format('Y-m-d H:i T'));
    }

    public function testAPeriodIsHalfOpen(): void
    {
        $period = StatementPeriod::of(Fixtures::at(9), Fixtures::at(10));

        self::assertSame('UTC', $period->start->getTimezone()->getName());
        self::assertSame('UTC', $period->end->getTimezone()->getName());
        self::assertSame('14:00', $period->start->format('H:i'));
        self::assertTrue($period->contains(Fixtures::at(9)));
        self::assertTrue($period->contains(Fixtures::at(9, 59)));
        self::assertFalse($period->contains(Fixtures::at(10)));
        self::assertFalse($period->contains(Fixtures::at(8, 59)));
        self::assertSame(367 * 86_400, StatementPeriod::of(Fixtures::utc('2026-01-01'), Fixtures::utc('2027-01-03'))->end->getTimestamp() - Fixtures::utc('2026-01-01')->getTimestamp());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function badPeriods(): iterable
    {
        yield 'empty' => ['2026-11-20 10:00:00', '2026-11-20 10:00:00', 'A period ends after it starts; 2026-11-20T10:00:00+00:00 to 2026-11-20T10:00:00+00:00 does not.'];
        yield 'backwards' => ['2026-11-20 10:00:00', '2026-11-20 09:00:00', 'A period ends after it starts;'];
        yield 'too long' => ['2026-01-01 00:00:00', '2027-01-03 00:00:01', 'A statement covers 367 days at most; 2026-01-01T00:00:00+00:00 to 2027-01-03T00:00:01+00:00 is longer.'];
    }

    #[DataProvider('badPeriods')]
    public function testAPeriodIsChecked(string $start, string $end, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);
        StatementPeriod::of(Fixtures::utc($start), Fixtures::utc($end));
    }
}
