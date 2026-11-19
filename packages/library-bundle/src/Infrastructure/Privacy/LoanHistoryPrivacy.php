<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Infrastructure\Privacy;

use DateTimeInterface;
use Override;
use Phil\LibraryBundle\Domain\Contract\LoanRepository;
use Phil\LibraryBundle\Domain\Model\Loan;
use Phil\LibraryBundle\Domain\Model\Title;
use Phil\LibraryBundle\Domain\ValueObject\Member;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

/**
 * The history of a member's loans in an access or erasure request, the data subject being the member's identifier: the
 * export lists each loan — the title, the copy's barcode, its span, when it was handed over and closed, its state —; the
 * erasure forgets the member on every loan, which stays as a count of the copy's use. Running it again finds nothing
 * more and reports the same.
 */
final readonly class LoanHistoryPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'library';

    /** The loans listed at most. */
    private const int LOANS = 1000;

    public function __construct(
        private LoanRepository $loans,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    #[Override]
    public function export(DataSubject $subject): array
    {
        if (!Member::fits($subject->value)) {
            return [];
        }

        $loans = array_map(static fn (Loan $loan): array => [
            'loan' => $loan->id,
            'title' => self::title($loan->copy()->title()),
            'copy' => $loan->copy()->barcode(),
            'status' => $loan->status()->value,
            'starts_at' => $loan->startsAt()->format(DateTimeInterface::ATOM),
            'due_at' => $loan->dueAt()->format(DateTimeInterface::ATOM),
            'checked_out_at' => $loan->checkedOutAt()?->format(DateTimeInterface::ATOM),
            'closed_at' => $loan->closedAt()?->format(DateTimeInterface::ATOM),
            'renewals' => $loan->renewals(),
        ], $this->loans->ofMember($subject->value, self::LOANS));

        return [] === $loans ? [] : ['loans' => $loans];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        if (Member::fits($subject->value)) {
            $this->loans->forgetMember($subject->value);
        }

        return ErasureReport::of(self::MODULE, [Loan::TABLE.'.member' => ErasureEffect::Redacted]);
    }

    private static function title(Title $title): string
    {
        return $title->name();
    }
}
