<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Incident;

use DateTimeImmutable;
use Phil\PrivacyBundle\Application\Contract\NoticeOrganisationSource;
use Phil\PrivacyBundle\Application\Exception\NoticeRefused;
use Phil\PrivacyBundle\Domain\Incident\Incident;
use Phil\PrivacyBundle\Domain\Incident\IncidentRevision;
use Phil\PrivacyBundle\Domain\Incident\NoticeOrganisation;
use Phil\PrivacyBundle\Domain\Incident\NoticeRecipient;
use Phil\PrivacyBundle\Domain\Incident\NoticeRubric;
use Phil\PrivacyBundle\Domain\Incident\PublicNoticeReason;

/**
 * Drafts the notices of a confidentiality incident from the register, under sections 3 to 6 of the Québec *Regulation
 * respecting confidentiality incidents* (chapter A-2.1, r. 3.1): each rubric the register or the organisation knows is
 * filled, the others are marked to complete. A draft is only a draft: whether to notify, what to send and sending it
 * belong to the person in charge of the protection of personal information, and nothing here sends anything.
 */
final readonly class IncidentNotices
{
    /**
     * The rubrics that name who gives a notice to the Commission, at the head of a follow-up as of the notice.
     */
    public const array IDENTIFICATION = [NoticeRubric::Organisation, NoticeRubric::EnterpriseNumber, NoticeRubric::Contact];

    public function __construct(
        private ?NoticeOrganisationSource $organisations = null,
    ) {
    }

    /**
     * The notice to the recipient, every rubric of its section shown.
     *
     * @throws NoticeRefused for a public notice without a reason of section 6
     */
    public function draft(Incident $incident, NoticeRecipient $recipient): IncidentNoticeDraft
    {
        if (NoticeRecipient::Public === $recipient && !$incident->publicNoticeReason instanceof PublicNoticeReason) {
            throw NoticeRefused::publicWithoutReason();
        }

        return $this->compose($incident, $recipient, $recipient->rubrics());
    }

    /**
     * The follow-up to the Commission of section 4: the rubrics of section 3 a correction changed after the last notice,
     * the date of the notice to the Commission unless `$since` gives another, such as the date of the last follow-up.
     *
     * @param list<IncidentRevision> $revisions the corrections of the incident
     *
     * @throws NoticeRefused when no notice to the Commission is known
     */
    public function followUp(Incident $incident, array $revisions, ?DateTimeImmutable $since = null): IncidentNoticeDraft
    {
        $since ??= $incident->authorityNotifiedAt ?? throw NoticeRefused::followUpWithoutNotice();
        $changed = [];

        foreach ($revisions as $revision) {
            if ($revision->revisedAt > $since && $revision->incidentId === $incident->id) {
                foreach (NoticeRubric::fedBy($revision->field) as $rubric) {
                    $changed[] = $rubric->paragraph(NoticeRecipient::Commission);
                }
            }
        }

        // A changed paragraph is repeated whole: the cause with the circumstances, the people residing in Québec with the
        // number of people.
        $rubrics = array_values(array_filter(
            NoticeRecipient::Commission->rubrics(),
            static fn (NoticeRubric $rubric): bool => \in_array($rubric, self::IDENTIFICATION, true) || \in_array($rubric->paragraph(NoticeRecipient::Commission), $changed, true),
        ));

        return $this->compose($incident, NoticeRecipient::Commission, $rubrics, $since);
    }

    /**
     * @param list<NoticeRubric> $rubrics
     */
    private function compose(Incident $incident, NoticeRecipient $recipient, array $rubrics, ?DateTimeImmutable $since = null): IncidentNoticeDraft
    {
        $organisation = $this->organisations?->organisation() ?? new NoticeOrganisation();
        $missing = array_values(array_filter($rubrics, fn (NoticeRubric $rubric): bool => $this->lacks($rubric, $incident, $organisation)));

        return new IncidentNoticeDraft($recipient, $incident, $organisation, $rubrics, $missing, $since);
    }

    /**
     * Whether the register and the organisation leave the rubric unanswered. The cause, the people residing in Québec,
     * an authority outside Québec and the measures suggested to the people are not kept in the register: the person in
     * charge writes them in the draft.
     */
    private function lacks(NoticeRubric $rubric, Incident $incident, NoticeOrganisation $organisation): bool
    {
        return match ($rubric) {
            NoticeRubric::Organisation => null === $organisation->name,
            NoticeRubric::EnterpriseNumber => null === $organisation->enterpriseNumber,
            NoticeRubric::Contact => null === $organisation->contactName || null === $organisation->contactDetails,
            NoticeRubric::Information => [] === $incident->categories && '' === trim($incident->information) && '' === trim($incident->undescribedReason),
            NoticeRubric::Circumstances, NoticeRubric::Discovery => false,
            NoticeRubric::Occurrence => !$incident->occurredAt instanceof DateTimeImmutable,
            NoticeRubric::Affected => null === $incident->affected,
            NoticeRubric::RiskAssessment => '' === trim($incident->riskAssessment),
            NoticeRubric::SubjectNotification => !$incident->subjectsNotifiedAt instanceof DateTimeImmutable && !$incident->publicNoticeAt instanceof DateTimeImmutable,
            NoticeRubric::Measures => '' === trim($incident->measures),
            NoticeRubric::Cause, NoticeRubric::AffectedInQuebec, NoticeRubric::OtherAuthority, NoticeRubric::SuggestedMeasures => true,
        };
    }
}
