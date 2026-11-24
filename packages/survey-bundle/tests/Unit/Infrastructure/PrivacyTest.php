<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Tests\Unit\Infrastructure;

use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\SurveyBundle\Domain\ValueObject\Questionnaire;
use Phil\SurveyBundle\Infrastructure\Privacy\SurveyPrivacy;
use Phil\SurveyBundle\Tests\Fixtures\Samples;
use Phil\SurveyBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SurveyPrivacy::class)]
final class PrivacyTest extends TestCase
{
    public function testTheResponsesThatNameAPersonAreExportedAndErasedAndTheAnonymousOnesAreNot(): void
    {
        $stage = new Stage();
        $privacy = new SurveyPrivacy($stage->responses, $stage->participations);
        $signed = Questionnaire::fromArray(Samples::colour(['key' => 'signed', 'anonymity' => 'identified', 'addenda' => true]));
        $anonymous = Questionnaire::fromArray(Samples::colour(['key' => 'anonymous']));
        $receipt = $stage->submissions->submit($signed, ['colour' => 'red'], 'member:ann');
        $stage->addenda->add($signed, (string) $receipt->addendumToken, 'More');
        $stage->submissions->submit($anonymous, ['colour' => 'blue'], 'member:ann');
        $stage->submissions->submit($signed, ['colour' => 'blue'], 'member:bob');

        $ann = DataSubject::fromString('member:ann');

        self::assertSame('survey', $privacy->module()->value);
        self::assertSame([
            'responses' => [[
                'id' => $receipt->response,
                'questionnaire' => 'signed',
                'subject' => null,
                'submitted_at' => '2026-11-21T19:30:00+00:00',
                'ending' => 'thanks',
                'answers' => ['colour' => 'red'],
                'computed' => [],
                'addenda' => [['text' => 'More', 'added_at' => '2026-11-21T19:30:00+00:00']],
            ]],
            'participations' => [['questionnaire' => 'anonymous', 'subject' => null], ['questionnaire' => 'signed', 'subject' => null]],
        ], $privacy->export($ann));
        self::assertSame([], $privacy->export(DataSubject::fromString('member:nobody')));

        $report = $privacy->erase($ann);
        self::assertSame(['phil_survey_responses' => ErasureEffect::Deleted, 'phil_survey_participations' => ErasureEffect::Deleted], $report->effects);
        self::assertSame([], $privacy->export($ann));
        self::assertSame(1, $stage->responses->count('anonymous'));
        self::assertSame(1, $stage->responses->count('signed'));
        self::assertSame(0, $stage->rows('phil_survey_addenda'));
        self::assertTrue($stage->submissions->hasAnswered($signed, 'member:bob'));
    }

    public function testAParticipationAboutASubjectNamesIt(): void
    {
        $stage = new Stage();
        $stage->connection->insert('phil_survey_participations', ['questionnaire' => 'talks', 'subject' => 'session:1', 'respondent_digest' => $stage->participations->digest('member:ann')]);

        self::assertSame(['participations' => [['questionnaire' => 'talks', 'subject' => 'session:1']]], new SurveyPrivacy($stage->responses, $stage->participations)->export(DataSubject::fromString('member:ann')));
    }
}
