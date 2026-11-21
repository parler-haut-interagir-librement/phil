<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Incident;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\Incident\Incident;
use Phil\PrivacyBundle\Domain\Incident\NoticeOrganisation;
use Phil\PrivacyBundle\Domain\Incident\NoticeRecipient;
use Phil\PrivacyBundle\Domain\Incident\NoticeRubric;

/**
 * What a draft of a notice holds before it is written out: the incident, who gives the notice, the rubrics it shows in
 * the order of the regulation, and those of them the register cannot fill, which the draft marks to complete. A
 * follow-up to the Commission (section 4) shows the rubrics changed since `$since`.
 */
final readonly class IncidentNoticeDraft
{
    /**
     * @param list<NoticeRubric> $rubrics the rubrics shown, in the order of the recipient's section
     * @param list<NoticeRubric> $missing the rubrics shown that are left to complete, in the same order
     */
    public function __construct(
        public NoticeRecipient $recipient,
        public Incident $incident,
        public NoticeOrganisation $organisation,
        public array $rubrics,
        public array $missing,
        public ?DateTimeImmutable $since = null,
    ) {
    }

    public function isFollowUp(): bool
    {
        return $this->since instanceof DateTimeImmutable;
    }

    public function shows(NoticeRubric $rubric): bool
    {
        return \in_array($rubric, $this->rubrics, true);
    }

    public function lacks(NoticeRubric $rubric): bool
    {
        return \in_array($rubric, $this->missing, true);
    }

    /**
     * The rubrics changed since the last notice, without those that name who gives it.
     *
     * @return list<NoticeRubric>
     */
    public function changes(): array
    {
        return array_values(array_filter($this->rubrics, static fn (NoticeRubric $rubric): bool => !\in_array($rubric, IncidentNotices::IDENTIFICATION, true)));
    }
}
