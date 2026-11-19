<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Tests\Unit\Application;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Override;
use Phil\LibraryBundle\Application\Contract\LoanTransaction;
use Phil\LibraryBundle\Application\Contract\StockLink;
use Phil\LibraryBundle\Application\Exception\LoanRefused;
use Phil\LibraryBundle\Application\Exception\NotFound;
use Phil\LibraryBundle\Application\LoanPolicy;
use Phil\LibraryBundle\Application\Loans;
use Phil\LibraryBundle\Domain\Event\CopyLent;
use Phil\LibraryBundle\Domain\Event\CopyReturned;
use Phil\LibraryBundle\Domain\Exception\InvalidLibraryData;
use Phil\LibraryBundle\Domain\Model\Loan;
use Phil\LibraryBundle\Domain\Status\CopyCondition;
use Phil\LibraryBundle\Domain\Status\LoanStatus;
use Phil\LibraryBundle\Tests\Unit\Fixture\Library;
use Phil\LibraryBundle\Tests\Unit\Fixture\Recorders;
use Phil\Reservation\Interval;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Loans::class)]
#[CoversClass(LoanRefused::class)]
final class LoansTest extends TestCase
{
    private Library $library;

    private Loans $loans;

    #[Override]
    protected function setUp(): void
    {
        $this->library = new Library();
        $this->loans = $this->library->service;
        $this->library->shelve('Azul', ['G-1', 'G-2']);
        $this->library->shelve('Catan', ['G-3'], true);
    }

    public function testACopyIsLentUntilTheEndOfItsLastDay(): void
    {
        $loan = $this->loans->lend('G-1', 'card-7');

        self::assertSame([LoanStatus::Active, 'card-7', '2026-11-27 23:59:59'], [$loan->status(), $loan->member(), $this->local($loan->dueAt())]);
        self::assertSame(1, $loan->copy()->claims());
        self::assertSame([$loan->id], $this->library->loans->saved);
        self::assertEquals([new CopyLent($loan->id, $loan->copy()->id)], $this->library->recorders->events);
        self::assertSame([], $this->library->recorders->lines, 'an unlinked copy moves nothing');
        self::assertSame($loan, $this->loans->get($loan->id));
    }

    public function testADueDateGivenIsKept(): void
    {
        $loan = $this->loans->lend('G-1', 'card-7', $this->at('2026-11-14 12:00'));
        self::assertSame('2026-11-14 12:00:00', $this->local($loan->dueAt()));

        foreach (['2026-11-13 10:00', '2026-11-13 09:00'] as $due) {
            try {
                $this->loans->lend('G-2', 'card-7', $this->at($due));
                self::fail('A loan was due when it began.');
            } catch (InvalidLibraryData $invalid) {
                self::assertSame('A loan is due after it starts.', $invalid->getMessage());
            }
        }

        $this->expectExceptionObject(LoanRefused::because('too_long', ['minutes' => 30 * 1440]));
        $this->loans->lend('G-2', 'card-7', $this->at('2026-12-14 12:00'));
    }

    public function testALinkedCopyMovesItsItem(): void
    {
        $loan = $this->loans->lend('G-3', 'card-7');
        $back = $this->loans->giveBack('G-3');

        self::assertSame(['out item-G-3 '.$loan->id, 'back item-G-3 '.$loan->id], $this->library->recorders->lines);
        self::assertSame($loan, $back);

        $lost = $this->loans->lend('G-3', 'card-8');
        $this->loans->lose($lost->id, 'Never came back.');
        self::assertSame(['out item-G-3 '.$loan->id, 'back item-G-3 '.$loan->id, 'out item-G-3 '.$lost->id, 'lost item-G-3 '.$lost->id], $this->library->recorders->lines);
        self::assertSame([LoanStatus::Lost, CopyCondition::Withdrawn, 'Never came back.'], [$lost->status(), $lost->copy()->condition(), $lost->copy()->note()]);
    }

    public function testTheLoansThatMoveStockRunInTheTransaction(): void
    {
        $transaction = new class($this->library->recorders) implements LoanTransaction {
            /** @var list<string> the stock lines each transaction wrote */
            public array $lines = [];

            public function __construct(
                private readonly Recorders $recorders,
            ) {
            }

            public function run(Closure $work): mixed
            {
                $before = \count($this->recorders->lines);
                $result = $work();
                $this->lines[] = implode(', ', \array_slice($this->recorders->lines, $before));

                return $result;
            }
        };
        $loans = new Loans($this->library->copies, $this->library->loans, $this->library->recorders, $this->library->policy, $this->library->clock, $this->library->recorders, $transaction);

        $lent = $loans->lend('G-3', 'card-7');
        $loans->giveBack('G-3');
        $lost = $loans->lend('G-3', 'card-8');
        $loans->lose($lost->id);
        $copy = $this->library->catalogue->copyByBarcode('G-1');
        $reserved = $loans->reserve($copy->id, 'card-9', $this->at('2026-11-20 10:00'), $this->at('2026-11-25 10:00'));
        $loans->cancel($reserved->id);
        $again = $loans->reserve($copy->id, 'card-9', $this->at('2026-11-20 10:00'), $this->at('2026-11-25 10:00'));
        $this->library->clock->modify('+7 days');
        $out = $loans->checkOut($again->id);
        $loans->renew($out->id);

        self::assertSame([
            'out item-G-3 '.$lent->id,
            'back item-G-3 '.$lent->id,
            'out item-G-3 '.$lost->id,
            'lost item-G-3 '.$lost->id,
            '',
        ], $transaction->lines);
    }

    public function testAStockroomThatRefusesKeepsTheLoanOut(): void
    {
        $refusing = new class implements StockLink {
            public function out(string $item, string $loan): void
            {
                throw new DomainException('Not in stock.');
            }

            public function back(string $item, string $loan): void
            {
            }

            public function lost(string $item, string $loan): void
            {
            }
        };
        $loans = new Loans($this->library->copies, $this->library->loans, $refusing, $this->library->policy, $this->library->clock);

        try {
            $loans->lend('G-3', 'card-7');
            self::fail('A copy not in stock was lent.');
        } catch (DomainException) {
            self::assertSame([], $this->library->loans->saved);
            self::assertSame(0, $this->library->catalogue->copyByBarcode('G-3')->claims());
        }
    }

    public function testACopyOutIsNotLentAgain(): void
    {
        $this->loans->lend('G-1', 'card-7');

        $this->expectExceptionObject(LoanRefused::because('taken'));
        $this->loans->lend('G-1', 'card-8');
    }

    public function testAWithdrawnCopyIsNotLent(): void
    {
        $this->library->catalogue->assess($this->library->catalogue->copyByBarcode('G-1')->id, CopyCondition::Withdrawn);

        $this->expectExceptionObject(LoanRefused::because('withdrawn'));
        $this->loans->lend('G-1', 'card-7');
    }

    public function testAnUnknownCopyOrAnAddressIsRefused(): void
    {
        try {
            $this->loans->lend('ghost', 'card-7');
            self::fail('A copy that does not exist was lent.');
        } catch (NotFound $notFound) {
            self::assertSame('There is no copy "ghost".', $notFound->getMessage());
        }

        $this->expectException(InvalidLibraryData::class);
        $this->loans->lend('G-1', 'jo@example.com');
    }

    public function testAMemberHoldsSoManyLoansAtOnce(): void
    {
        $this->library->shelve('Dixit', ['G-4', 'G-5']);
        $this->loans->lend('G-1', 'card-7');
        $this->loans->lend('G-2', 'card-7');
        $this->loans->reserve($this->library->catalogue->copyByBarcode('G-4')->id, 'card-7', $this->at('2026-11-20 10:00'), $this->at('2026-11-21 10:00'));

        try {
            $this->loans->lend('G-5', 'card-7');
            self::fail('A fourth loan was lent.');
        } catch (LoanRefused $loanRefused) {
            self::assertSame(['limit', ['max' => 3], 'The loan is refused: limit.'], [$loanRefused->reason, $loanRefused->parameters, $loanRefused->getMessage()]);
        }

        $this->loans->giveBack('G-1');
        self::assertSame(LoanStatus::Active, $this->loans->lend('G-5', 'card-7')->status());
    }

    public function testAMemberWithACopyOverdueBorrowsNoMore(): void
    {
        $this->loans->lend('G-1', 'card-7');
        $this->library->clock->modify('+15 days');

        try {
            $this->loans->lend('G-2', 'card-7');
            self::fail('A member with a copy overdue borrowed.');
        } catch (LoanRefused $loanRefused) {
            self::assertSame(['overdue', []], [$loanRefused->reason, $loanRefused->parameters]);
        }

        $lenient = new Library(new LoanPolicy(14, 1, 3, false, 30, null, 'America/Toronto'));
        $lenient->shelve('Azul', ['G-1', 'G-2']);
        $lenient->service->lend('G-1', 'card-7');
        $lenient->clock->modify('+15 days');
        self::assertSame(LoanStatus::Active, $lenient->service->lend('G-2', 'card-7')->status());
    }

    public function testAnOverdueCopyIsStillTaken(): void
    {
        $this->loans->lend('G-1', 'card-7');
        $this->library->clock->modify('+20 days');

        $this->expectExceptionObject(LoanRefused::because('taken'));
        $this->loans->lend('G-1', 'card-8');
    }

    public function testAReservationKeepsTheCopyForItsSpan(): void
    {
        $copy = $this->library->catalogue->copyByBarcode('G-1');
        $reserved = $this->loans->reserve($copy->id, 'card-7', $this->at('2026-11-20 10:00'), $this->at('2026-11-25 10:00'));

        self::assertSame([LoanStatus::Reserved, 1], [$reserved->status(), $copy->claims()]);
        self::assertSame([], $this->library->recorders->events, 'nothing is handed over yet');
        self::assertFalse($this->loans->isFree($copy, new Interval($this->at('2026-11-24 10:00'), $this->at('2026-11-26 10:00'))));
        self::assertTrue($this->loans->isFree($copy, new Interval($this->at('2026-11-25 10:00'), $this->at('2026-11-26 10:00'))));
        self::assertSame([$reserved], $this->loans->holding($copy));

        try {
            $this->loans->lend('G-1', 'card-8');
            self::fail('A loan ran over a reservation.');
        } catch (LoanRefused $loanRefused) {
            self::assertSame('taken', $loanRefused->reason);
        }

        self::assertSame('2026-11-19 23:59:59', $this->local($this->loans->lend('G-1', 'card-8', $this->at('2026-11-19 23:59:59'))->dueAt()), 'a loan back before it is lent');
    }

    public function testAReservationIsBoundedAhead(): void
    {
        $copy = $this->library->catalogue->copyByBarcode('G-1');

        try {
            $this->loans->reserve($copy->id, 'card-7', $this->at('2026-12-14 10:00'), $this->at('2026-12-15 10:00'));
            self::fail('A copy was reserved too far ahead.');
        } catch (LoanRefused $loanRefused) {
            self::assertSame(['too_far_ahead', ['minutes' => 30 * 1440]], [$loanRefused->reason, $loanRefused->parameters]);
        }

        foreach ([fn (): mixed => $this->loans->reserve('ghost', 'card-7', $this->at('2026-11-20'), $this->at('2026-11-21')), fn (): mixed => $this->loans->reserve($copy->id, 'card-7', $this->at('2026-11-13 10:00'), $this->at('2026-11-21'))] as $call) {
            try {
                $call();
                self::fail('A reservation was made.');
            } catch (NotFound|InvalidLibraryData) {
            }
        }

        self::assertSame([], $this->library->loans->saved);
    }

    public function testAReservationIsCheckedOutAtTheDesk(): void
    {
        $copy = $this->library->catalogue->copyByBarcode('G-3');
        $reserved = $this->loans->reserve($copy->id, 'card-7', $this->at('2026-11-20 10:00'), $this->at('2026-11-25 10:00'));
        $this->library->clock->modify('+7 days');

        $loan = $this->loans->checkOut($reserved->id);

        self::assertSame([LoanStatus::Active, 2], [$loan->status(), $copy->claims()]);
        self::assertSame(['out item-G-3 '.$loan->id], $this->library->recorders->lines);
        self::assertEquals([new CopyLent($loan->id, $copy->id)], $this->library->recorders->events);

        try {
            $this->loans->checkOut($loan->id);
            self::fail('A loan out was checked out again.');
        } catch (InvalidLibraryData $invalidLibraryData) {
            self::assertSame('A loan active is not checked out.', $invalidLibraryData->getMessage());
        }
    }

    public function testAReservationWhoseCopyIsStillOutIsNotCheckedOut(): void
    {
        $copy = $this->library->catalogue->copyByBarcode('G-1');
        $this->loans->lend('G-1', 'card-8', $this->at('2026-11-19 12:00'));
        $reserved = $this->loans->reserve($copy->id, 'card-7', $this->at('2026-11-20 10:00'), $this->at('2026-11-25 10:00'));
        $this->library->clock->modify('+7 days');

        $this->expectExceptionObject(LoanRefused::because('taken'));
        $this->loans->checkOut($reserved->id);
    }

    public function testAWithdrawnCopyIsNotCheckedOut(): void
    {
        $copy = $this->library->catalogue->copyByBarcode('G-1');
        $reserved = $this->loans->reserve($copy->id, 'card-7', $this->at('2026-11-20 10:00'), $this->at('2026-11-25 10:00'));
        $this->library->catalogue->assess($copy->id, CopyCondition::Withdrawn);

        $this->expectExceptionObject(LoanRefused::because('withdrawn'));
        $this->loans->checkOut($reserved->id);
    }

    public function testACopyComesBackWithItsCondition(): void
    {
        $loan = $this->loans->lend('G-1', 'card-7');
        $this->library->clock->modify('+3 days');

        $back = $this->loans->giveBack('G-1', CopyCondition::Incomplete, 'Two tiles missing.');

        self::assertSame([LoanStatus::Returned, '2026-11-16 10:00:00', CopyCondition::Incomplete, 'Two tiles missing.'], [$back->status(), $this->local($back->closedAt() ?? new DateTimeImmutable()), $back->copy()->condition(), $back->copy()->note()]);
        self::assertEquals(new CopyReturned($loan->id, $loan->copy()->id, CopyCondition::Incomplete), $this->library->recorders->events[1]);

        $this->loans->lend('G-1', 'card-7');
        self::assertSame(CopyCondition::Incomplete, $this->loans->giveBack('G-1')->copy()->condition(), 'no condition keeps the one it had');
        self::assertSame('Two tiles missing.', $loan->copy()->note());

        try {
            $this->loans->giveBack('G-1');
            self::fail('A copy on the shelf came back.');
        } catch (NotFound $notFound) {
            self::assertSame('The copy "G-1" is not out.', $notFound->getMessage());
        }

        $this->expectExceptionObject(NotFound::copy('ghost'));
        $this->loans->giveBack('ghost');
    }

    public function testALoanIsRenewedFromItsDueDate(): void
    {
        $loan = $this->loans->lend('G-1', 'card-7');
        $renewed = $this->loans->renew($loan->id);

        self::assertSame(['2026-12-11 23:59:59', 1, 2], [$this->local($renewed->dueAt()), $renewed->renewals(), $loan->copy()->claims()]);

        try {
            $this->loans->renew($loan->id);
            self::fail('A loan was renewed twice.');
        } catch (LoanRefused $loanRefused) {
            self::assertSame(['renewals', ['max' => 1]], [$loanRefused->reason, $loanRefused->parameters]);
        }
    }

    public function testALoanReservedAfterIsNotRenewed(): void
    {
        $loan = $this->loans->lend('G-1', 'card-7');
        $this->loans->reserve($loan->copy()->id, 'card-8', $this->at('2026-11-30 10:00'), $this->at('2026-12-02 10:00'));

        try {
            $this->loans->renew($loan->id);
            self::fail('A loan ran over a reservation.');
        } catch (LoanRefused $loanRefused) {
            self::assertSame('taken', $loanRefused->reason);
        }

        self::assertSame(0, $loan->renewals());
    }

    public function testOnlyAnActiveLoanIsRenewed(): void
    {
        $copy = $this->library->catalogue->copyByBarcode('G-1');
        $reserved = $this->loans->reserve($copy->id, 'card-7', $this->at('2026-11-20 10:00'), $this->at('2026-11-25 10:00'));

        $this->expectExceptionObject(InvalidLibraryData::because('A loan reserved is not renewed.'));
        $this->loans->renew($reserved->id);
    }

    public function testAReservationIsCancelled(): void
    {
        $copy = $this->library->catalogue->copyByBarcode('G-1');
        $reserved = $this->loans->reserve($copy->id, 'card-7', $this->at('2026-11-20 10:00'), $this->at('2026-11-25 10:00'));

        self::assertSame(LoanStatus::Cancelled, $this->loans->cancel($reserved->id)->status());
        self::assertSame('2026-11-13 10:00:00', $this->local($reserved->closedAt() ?? new DateTimeImmutable()));
        self::assertSame([$reserved->id, $reserved->id], $this->library->loans->saved);
        self::assertTrue($this->loans->isFree($copy, new Interval($this->at('2026-11-20 10:00'), $this->at('2026-11-25 10:00'))));

        $this->expectExceptionObject(NotFound::loan('ghost'));
        $this->loans->cancel('ghost');
    }

    public function testEveryChangeOfALoanIsSaved(): void
    {
        $copy = $this->library->catalogue->copyByBarcode('G-1');
        $lent = $this->loans->lend('G-1', 'card-7');
        $this->loans->renew($lent->id);
        $this->loans->giveBack('G-1');

        $reserved = $this->loans->reserve($copy->id, 'card-7', $this->at('2026-11-20 10:00'), $this->at('2026-11-25 10:00'));
        $this->library->loans->saved = [];
        $this->library->clock->modify('+7 days');

        $this->loans->checkOut($reserved->id);
        self::assertSame([$reserved->id], $this->library->loans->saved);

        $this->library->loans->saved = [];
        $this->loans->giveBack('G-1');
        self::assertSame([$reserved->id], $this->library->loans->saved, 'a copy given back');

        $lost = $this->loans->lend('G-2', 'card-7');
        $this->library->loans->saved = [];
        $this->loans->lose($lost->id, 'Gone');
        self::assertSame([$lost->id], $this->library->loans->saved, 'a copy lost');

        $again = $this->loans->lend('G-3', 'card-7');
        $this->library->loans->saved = [];
        $this->loans->renew($again->id);
        self::assertSame([$again->id], $this->library->loans->saved, 'a loan renewed');
    }

    public function testAReservationIsRefusedToAMemberWhoMayNotBorrow(): void
    {
        $copy = $this->library->catalogue->copyByBarcode('G-1');
        $this->loans->lend('G-2', 'card-7', $this->at('2026-11-14 10:00'));
        $this->library->clock->modify('2026-11-15 10:00');

        try {
            $this->loans->reserve($copy->id, 'card-7', $this->at('2026-11-20 10:00'), $this->at('2026-11-25 10:00'));
            self::fail('A member with a copy overdue reserved.');
        } catch (LoanRefused $loanRefused) {
            self::assertSame('overdue', $loanRefused->reason);
        }

        self::assertSame([], $this->library->loans->holding($copy));
    }

    public function testALoanThatIsNotReservedIsRefusedBeforeAnythingElseIsChecked(): void
    {
        $copy = $this->library->catalogue->copyByBarcode('G-1');
        $reserved = $this->loans->reserve($copy->id, 'card-7', $this->at('2026-11-20 10:00'), $this->at('2026-11-25 10:00'));
        $this->loans->cancel($reserved->id);
        $this->loans->lend('G-1', 'card-8', $this->at('2026-11-26 10:00'));

        $this->expectExceptionObject(InvalidLibraryData::because('A loan cancelled is not checked out.'));
        $this->loans->checkOut($reserved->id);
    }

    public function testALoanThatIsNotActiveIsRefusedBeforeTheRenewalsAreCounted(): void
    {
        $library = new Library(new LoanPolicy(14, 0, 3, true, 30, 365, 'America/Toronto'));
        $library->shelve('Azul', ['G-1']);

        $reserved = $library->service->reserve($library->catalogue->copyByBarcode('G-1')->id, 'card-7', $this->at('2026-11-20 10:00'), $this->at('2026-11-25 10:00'));

        $this->expectExceptionObject(InvalidLibraryData::because('A loan reserved is not renewed.'));
        $library->service->renew($reserved->id);
    }

    public function testNothingNeedsADispatcher(): void
    {
        $library = new Library();
        $library->shelve('Azul', ['G-1']);

        $silent = new Loans($library->copies, $library->loans, $library->recorders, $library->policy, $library->clock);

        $loan = $silent->lend('G-1', 'card-7');
        self::assertSame(LoanStatus::Returned, $silent->giveBack('G-1')->status());
        self::assertSame(LoanStatus::Returned, $loan->status());
        self::assertSame([], $library->recorders->events);
    }

    public function testAMembersLoansAreListedByDefaultAHundredAtATime(): void
    {
        $this->loans->ofMember('card-7');
        self::assertSame(100, end($this->library->loans->limits));

        $this->loans->ofMember('card-7', 5000);
        self::assertSame(1000, end($this->library->loans->limits));

        $this->loans->ofMember('card-7', 1000);
        self::assertSame(1000, end($this->library->loans->limits));

        $this->loans->ofMember('card-7', 0);
        self::assertSame(1, end($this->library->loans->limits));
    }

    public function testAMembersLoansAreListed(): void
    {
        $first = $this->loans->lend('G-1', 'card-7');
        $second = $this->loans->lend('G-2', 'card-7');
        $this->loans->giveBack('G-1');

        self::assertSame([$second, $first], $this->loans->ofMember('card-7'));
        self::assertSame([$second], $this->loans->ofMember('card-7', 1));
        self::assertSame([$second], $this->loans->ofMember('card-7', open: true));
        self::assertSame([$second], $this->loans->ofMember('card-7', 0), 'one at least');

        $this->expectException(InvalidLibraryData::class);
        $this->loans->ofMember('jo@example.com');
    }

    public function testTheHistoryIsForgottenAfterTheDaysThePolicyKeepsIt(): void
    {
        $old = $this->loans->lend('G-1', 'card-7');
        $this->loans->giveBack('G-1');
        $this->library->clock->modify('+364 days');
        $recent = $this->loans->lend('G-1', 'card-8');
        $this->loans->giveBack('G-1');
        $this->loans->lend('G-2', 'card-9');

        $this->library->clock->modify('+1 day');

        self::assertSame(0, $this->loans->forgetHistory(), 'closed exactly 365 days ago, it is kept');
        $this->library->clock->modify('+1 second');
        self::assertSame(1, $this->loans->forgetHistory());
        self::assertSame([null, 'card-8'], [$old->member(), $recent->member()]);

        $forever = new Library(new LoanPolicy(timezone: 'America/Toronto'));
        $forever->shelve('Azul', ['G-1']);

        $kept = $forever->service->lend('G-1', 'card-7');
        $forever->service->giveBack('G-1');
        $forever->clock->modify('+100 years');
        self::assertSame(0, $forever->service->forgetHistory());
        self::assertSame('card-7', $kept->member());
    }

    public function testTheRefusalsOfTheReservationLibraryAreRead(): void
    {
        $loan = $this->loans->lend('G-1', 'card-7', $this->at('2026-11-14 12:00'));
        self::assertInstanceOf(Loan::class, $loan);
        $this->library->clock->modify('+2 days');
        $this->loans->giveBack('G-1');

        self::assertTrue($this->loans->isFree($loan->copy(), new Interval($this->at('2026-11-20'), $this->at('2026-11-21'))));
        $this->library->catalogue->assess($loan->copy()->id, CopyCondition::Withdrawn);
        self::assertFalse($this->loans->isFree($loan->copy(), new Interval($this->at('2026-11-20'), $this->at('2026-11-21'))));
    }

    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone('America/Toronto'));
    }

    private function local(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('America/Toronto'))->format('Y-m-d H:i:s');
    }
}
