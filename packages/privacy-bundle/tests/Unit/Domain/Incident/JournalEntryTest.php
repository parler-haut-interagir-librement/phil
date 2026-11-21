<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Incident;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Incident\JournalEntry;
use Phil\PrivacyBundle\Domain\Incident\JournalKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(JournalEntry::class)]
#[CoversClass(JournalKind::class)]
final class JournalEntryTest extends TestCase
{
    private const string ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testAnEntryKeepsWhatItIsGivenAndIsNumbered(): void
    {
        $at = new DateTimeImmutable('2026-11-11T08:00:00Z');
        $until = new DateTimeImmutable('2026-12-11T08:00:00Z');
        $recorded = new DateTimeImmutable('2026-11-12T08:00:00Z');
        $entry = new JournalEntry(self::ID, 0, JournalKind::NoticeDeferral, $at, 'Police', 'Would warn the suspect', $until, $recorded);

        $numbered = $entry->numbered(3);

        self::assertSame([0, 3], [$entry->sequence, $numbered->sequence]);
        self::assertSame([self::ID, JournalKind::NoticeDeferral, $at, 'Police', 'Would warn the suspect', $until, $recorded], [$numbered->incidentId, $numbered->kind, $numbered->at, $numbered->party, $numbered->details, $numbered->until, $numbered->recordedAt]);
    }

    public function testTheLimitsAreInclusive(): void
    {
        $at = new DateTimeImmutable('2026-11-11T08:00:00Z');
        $entry = new JournalEntry(self::ID, 0, JournalKind::NoticeDeferral, $at, str_repeat('é', 255), str_repeat('é', 4000), $at, $at);

        self::assertSame(255, mb_strlen($entry->party));
        self::assertSame(4000, mb_strlen($entry->details));
    }

    public function testALiftedDeferralNeedsNoDetails(): void
    {
        $at = new DateTimeImmutable('2026-11-11T08:00:00Z');

        self::assertSame(' ', new JournalEntry(self::ID, 1, JournalKind::DeferralLifted, $at, 'Police', ' ', null, $at)->details);
    }

    /**
     * @return iterable<string, array{string, int, JournalKind, string, string, ?string, string, string}>
     */
    public static function refused(): iterable
    {
        $at = '2026-11-11T08:00:00Z';

        yield 'identifier' => ['ABC', 0, JournalKind::OfficerConsultation, 'Officer', 'Asked', null, $at, 'An incident identifier is 32 lowercase hexadecimal characters.'];
        yield 'negative number' => [self::ID, -1, JournalKind::OfficerConsultation, 'Officer', 'Asked', null, $at, 'The number of an entry of the journal cannot be negative.'];
        yield 'blank party' => [self::ID, 0, JournalKind::OfficerConsultation, ' ', 'Asked', null, $at, 'An entry of the journal names who received the information, who investigates or who was consulted, in at most 255 characters.'];
        yield 'long party' => [self::ID, 0, JournalKind::OfficerConsultation, str_repeat('a', 256), 'Asked', null, $at, 'An entry of the journal names who received the information, who investigates or who was consulted, in at most 255 characters.'];
        yield 'long details' => [self::ID, 0, JournalKind::DeferralLifted, 'Police', str_repeat('a', 4001), null, $at, 'The details of an entry of the journal are at most 4000 characters.'];
        yield 'communication without details' => [self::ID, 0, JournalKind::ThirdPartyCommunication, 'Bank', ' ', null, $at, 'A communication to a third party says which personal information was communicated, and how it could reduce the risk of injury.'];
        yield 'deferral without details' => [self::ID, 0, JournalKind::NoticeDeferral, 'Police', '', null, $at, 'A deferral of the notice says which investigation the notice could hamper.'];
        yield 'consultation without details' => [self::ID, 0, JournalKind::OfficerConsultation, 'Officer', '', null, $at, 'A consultation says what the person in charge of the protection of personal information was asked, and what they answered.'];
        yield 'end of something else' => [self::ID, 0, JournalKind::OfficerConsultation, 'Officer', 'Asked', '2026-11-12T08:00:00Z', $at, 'Only a deferral of the notice has an expected end.'];
        yield 'end of a lift' => [self::ID, 0, JournalKind::DeferralLifted, 'Police', '', '2026-11-12T08:00:00Z', $at, 'Only a deferral of the notice has an expected end.'];
        yield 'end before the start' => [self::ID, 0, JournalKind::NoticeDeferral, 'Police', 'Investigation', '2026-11-11T07:59:59Z', $at, 'A deferral of the notice cannot end before it starts.'];
        yield 'ahead of time' => [self::ID, 0, JournalKind::OfficerConsultation, 'Officer', 'Asked', null, '2026-11-11T07:59:59Z', 'An entry of the journal records what was done, never ahead of time.'];
    }

    #[DataProvider('refused')]
    public function testWhatIsRefused(string $id, int $sequence, JournalKind $kind, string $party, string $details, ?string $until, string $recorded, string $message): void
    {
        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        new JournalEntry($id, $sequence, $kind, new DateTimeImmutable('2026-11-11T08:00:00Z'), $party, $details, null === $until ? null : new DateTimeImmutable($until), new DateTimeImmutable($recorded));
    }

    public function testEachKindNamesItsSectionAndWhetherItSaysWhy(): void
    {
        self::assertSame(
            ['third_party_communication' => ['3.5', true], 'notice_deferral' => ['3.5', true], 'deferral_lifted' => ['3.5', false], 'officer_consultation' => ['3.7', true]],
            array_combine(
                array_map(static fn (JournalKind $kind): string => $kind->value, JournalKind::cases()),
                array_map(static fn (JournalKind $kind): array => [$kind->section(), $kind->needsDetails()], JournalKind::cases()),
            ),
        );
    }
}
