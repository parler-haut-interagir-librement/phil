<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Incident;

use Phil\PrivacyBundle\Domain\Incident\NoticeRecipient;
use Phil\PrivacyBundle\Domain\Incident\NoticeRubric;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NoticeRubric::class)]
#[CoversClass(NoticeRecipient::class)]
final class NoticeRubricTest extends TestCase
{
    public function testTheNoticeToTheCommissionHasTheElevenParagraphsOfSection3(): void
    {
        $paragraphs = [];

        foreach (NoticeRecipient::Commission->rubrics() as $rubric) {
            $paragraphs[$rubric->value] = $rubric->paragraph(NoticeRecipient::Commission);
        }

        self::assertSame([
            'organisation' => 1,
            'enterprise_number' => 1,
            'contact' => 2,
            'information' => 3,
            'circumstances' => 4,
            'cause' => 4,
            'occurrence' => 5,
            'discovery' => 6,
            'affected' => 7,
            'affected_in_quebec' => 7,
            'risk_assessment' => 8,
            'subject_notification' => 9,
            'measures' => 10,
            'other_authority' => 11,
        ], $paragraphs);
        self::assertSame(3, NoticeRecipient::Commission->section());
        self::assertNull(NoticeRubric::SuggestedMeasures->paragraph(NoticeRecipient::Commission));
    }

    public function testTheNoticeToThePeopleHasTheSixParagraphsOfSection5WhetherSentOrPublic(): void
    {
        foreach ([NoticeRecipient::Subject, NoticeRecipient::Public] as $recipient) {
            $paragraphs = [];

            foreach ($recipient->rubrics() as $rubric) {
                $paragraphs[$rubric->value] = $rubric->paragraph($recipient);
            }

            self::assertSame(['contact' => 6, 'information' => 1, 'circumstances' => 2, 'occurrence' => 3, 'measures' => 4, 'suggested_measures' => 5], $paragraphs);
            self::assertSame(5, $recipient->section());
        }

        foreach ([NoticeRubric::Organisation, NoticeRubric::EnterpriseNumber, NoticeRubric::Cause, NoticeRubric::Discovery, NoticeRubric::Affected, NoticeRubric::AffectedInQuebec, NoticeRubric::RiskAssessment, NoticeRubric::SubjectNotification, NoticeRubric::OtherAuthority] as $rubric) {
            self::assertNull($rubric->paragraph(NoticeRecipient::Subject), $rubric->value);
        }
    }

    public function testEachFieldOfTheRegisterFeedsItsRubric(): void
    {
        $fed = [];

        foreach (['categories', 'information', 'undescribed_reason', 'summary', 'occurred_at', 'occurred_until', 'occurrence_approximate', 'discovered_at', 'discovered_until', 'affected', 'affected_approximate', 'serious_risk', 'risk_assessment', 'subjects_notified_at', 'public_notice_at', 'public_notice_reason', 'measures', 'authority_notified_at', 'closed_at', 'unknown'] as $field) {
            $fed[$field] = array_map(static fn (NoticeRubric $rubric): string => $rubric->value, NoticeRubric::fedBy($field));
        }

        self::assertSame([
            'categories' => ['information'],
            'information' => ['information'],
            'undescribed_reason' => ['information'],
            'summary' => ['circumstances'],
            'occurred_at' => ['occurrence'],
            'occurred_until' => ['occurrence'],
            'occurrence_approximate' => ['occurrence'],
            'discovered_at' => ['discovery'],
            'discovered_until' => ['discovery'],
            'affected' => ['affected'],
            'affected_approximate' => ['affected'],
            'serious_risk' => ['risk_assessment'],
            'risk_assessment' => ['risk_assessment'],
            'subjects_notified_at' => ['subject_notification'],
            'public_notice_at' => ['subject_notification'],
            'public_notice_reason' => ['subject_notification'],
            'measures' => ['measures'],
            'authority_notified_at' => [],
            'closed_at' => [],
            'unknown' => [],
        ], $fed);
    }
}
