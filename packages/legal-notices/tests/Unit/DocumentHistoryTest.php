<?php

declare(strict_types=1);

namespace Phil\LegalNotices\Tests\Unit;

use DateTimeImmutable;
use Phil\LegalNotices\DocumentHistory;
use Phil\LegalNotices\DocumentType;
use Phil\LegalNotices\Exception\InvalidLegalNotice;
use Phil\LegalNotices\LegalDocumentVersion;
use Phil\LegalNotices\PublicationState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentHistory::class)]
final class DocumentHistoryTest extends TestCase
{
    public function testTheVersionInForceAndTheNextOne(): void
    {
        $history = new DocumentHistory(DocumentType::LegalNotice, [
            self::version(4, '2027-03-01'),
            self::version(1, '2026-01-01'),
            self::version(3, '2026-06-01', PublicationState::Withdrawn),
            self::version(2, '2026-02-01'),
            self::version(5, '2027-02-01', PublicationState::Draft),
            self::version(6, '2027-03-01'),
            self::version(7, '2027-04-01'),
        ]);

        self::assertSame([1, 2, 3, 4, 5, 6, 7], array_map(static fn (LegalDocumentVersion $version): int => $version->number, $history->versions));
        self::assertNull($history->inForce(new DateTimeImmutable('2025-12-31')));
        self::assertSame(1, $history->inForce(new DateTimeImmutable('2026-01-01'))?->number);
        self::assertSame(2, $history->inForce(new DateTimeImmutable('2026-07-01'))?->number);
        self::assertSame(2, $history->inForce(new DateTimeImmutable('2027-02-15'))?->number);
        self::assertSame(6, $history->inForce(new DateTimeImmutable('2027-03-01'))?->number);
        self::assertSame(7, $history->inForce(new DateTimeImmutable('2027-05-01'))?->number);

        self::assertSame(1, $history->upcoming(new DateTimeImmutable('2025-01-01'))?->number);
        self::assertSame(6, $history->upcoming(new DateTimeImmutable('2026-07-01'))?->number);
        self::assertSame(6, $history->upcoming(new DateTimeImmutable('2026-03-01'))?->number);
        self::assertSame(7, $history->upcoming(new DateTimeImmutable('2027-03-01'))?->number);
        self::assertNull($history->upcoming(new DateTimeImmutable('2027-04-01')));

        self::assertSame([7, 6, 4, 3, 2, 1], array_map(static fn (LegalDocumentVersion $version): int => $version->number, $history->publicVersions()));
        self::assertSame(3, $history->find(3)?->number);
        self::assertNull($history->find(5));
        self::assertNull($history->find(8));
        self::assertSame(DocumentType::LegalNotice, $history->type);
    }

    public function testTheVersionThatTookEffectLastIsInForceWhateverItsNumber(): void
    {
        $history = new DocumentHistory(DocumentType::LegalNotice, [self::version(1, '2026-06-01'), self::version(2, '2026-01-01')]);

        self::assertSame(1, $history->inForce(new DateTimeImmutable('2026-07-01'))?->number);
        self::assertSame(2, $history->upcoming(new DateTimeImmutable('2025-07-01'))?->number);
    }

    public function testAnEmptyHistory(): void
    {
        $history = new DocumentHistory(DocumentType::PrivacyPolicy);

        self::assertSame([], $history->versions);
        self::assertSame([], $history->publicVersions());
        self::assertNull($history->inForce(new DateTimeImmutable()));
    }

    public function testAThousandVersionsAtMost(): void
    {
        $versions = array_map(static fn (int $number): LegalDocumentVersion => self::version($number, '2026-01-01'), range(1, 1000));
        self::assertCount(1000, new DocumentHistory(DocumentType::LegalNotice, $versions)->versions);

        $this->expectExceptionMessageIsOrContains('The legal_notice has 1000 versions at most.');
        new DocumentHistory(DocumentType::LegalNotice, [...$versions, self::version(1001, '2026-01-01')]);
    }

    public function testAVersionOfAnotherDocumentOrANumberTwiceIsRefused(): void
    {
        try {
            new DocumentHistory(DocumentType::PrivacyPolicy, [self::version(1, '2026-01-01')]);
            self::fail('A version of another document was accepted.');
        } catch (InvalidLegalNotice $invalidLegalNotice) {
            self::assertSame(['other_type', 'Version 1 is a version of the legal_notice, not of the privacy_policy.'], [$invalidLegalNotice->reason, $invalidLegalNotice->getMessage()]);
        }

        try {
            new DocumentHistory(DocumentType::LegalNotice, [self::version(2, '2026-01-01'), self::version(2, '2026-02-01')]);
            self::fail('A number given twice was accepted.');
        } catch (InvalidLegalNotice $invalidLegalNotice) {
            self::assertSame(['duplicate_number', 'The legal_notice has two versions 2.'], [$invalidLegalNotice->reason, $invalidLegalNotice->getMessage()]);
        }

        try {
            new DocumentHistory(DocumentType::LegalNotice, array_map(static fn (int $number): LegalDocumentVersion => self::version($number, '2026-01-01'), range(1, 1001)));
            self::fail('Too many versions were accepted.');
        } catch (InvalidLegalNotice $invalidLegalNotice) {
            self::assertSame('too_many_versions', $invalidLegalNotice->reason);
        }
    }

    private static function version(int $number, string $effectiveAt, PublicationState $state = PublicationState::Published): LegalDocumentVersion
    {
        $at = new DateTimeImmutable($effectiveAt);

        return new LegalDocumentVersion(DocumentType::LegalNotice, $number, ['en' => 'v'.$number], 'text', $at, $state, PublicationState::Draft === $state ? null : $at);
    }
}
