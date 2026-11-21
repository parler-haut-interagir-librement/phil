<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Incident;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Incident\Incident;
use Phil\PrivacyBundle\Domain\Incident\PublicNoticeReason;
use Phil\PrivacyBundle\Domain\Subject\DataCategory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Incident::class)]
#[CoversClass(PublicNoticeReason::class)]
final class IncidentTest extends TestCase
{
    public function testAnIncidentIsRecordedThenCompleted(): void
    {
        $discovered = new DateTimeImmutable('2026-11-10T09:00:00Z');
        $incident = Incident::record('List exported', $discovered, $discovered->modify('-1 day'), [DataCategory::fromString('email')], 120, true);

        self::assertMatchesRegularExpression(Incident::ID_PATTERN, $incident->id);
        self::assertSame(['authority', 'subjects'], $incident->pendingNotifications());
        self::assertFalse($incident->isClosed());
        self::assertSame('', $incident->measures);

        $notified = $incident->update(measures: 'Folder removed', authorityNotifiedAt: $discovered->modify('+1 day'));
        self::assertSame(['subjects'], $notified->pendingNotifications());
        self::assertSame('Folder removed', $notified->measures);
        self::assertSame($incident->id, $notified->id);
        self::assertSame(120, $notified->affected);
        self::assertSame('email', $notified->categories[0]->value);

        $closed = $notified->update(subjectsNotifiedAt: $discovered->modify('+2 days'), closedAt: $discovered->modify('+3 days'));
        self::assertSame([], $closed->pendingNotifications());
        self::assertTrue($closed->isClosed());
        self::assertSame('Folder removed', $closed->measures);
        self::assertEquals($discovered->modify('+1 day'), $closed->authorityNotifiedAt);

        $lowered = $closed->update(seriousRisk: false, affected: 3, categories: [DataCategory::fromString('phone')]);
        self::assertFalse($lowered->seriousRisk);
        self::assertSame(3, $lowered->affected);
        self::assertSame('phone', $lowered->categories[0]->value);
    }

    public function testANewMomentReplacesTheOneAlreadyKept(): void
    {
        $at = new DateTimeImmutable('2026-11-10T09:00:00Z');
        $incident = new Incident(str_repeat('a', 32), 'x', $at, null, [], null, true, '', $at, $at, $at);

        $later = $incident->update(authorityNotifiedAt: $at->modify('+1 day'), subjectsNotifiedAt: $at->modify('+2 days'), closedAt: $at->modify('+3 days'));

        self::assertEquals($at->modify('+1 day'), $later->authorityNotifiedAt);
        self::assertEquals($at->modify('+2 days'), $later->subjectsNotifiedAt);
        self::assertEquals($at->modify('+3 days'), $later->closedAt);
    }

    public function testAnIncidentWithoutSeriousRiskOwesNoNotification(): void
    {
        $incident = Incident::record('A mail to the wrong person', new DateTimeImmutable('2026-11-10'), null, [], null, false, 'Asked them to delete it');

        self::assertSame([], $incident->pendingNotifications());
        self::assertNull($incident->affected);
        self::assertNull($incident->occurredAt);
        self::assertSame('Asked them to delete it', $incident->measures);
    }

    public function testTheSameMomentAsTheDiscoveryIsAllowed(): void
    {
        $at = new DateTimeImmutable('2026-11-10T09:00:00Z');
        $incident = new Incident(str_repeat('a', 32), 'x', $at, $at, [], 0, true, str_repeat('é', 4000), $at, $at, $at);

        self::assertSame([], $incident->pendingNotifications());
        self::assertSame(0, $incident->affected);
        self::assertSame(4000, mb_strlen(new Incident(str_repeat('a', 32), str_repeat('é', 4000), $at, null, [], null, false)->summary));
    }

    /**
     * @return iterable<string, array{callable(): Incident, string}>
     */
    public static function refused(): iterable
    {
        $at = new DateTimeImmutable('2026-11-10T09:00:00Z');
        $before = $at->modify('-1 second');
        $id = str_repeat('a', 32);

        yield 'identifier' => [static fn (): Incident => new Incident('x', 'x', $at, null, [], null, false), 'An incident identifier is 32 lowercase hexadecimal characters.'];
        yield 'no summary' => [static fn (): Incident => new Incident($id, ' ', $at, null, [], null, false), 'An incident has a summary'];
        yield 'long summary' => [static fn (): Incident => new Incident($id, str_repeat('a', 4001), $at, null, [], null, false), 'An incident has a summary'];
        yield 'long measures' => [static fn (): Incident => new Incident($id, 'x', $at, null, [], null, false, str_repeat('a', 4001)), 'An incident has a summary'];
        yield 'negative' => [static fn (): Incident => new Incident($id, 'x', $at, null, [], -1, false), 'cannot be negative'];
        yield 'occurred after' => [static fn (): Incident => new Incident($id, 'x', $at, $at->modify('+1 second'), [], null, false), 'cannot have occurred after it was discovered'];
        yield 'authority before' => [static fn (): Incident => new Incident($id, 'x', $at, null, [], null, false, '', $before), 'cannot be marked "authority notified" before'];
        yield 'people before' => [static fn (): Incident => new Incident($id, 'x', $at, null, [], null, false, '', null, $before), 'cannot be marked "people notified" before'];
        yield 'closed before' => [static fn (): Incident => new Incident($id, 'x', $at, null, [], null, false, '', null, null, $before), 'cannot be marked "closed" before'];
        yield 'long information' => [static fn (): Incident => new Incident($id, 'x', $at, null, [], null, false, information: str_repeat('a', 4001)), 'as is its information.'];
        yield 'long reason' => [static fn (): Incident => new Incident($id, 'x', $at, null, [], null, false, undescribedReason: str_repeat('a', 4001)), 'as is its undescribed reason.'];
        yield 'long assessment' => [static fn (): Incident => new Incident($id, 'x', $at, null, [], null, false, riskAssessment: str_repeat('a', 4001)), 'as is its risk assessment.'];
        yield 'long summary named' => [static fn (): Incident => new Incident($id, str_repeat('a', 4001), $at, null, [], null, false), 'as is its summary.'];
        yield 'long measures named' => [static fn (): Incident => new Incident($id, 'x', $at, null, [], null, false, str_repeat('a', 4001)), 'as is its measures.'];
        yield 'description and reason' => [static fn (): Incident => new Incident($id, 'x', $at, null, [], null, false, information: 'Emails', undescribedReason: 'Logs lost'), 'or says why it cannot be described, not both.'];
        yield 'approximate nobody' => [static fn (): Incident => new Incident($id, 'x', $at, null, [], null, false, affectedApproximate: true), 'An approximate number of people needs the number.'];
        yield 'discovery ends before it starts' => [static fn (): Incident => new Incident($id, 'x', $at, null, [], null, false, discoveredUntil: $before), 'The period of the discovery cannot end before it starts.'];
        yield 'incident ends before it starts' => [static fn (): Incident => new Incident($id, 'x', $at, $before, [], null, false, occurredUntil: $before->modify('-1 second')), 'The period of the incident cannot end before it starts.'];
        yield 'end without start' => [static fn (): Incident => new Incident($id, 'x', $at, null, [], null, false, occurredUntil: $before), 'needs the date it occurred.'];
        yield 'approximate without start' => [static fn (): Incident => new Incident($id, 'x', $at, null, [], null, false, occurrenceApproximate: true), 'needs the date it occurred.'];
        yield 'occurred after the discovery period' => [static fn (): Incident => new Incident($id, 'x', $at, $at->modify('+2 days'), [], null, false, discoveredUntil: $at->modify('+1 day')), 'cannot have occurred after it was discovered'];
        yield 'public notice without reason' => [static fn (): Incident => new Incident($id, 'x', $at, null, [], null, true, publicNoticeAt: $at), 'A public notice is recorded with its date and its reason.'];
        yield 'public reason without notice' => [static fn (): Incident => new Incident($id, 'x', $at, null, [], null, true, publicNoticeReason: PublicNoticeReason::NoContactDetails), 'A public notice is recorded with its date and its reason.'];
        yield 'public notice before' => [static fn (): Incident => new Incident($id, 'x', $at, null, [], null, true, publicNoticeAt: $before, publicNoticeReason: PublicNoticeReason::PromptAction), 'cannot be marked "public notice" before'];
        yield 'clearing the summary' => [static fn (): Incident => new Incident($id, 'x', $at, null, [], null, false)->update(clear: ['summary']), '"summary" cannot be emptied; the fields that can are occurred, occurred_until, discovered_until, affected, authority_notified, subjects_notified, public_notice, closed.'];
        yield 'twice a category' => [static fn (): Incident => new Incident($id, 'x', $at, null, [DataCategory::fromString('email'), DataCategory::fromString('email')], null, false), 'lists the category "email" twice'];
    }

    /**
     * @param callable(): Incident $build
     */
    #[DataProvider('refused')]
    public function testWhatIsRefused(callable $build, string $message): void
    {
        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    public function testEachParagraphOfSectionSevenIsAnsweredInTurn(): void
    {
        $at = new DateTimeImmutable('2026-11-10T09:00:00Z');
        $incident = Incident::record('List exported', $at, null, [], null, true);

        self::assertSame([1, 3, 5, 6, 7, 8], $incident->missingParagraphs());
        self::assertSame([3, 5, 6, 7, 8], $incident->update(information: 'Names and addresses')->missingParagraphs());
        self::assertSame([3, 5, 6, 7, 8], $incident->update(undescribedReason: 'The logs were overwritten')->missingParagraphs());
        self::assertSame([3, 5, 6, 7, 8], $incident->update(categories: [DataCategory::fromString('email')])->missingParagraphs());
        self::assertSame([1, 3, 5, 6, 7, 8], $incident->update(information: ' ', undescribedReason: ' ')->missingParagraphs(), 'Blank texts answer nothing.');

        $complete = $incident->update(
            measures: 'Folder removed',
            affected: 120,
            authorityNotifiedAt: $at,
            subjectsNotifiedAt: $at,
            occurredAt: $at->modify('-1 day'),
            information: 'Names and addresses',
            riskAssessment: 'Addresses of minors, public folder indexed: misuse likely.',
        );
        self::assertSame([], $complete->missingParagraphs());
        self::assertSame([6], $complete->update(riskAssessment: ' ')->missingParagraphs());
        self::assertSame([8], $complete->update(measures: ' ')->missingParagraphs());
        self::assertSame([7], $complete->update(clear: ['subjects_notified'])->missingParagraphs());
        self::assertSame([3], $complete->update(clear: ['occurred'])->missingParagraphs());
        self::assertSame([5], $complete->update(clear: ['affected'])->missingParagraphs());
        self::assertSame([], $complete->update(seriousRisk: false, clear: ['authority_notified', 'subjects_notified'])->missingParagraphs(), 'Without a serious risk, no notice is owed.');
    }

    public function testAPublicNoticeStandsForTheNoticeToEachPersonOnlyInTheCasesThatReplaceIt(): void
    {
        $at = new DateTimeImmutable('2026-11-10T09:00:00Z');
        $incident = Incident::record('Leak', $at, null, [], null, true);

        foreach ([PublicNoticeReason::IncreasedInjury, PublicNoticeReason::ExcessiveHardship, PublicNoticeReason::NoContactDetails] as $reason) {
            self::assertTrue($reason->replacesIndividualNotice());
            self::assertSame(['authority'], $incident->update(publicNoticeAt: $at, publicNoticeReason: $reason)->pendingNotifications(), $reason->value);
        }

        self::assertFalse(PublicNoticeReason::PromptAction->replacesIndividualNotice());
        $prompt = $incident->update(publicNoticeAt: $at->modify('+1 hour'), publicNoticeReason: PublicNoticeReason::PromptAction);
        self::assertSame(['authority', 'subjects'], $prompt->pendingNotifications());
        self::assertEquals($at->modify('+1 hour'), $prompt->publicNoticeAt);
        self::assertSame(PublicNoticeReason::PromptAction, $prompt->publicNoticeReason);

        $withdrawn = $prompt->update(clear: ['public_notice']);
        self::assertNull($withdrawn->publicNoticeAt);
        self::assertNull($withdrawn->publicNoticeReason);
    }

    public function testPeriodsAndApproximationsAreKeptAndCleared(): void
    {
        $at = new DateTimeImmutable('2026-11-10T09:00:00Z');
        $incident = new Incident(
            str_repeat('a', 32),
            'x',
            $at,
            $at->modify('-3 days'),
            [],
            40,
            false,
            occurredUntil: $at->modify('+1 day'),
            occurrenceApproximate: true,
            discoveredUntil: $at->modify('+2 days'),
            affectedApproximate: true
        );

        self::assertEquals($at->modify('+1 day'), $incident->occurredUntil, 'An incident may go on after it is found.');
        self::assertTrue($incident->occurrenceApproximate);
        self::assertTrue($incident->affectedApproximate);
        self::assertEquals($at->modify('+2 days'), $incident->knownSince());
        self::assertEquals($at, $incident->update(clear: ['discovered_until'])->knownSince());

        $kept = $incident->update(measures: 'm');
        self::assertEquals($at->modify('+1 day'), $kept->occurredUntil);
        self::assertTrue($kept->occurrenceApproximate);
        self::assertTrue($kept->affectedApproximate);
        self::assertEquals($at->modify('+2 days'), $kept->discoveredUntil);

        $exact = $incident->update(occurrenceApproximate: false, affectedApproximate: false);
        self::assertFalse($exact->occurrenceApproximate);
        self::assertFalse($exact->affectedApproximate);

        $noEnd = $incident->update(clear: ['occurred_until']);
        self::assertNull($noEnd->occurredUntil);
        self::assertTrue($noEnd->occurrenceApproximate);
        self::assertEquals($at->modify('-3 days'), $noEnd->occurredAt);

        $noOccurrence = $incident->update(clear: ['occurred']);
        self::assertNull($noOccurrence->occurredAt);
        self::assertNull($noOccurrence->occurredUntil);
        self::assertFalse($noOccurrence->occurrenceApproximate);

        $nobody = $incident->update(clear: ['affected']);
        self::assertNull($nobody->affected);
        self::assertFalse($nobody->affectedApproximate);

        $sameMoment = new Incident(str_repeat('a', 32), 'x', $at, $at, [], null, false, occurredUntil: $at, discoveredUntil: $at);
        self::assertEquals($at, $sameMoment->occurredUntil);
        self::assertEquals($at, $sameMoment->discoveredUntil);
        self::assertEquals($at->modify('+1 day'), new Incident(str_repeat('a', 32), 'x', $at, $at->modify('+1 day'), [], null, false, discoveredUntil: $at->modify('+1 day'))->occurredAt, 'It may start before the end of the discovery.');
    }

    public function testTheSummaryAndTheDiscoveryAreCorrected(): void
    {
        $at = new DateTimeImmutable('2026-11-10T09:00:00Z');
        $incident = Incident::record('Typo', $at, null, [], null, false, 'm');
        $corrected = $incident->update(summary: 'List exported to a public folder', discoveredAt: $at->modify('-1 day'), discoveredUntil: $at);

        self::assertSame($incident->id, $corrected->id);
        self::assertSame('List exported to a public folder', $corrected->summary);
        self::assertEquals($at->modify('-1 day'), $corrected->discoveredAt);
        self::assertEquals($at, $corrected->discoveredUntil);
        self::assertSame('m', $corrected->measures);
    }

    public function testEveryDateRecordedByMistakeCanBeEmptied(): void
    {
        $at = new DateTimeImmutable('2026-11-10T09:00:00Z');
        $incident = new Incident(str_repeat('a', 32), 'x', $at, null, [], null, true, '', $at, $at, $at);
        $emptied = $incident->update(clear: ['authority_notified', 'subjects_notified', 'closed']);

        self::assertNull($emptied->authorityNotifiedAt);
        self::assertNull($emptied->subjectsNotifiedAt);
        self::assertNull($emptied->closedAt);
        self::assertSame(['authority', 'subjects'], $emptied->pendingNotifications());
        self::assertEquals($at, $incident->update(clear: ['closed'])->authorityNotifiedAt);
        self::assertEquals($at, $incident->update(clear: ['authority_notified'])->subjectsNotifiedAt);
        self::assertEquals($at, $incident->update(clear: ['subjects_notified'])->closedAt);
        self::assertSame('P5Y', Incident::MINIMUM_RETENTION);
    }

    public function testTheNewTextsAreAtMostTheLimit(): void
    {
        $at = new DateTimeImmutable('2026-11-10T09:00:00Z');
        $long = str_repeat('é', Incident::MAX_TEXT);
        $incident = new Incident(str_repeat('a', 32), 'x', $at, null, [], null, false, information: $long, riskAssessment: $long);

        self::assertSame($long, $incident->information);
        self::assertSame($long, $incident->riskAssessment);
        self::assertSame($long, new Incident(str_repeat('a', 32), 'x', $at, null, [], null, false, undescribedReason: $long)->undescribedReason);
    }

    public function testABlankInformationAndABlankReasonCountAsLeftOut(): void
    {
        $at = new DateTimeImmutable('2026-11-10T09:00:00Z');

        self::assertSame('  ', new Incident(str_repeat('a', 32), 'x', $at, null, [], null, false, information: '  ', undescribedReason: 'Lost')->information);
        self::assertSame('  ', new Incident(str_repeat('a', 32), 'x', $at, null, [], null, false, information: 'Names', undescribedReason: '  ')->undescribedReason);
    }

    public function testACorrectionReplacesWhatTheLineAlreadyHeld(): void
    {
        $at = new DateTimeImmutable('2026-11-10T09:00:00Z');
        $incident = new Incident(
            str_repeat('a', 32),
            'x',
            $at,
            $at->modify('-5 days'),
            [],
            null,
            false,
            occurredUntil: $at->modify('-4 days'),
            discoveredUntil: $at->modify('+1 day'),
            publicNoticeAt: $at->modify('+2 days'),
            publicNoticeReason: PublicNoticeReason::PromptAction,
        );

        $corrected = $incident->update(
            discoveredUntil: $at->modify('+3 days'),
            occurredAt: $at->modify('-3 days'),
            occurredUntil: $at->modify('-2 days'),
            publicNoticeAt: $at->modify('+4 days'),
            publicNoticeReason: PublicNoticeReason::NoContactDetails,
        );

        self::assertEquals($at->modify('-3 days'), $corrected->occurredAt);
        self::assertEquals($at->modify('-2 days'), $corrected->occurredUntil);
        self::assertEquals($at->modify('+3 days'), $corrected->discoveredUntil);
        self::assertEquals($at->modify('+4 days'), $corrected->publicNoticeAt);
        self::assertSame(PublicNoticeReason::NoContactDetails, $corrected->publicNoticeReason);
    }
}
