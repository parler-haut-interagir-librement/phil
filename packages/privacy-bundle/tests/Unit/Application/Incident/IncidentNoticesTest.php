<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Incident;

use DateTimeImmutable;
use Phil\PrivacyBundle\Application\Contract\NoticeOrganisationSource;
use Phil\PrivacyBundle\Application\Exception\NoticeRefused;
use Phil\PrivacyBundle\Application\Incident\IncidentNoticeDraft;
use Phil\PrivacyBundle\Application\Incident\IncidentNotices;
use Phil\PrivacyBundle\Domain\Incident\IncidentRevision;
use Phil\PrivacyBundle\Domain\Incident\NoticeOrganisation;
use Phil\PrivacyBundle\Domain\Incident\NoticeRecipient;
use Phil\PrivacyBundle\Domain\Incident\NoticeRubric;
use Phil\PrivacyBundle\Domain\Incident\PublicNoticeReason;
use Phil\PrivacyBundle\Domain\Subject\DataCategory;
use Phil\PrivacyBundle\Tests\Unit\Infrastructure\Twig\NoticeFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IncidentNotices::class)]
#[CoversClass(IncidentNoticeDraft::class)]
#[CoversClass(NoticeRefused::class)]
#[CoversClass(NoticeOrganisation::class)]
final class IncidentNoticesTest extends TestCase
{
    public function testAFullRegisterAndOrganisationLeaveOnlyWhatTheRegisterDoesNotKeep(): void
    {
        $notices = new IncidentNotices($this->source(NoticeFixtures::organisation()));
        $incident = NoticeFixtures::complete();

        $commission = $notices->draft($incident, NoticeRecipient::Commission);
        self::assertSame(NoticeRecipient::Commission->rubrics(), $commission->rubrics);
        self::assertSame([NoticeRubric::Cause, NoticeRubric::AffectedInQuebec, NoticeRubric::OtherAuthority], $commission->missing);
        self::assertFalse($commission->isFollowUp());
        self::assertNull($commission->since);
        self::assertSame($incident, $commission->incident);
        self::assertEquals(NoticeFixtures::organisation(), $commission->organisation);

        $subject = $notices->draft($incident, NoticeRecipient::Subject);
        self::assertSame(NoticeRecipient::Subject, $subject->recipient);
        self::assertSame([NoticeRubric::SuggestedMeasures], $subject->missing);
        self::assertTrue($subject->lacks(NoticeRubric::SuggestedMeasures));
        self::assertFalse($subject->lacks(NoticeRubric::Measures));
        self::assertTrue($subject->shows(NoticeRubric::Contact));
        self::assertFalse($subject->shows(NoticeRubric::Organisation));
    }

    public function testALineJustRecordedWithoutAnOrganisationLeavesEveryUnknownRubric(): void
    {
        $commission = new IncidentNotices()->draft(NoticeFixtures::incomplete(), NoticeRecipient::Commission);

        self::assertSame([
            NoticeRubric::Organisation,
            NoticeRubric::EnterpriseNumber,
            NoticeRubric::Contact,
            NoticeRubric::Information,
            NoticeRubric::Cause,
            NoticeRubric::Occurrence,
            NoticeRubric::Affected,
            NoticeRubric::AffectedInQuebec,
            NoticeRubric::RiskAssessment,
            NoticeRubric::SubjectNotification,
            NoticeRubric::Measures,
            NoticeRubric::OtherAuthority,
        ], $commission->missing);
        self::assertEquals(new NoticeOrganisation(), $commission->organisation);
    }

    public function testEachPartOfTheOrganisationAndOfTheInformationCounts(): void
    {
        $partial = new IncidentNotices($this->source(new NoticeOrganisation('Atelier Fictif inc.', null, 'Camille Exemple')));
        $draft = $partial->draft(NoticeFixtures::complete(), NoticeRecipient::Commission);
        self::assertSame([NoticeRubric::EnterpriseNumber, NoticeRubric::Contact], \array_slice($draft->missing, 0, 2));

        $detailsOnly = new IncidentNotices($this->source(new NoticeOrganisation(null, '1170000000', null, 'vie-privee@atelier-fictif.example')));
        self::assertSame([NoticeRubric::Organisation, NoticeRubric::Contact], \array_slice($detailsOnly->draft(NoticeFixtures::complete(), NoticeRecipient::Commission)->missing, 0, 2));

        $line = NoticeFixtures::incomplete();
        $notices = new IncidentNotices();

        foreach ([
            'categories' => $line->update(categories: [DataCategory::fromString('email')]),
            'information' => $line->update(information: 'Names.'),
            'undescribed reason' => $line->update(undescribedReason: 'The export was encrypted by the thief.'),
        ] as $what => $described) {
            self::assertFalse($notices->draft($described, NoticeRecipient::Subject)->lacks(NoticeRubric::Information), $what);
        }

        self::assertTrue($notices->draft($line->update(measures: ' ', information: '   ', riskAssessment: ' '), NoticeRecipient::Commission)->lacks(NoticeRubric::Information));
        self::assertTrue($notices->draft($line->update(undescribedReason: '  '), NoticeRecipient::Subject)->lacks(NoticeRubric::Information));
        self::assertTrue($notices->draft($line->update(riskAssessment: ' '), NoticeRecipient::Commission)->lacks(NoticeRubric::RiskAssessment));
        self::assertTrue($notices->draft($line->update(measures: ' '), NoticeRecipient::Commission)->lacks(NoticeRubric::Measures));
    }

    public function testThePeopleAreNotifiedOneByOneOrInPublic(): void
    {
        $notices = new IncidentNotices();
        $line = NoticeFixtures::incomplete();

        self::assertFalse($notices->draft($line->update(subjectsNotifiedAt: new DateTimeImmutable('2026-11-12')), NoticeRecipient::Commission)->lacks(NoticeRubric::SubjectNotification));
        self::assertFalse($notices->draft(NoticeFixtures::incomplete(PublicNoticeReason::ExcessiveHardship), NoticeRecipient::Commission)->lacks(NoticeRubric::SubjectNotification));
    }

    public function testAPublicNoticeNeedsAReasonOfSection6(): void
    {
        try {
            new IncidentNotices()->draft(NoticeFixtures::complete(), NoticeRecipient::Public);
            self::fail('A public notice without a reason was drafted.');
        } catch (NoticeRefused $noticeRefused) {
            foreach (['section 6', 'greater injury (increased_injury)', 'excessively difficult (excessive_hardship)', 'no contact details for them (no_contact_details)', '--to=subject'] as $part) {
                self::assertStringContainsString($part, $noticeRefused->getMessage());
            }
        }

        foreach (PublicNoticeReason::cases() as $reason) {
            self::assertSame(NoticeRecipient::Public, new IncidentNotices()->draft(NoticeFixtures::complete($reason), NoticeRecipient::Public)->recipient);
        }
    }

    public function testAFollowUpShowsTheParagraphsCorrectedAfterTheNotice(): void
    {
        $draft = new IncidentNotices($this->source(NoticeFixtures::organisation()))->followUp(NoticeFixtures::complete(), NoticeFixtures::revisions());

        self::assertTrue($draft->isFollowUp());
        self::assertEquals(new DateTimeImmutable('2026-11-11T15:30:00Z'), $draft->since);
        self::assertSame(NoticeRecipient::Commission, $draft->recipient);
        self::assertSame([NoticeRubric::Organisation, NoticeRubric::EnterpriseNumber, NoticeRubric::Contact, NoticeRubric::Circumstances, NoticeRubric::Cause, NoticeRubric::Affected, NoticeRubric::AffectedInQuebec], $draft->rubrics);
        self::assertSame([NoticeRubric::Circumstances, NoticeRubric::Cause, NoticeRubric::Affected, NoticeRubric::AffectedInQuebec], $draft->changes());
        self::assertSame([NoticeRubric::Cause, NoticeRubric::AffectedInQuebec], $draft->missing);
    }

    public function testAFollowUpCanStartFromTheLastOne(): void
    {
        $notices = new IncidentNotices();

        self::assertSame([], $notices->followUp(NoticeFixtures::complete(), NoticeFixtures::revisions(), new DateTimeImmutable('2026-11-13T16:00:00Z'))->changes());
        self::assertSame([NoticeRubric::Circumstances, NoticeRubric::Cause, NoticeRubric::Affected, NoticeRubric::AffectedInQuebec], $notices->followUp(NoticeFixtures::complete(), NoticeFixtures::revisions(), new DateTimeImmutable('2026-11-13T15:59:59Z'))->changes());
        self::assertSame([NoticeRubric::Circumstances, NoticeRubric::Cause, NoticeRubric::Affected, NoticeRubric::AffectedInQuebec, NoticeRubric::Measures], $notices->followUp(NoticeFixtures::complete(), NoticeFixtures::revisions(), new DateTimeImmutable('2026-11-11T09:00:00Z'))->changes());
    }

    public function testAFollowUpReadsOnlyTheCorrectionsOfItsIncident(): void
    {
        $other = [new IncidentRevision('ffffffffffffffffffffffffffffffff', 1, 'measures', '', new DateTimeImmutable('2026-11-20T00:00:00Z'))];

        self::assertSame([], new IncidentNotices()->followUp(NoticeFixtures::complete(), $other)->changes());
    }

    public function testAFollowUpNeedsANotice(): void
    {
        $this->expectException(NoticeRefused::class);
        $this->expectExceptionMessageIsOrContains('A follow-up completes a notice already given to the Commission (section 4 of the regulation)');

        new IncidentNotices()->followUp(NoticeFixtures::incomplete(), NoticeFixtures::revisions());
    }

    private function source(NoticeOrganisation $organisation): NoticeOrganisationSource
    {
        return new readonly class($organisation) implements NoticeOrganisationSource {
            public function __construct(
                private NoticeOrganisation $organisation,
            ) {
            }

            public function organisation(): NoticeOrganisation
            {
                return $this->organisation;
            }
        };
    }
}
