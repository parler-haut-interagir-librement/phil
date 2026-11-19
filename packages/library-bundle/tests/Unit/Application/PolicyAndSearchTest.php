<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\LibraryBundle\Application\LoanPolicy;
use Phil\LibraryBundle\Application\Message\SendLoanReminder;
use Phil\LibraryBundle\Application\Search\SearchCriteria;
use Phil\LibraryBundle\Application\Search\SearchResult;
use Phil\LibraryBundle\Application\Search\TitleRow;
use Phil\LibraryBundle\Domain\Exception\InvalidLibraryData;
use Phil\LibraryBundle\Domain\Status\TitleKind;
use Phil\Reservation\Rule\DurationLimits;
use Phil\Reservation\Rule\MaximumAdvance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoanPolicy::class)]
#[CoversClass(SearchCriteria::class)]
#[CoversClass(SearchResult::class)]
#[CoversClass(TitleRow::class)]
#[CoversClass(SendLoanReminder::class)]
final class PolicyAndSearchTest extends TestCase
{
    public function testThePolicyHasDefaults(): void
    {
        $policy = new LoanPolicy();

        self::assertSame([21, 2, 10, true, 90, null, 'UTC'], [$policy->loanDays, $policy->maxRenewals, $policy->maxOpenLoans, $policy->blockWhenOverdue, $policy->reservationDays, $policy->historyDays, $policy->timezone->getName()]);
        self::assertSame((21 * 3 + 1) * 1440, $policy->longestMinutes());
    }

    public function testADueDateIsTheEndOfTheLastDayOnTheLibrarysClock(): void
    {
        $policy = new LoanPolicy(7, timezone: 'America/Toronto');

        self::assertSame('2026-11-20T23:59:59-05:00', $policy->dueFrom(new DateTimeImmutable('2026-11-14 02:00 UTC'))->format(\DATE_ATOM), 'in Montréal it is still the 13th');
        self::assertSame('2026-03-15T23:59:59-04:00', $policy->dueFrom(new DateTimeImmutable('2026-03-08 12:00 America/Toronto'))->format(\DATE_ATOM), 'across the change of hour');
    }

    public function testACopyIsBookableOnceWithTheRules(): void
    {
        $bookable = new LoanPolicy(14, 1, reservationDays: 30, timezone: 'Europe/Paris')->bookable();

        self::assertSame([1, 'Europe/Paris'], [$bookable->capacity, $bookable->timezone->getName()]);
        self::assertEquals([new MaximumAdvance(30 * 1440), new DurationLimits(1, 29 * 1440)], $bookable->rules);
    }

    /**
     * @return iterable<string, array{array{loanDays?: int, maxRenewals?: int, maxOpenLoans?: int, reservationDays?: int, historyDays?: int}}>
     */
    public static function badPolicies(): iterable
    {
        yield 'no day' => [['loanDays' => 0]];
        yield 'more than a year' => [['loanDays' => 366]];
        yield 'negative renewals' => [['maxRenewals' => -1]];
        yield 'too many renewals' => [['maxRenewals' => 100]];
        yield 'no loan' => [['maxOpenLoans' => 0]];
        yield 'too many loans' => [['maxOpenLoans' => 1000]];
        yield 'no reservation' => [['reservationDays' => 0]];
        yield 'reserved too far' => [['reservationDays' => 731]];
        yield 'a negative history' => [['historyDays' => -1]];
        yield 'a history too long' => [['historyDays' => 36_501]];
    }

    /**
     * @param array{loanDays?: int, maxRenewals?: int, maxOpenLoans?: int, reservationDays?: int, historyDays?: int} $values
     */
    #[DataProvider('badPolicies')]
    public function testAPolicyOutOfBoundsIsRefused(array $values): void
    {
        $this->expectExceptionObject(InvalidLibraryData::because('A loan lasts 1 to 365 days, is renewed 0 to 99 times, a member holds 1 to 999 loans, a copy is reserved 1 to 730 days ahead, and a history is kept 0 to 36500 days.'));

        new LoanPolicy(...$values);
    }

    public function testThePolicyBounds(): void
    {
        $low = new LoanPolicy(1, 0, 1, false, 1, 0);
        $high = new LoanPolicy(365, 99, 999, true, 730, 36_500);

        self::assertSame([1, 0, 1, 1, 0], [$low->loanDays, $low->maxRenewals, $low->maxOpenLoans, $low->reservationDays, $low->historyDays]);
        self::assertSame([365, 99, 999, 730, 36_500], [$high->loanDays, $high->maxRenewals, $high->maxOpenLoans, $high->reservationDays, $high->historyDays]);

        $this->expectExceptionObject(InvalidLibraryData::because('"Mars/Olympus" is not a time zone.'));
        new LoanPolicy(timezone: 'Mars/Olympus');
    }

    public function testACriteriaIsTrimmedAndBounded(): void
    {
        $criteria = new SearchCriteria('  azul  ', TitleKind::Game, 4, 60, 8, 'fr', true);

        self::assertSame(['azul', TitleKind::Game, 4, 60, 8, 'fr', true, null], [$criteria->text, $criteria->kind, $criteria->players, $criteria->minutes, $criteria->age, $criteria->language, $criteria->availableOnly, $criteria->isbn()]);
        self::assertNull(new SearchCriteria('   ')->text);
        self::assertNull(new SearchCriteria()->isbn());
        self::assertSame('9780306406157', new SearchCriteria('0-306-40615-2')->isbn());
        self::assertSame([null, null, null, null, null, false], [new SearchCriteria()->kind, new SearchCriteria()->players, new SearchCriteria()->minutes, new SearchCriteria()->age, new SearchCriteria()->language, new SearchCriteria()->availableOnly]);
        self::assertSame(str_repeat('é', 100), new SearchCriteria(str_repeat('é', 100), players: 1, minutes: 1, age: 0)->text);
        self::assertSame(99, new SearchCriteria(players: 99, minutes: 10_080, age: 99)->players);
    }

    /**
     * @return iterable<string, array{array{text?: string, players?: int, minutes?: int, age?: int, language?: string}, string}>
     */
    public static function badCriteria(): iterable
    {
        $bounds = 'A search asks for players, minutes and an age within the bounds of a record.';

        yield 'a long text' => [['text' => str_repeat('a', 101)], 'A search is at most 100 characters.'];
        yield 'not UTF-8' => [['text' => "\xff"], 'A search is at most 100 characters.'];
        yield 'no player' => [['players' => 0], $bounds];
        yield 'too many players' => [['players' => 100], $bounds];
        yield 'no minute' => [['minutes' => 0], $bounds];
        yield 'more than a week' => [['minutes' => 10_081], $bounds];
        yield 'a negative age' => [['age' => -1], $bounds];
        yield 'an age too high' => [['age' => 100], $bounds];
        yield 'a language with something after the tag' => [['language' => 'fr!'], 'A search asks for a language by its tag, such as "fr" or "en-CA".'];
        yield 'a language with a line break after the tag' => [['language' => "fr\n"], 'A search asks for a language by its tag, such as "fr" or "en-CA".'];
        yield 'a language that is not a tag' => [['language' => 'Français'], 'A search asks for a language by its tag, such as "fr" or "en-CA".'];
    }

    /**
     * @param array{text?: string, players?: int, minutes?: int, age?: int, language?: string} $values
     */
    #[DataProvider('badCriteria')]
    public function testACriteriaOutOfBoundsIsRefused(array $values, string $message): void
    {
        $this->expectExceptionObject(InvalidLibraryData::because($message));

        new SearchCriteria(...$values);
    }

    public function testAResultCountsItsPages(): void
    {
        $row = new TitleRow('id', TitleKind::Game, 'Azul', 'Kiesling', 2017, 2, 4, 30, 45, 8, 3, 1);

        self::assertSame(['id', TitleKind::Game, 'Azul', 'Kiesling', 2017, 2, 4, 30, 45, 8, 3, 1], [$row->id, $row->kind, $row->name, $row->creators, $row->year, $row->minPlayers, $row->maxPlayers, $row->minMinutes, $row->maxMinutes, $row->minAge, $row->copies, $row->available]);
        self::assertSame([1, false, false], [new SearchResult([], 0, 1, 25)->pages(), new SearchResult([], 0, 1, 25)->hasPrevious(), new SearchResult([], 0, 1, 25)->hasNext()]);
        self::assertSame(1, new SearchResult([$row], 25, 1, 25)->pages());
        self::assertSame(2, new SearchResult([$row], 26, 1, 25)->pages());
        self::assertTrue(new SearchResult([$row], 26, 1, 25)->hasNext());
        self::assertFalse(new SearchResult([$row], 26, 2, 25)->hasNext());
        self::assertTrue(new SearchResult([$row], 26, 2, 25)->hasPrevious());
        self::assertSame([[$row], 26, 2, 25], [new SearchResult([$row], 26, 2, 25)->rows, new SearchResult([$row], 26, 2, 25)->total, new SearchResult([$row], 26, 2, 25)->page, new SearchResult([$row], 26, 2, 25)->perPage]);
    }

    public function testAReminderMessageCarriesIdentifiersOnly(): void
    {
        $message = new SendLoanReminder('loan', SendLoanReminder::OVERDUE, 2);

        self::assertSame(['loan', 'overdue', 2, 'due'], [$message->loanId, $message->kind, $message->step, SendLoanReminder::DUE]);
    }
}
