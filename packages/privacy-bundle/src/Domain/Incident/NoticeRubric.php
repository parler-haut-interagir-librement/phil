<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Incident;

/**
 * A rubric of a notice of a confidentiality incident, and the paragraph of the regulation that asks for it: section 3
 * for the notice to the Commission, section 5 for the notice to the people concerned. A rubric a notice does not
 * contain has no paragraph for it.
 */
enum NoticeRubric: string
{
    /**
     * The name of the organisation (s. 3, para. 1).
     */
    case Organisation = 'organisation';

    /**
     * Its Québec enterprise number, if it has one (s. 3, para. 1).
     */
    case EnterpriseNumber = 'enterprise_number';

    /**
     * The person to contact about the incident (s. 3, para. 2), or whom the people concerned reach to learn more
     * (s. 5, para. 6).
     */
    case Contact = 'contact';

    /**
     * The personal information concerned, or why it cannot be described (s. 3, para. 3; s. 5, para. 1).
     */
    case Information = 'information';

    /**
     * A brief description of the circumstances (s. 3, para. 4; s. 5, para. 2).
     */
    case Circumstances = 'circumstances';

    /**
     * The cause of the incident, if it is known (s. 3, para. 4).
     */
    case Cause = 'cause';

    /**
     * The date or period of the incident, or an approximation (s. 3, para. 5; s. 5, para. 3).
     */
    case Occurrence = 'occurrence';

    /**
     * The date or period the organisation became aware of it (s. 3, para. 6).
     */
    case Discovery = 'discovery';

    /**
     * The number of people concerned, or an approximation (s. 3, para. 7).
     */
    case Affected = 'affected';

    /**
     * How many of them reside in Québec, or an approximation (s. 3, para. 7).
     */
    case AffectedInQuebec = 'affected_in_quebec';

    /**
     * What leads to conclude that there is a risk of serious injury (s. 3, para. 8).
     */
    case RiskAssessment = 'risk_assessment';

    /**
     * The measures taken or planned to notify the people concerned, with the date or the time expected (s. 3, para. 9).
     */
    case SubjectNotification = 'subject_notification';

    /**
     * The measures taken or planned to reduce the risk of injury and to prevent a new incident (s. 3, para. 10; s. 5,
     * para. 4).
     */
    case Measures = 'measures';

    /**
     * That an authority outside Québec with similar functions was notified, if one was (s. 3, para. 11).
     */
    case OtherAuthority = 'other_authority';

    /**
     * The measures the organisation suggests to the person to reduce the risk or mitigate the injury (s. 5, para. 5).
     */
    case SuggestedMeasures = 'suggested_measures';

    /**
     * The paragraph of the recipient's section that asks for this rubric, or null when its notice does not contain it.
     */
    public function paragraph(NoticeRecipient $recipient): ?int
    {
        if (NoticeRecipient::Commission === $recipient) {
            return match ($this) {
                self::Organisation, self::EnterpriseNumber => 1,
                self::Contact => 2,
                self::Information => 3,
                self::Circumstances, self::Cause => 4,
                self::Occurrence => 5,
                self::Discovery => 6,
                self::Affected, self::AffectedInQuebec => 7,
                self::RiskAssessment => 8,
                self::SubjectNotification => 9,
                self::Measures => 10,
                self::OtherAuthority => 11,
                self::SuggestedMeasures => null,
            };
        }

        return match ($this) {
            self::Information => 1,
            self::Circumstances => 2,
            self::Occurrence => 3,
            self::Measures => 4,
            self::SuggestedMeasures => 5,
            self::Contact => 6,
            default => null,
        };
    }

    /**
     * The rubrics of the notice to the Commission a field of the register feeds, by the name its corrections keep:
     * a correction of the field after a notice is a change section 4 asks to send. The dates of the notice to the
     * Commission and of the closing feed none.
     *
     * @return list<self>
     */
    public static function fedBy(string $field): array
    {
        return match ($field) {
            'categories', 'information', 'undescribed_reason' => [self::Information],
            'summary' => [self::Circumstances],
            'occurred_at', 'occurred_until', 'occurrence_approximate' => [self::Occurrence],
            'discovered_at', 'discovered_until' => [self::Discovery],
            'affected', 'affected_approximate' => [self::Affected],
            'serious_risk', 'risk_assessment' => [self::RiskAssessment],
            'subjects_notified_at', 'public_notice_at', 'public_notice_reason' => [self::SubjectNotification],
            'measures' => [self::Measures],
            default => [],
        };
    }
}
