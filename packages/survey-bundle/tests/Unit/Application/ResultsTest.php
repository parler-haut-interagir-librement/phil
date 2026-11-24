<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Tests\Unit\Application;

use Phil\SurveyBundle\Application\Exception\SurveyRefused;
use Phil\SurveyBundle\Application\QuestionResult;
use Phil\SurveyBundle\Application\Results;
use Phil\SurveyBundle\Application\ResultsReport;
use Phil\SurveyBundle\Domain\ValueObject\Questionnaire;
use Phil\SurveyBundle\Tests\Fixtures\Samples;
use Phil\SurveyBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Results::class)]
#[CoversClass(ResultsReport::class)]
#[CoversClass(QuestionResult::class)]
final class ResultsTest extends TestCase
{
    private Stage $stage;

    protected function setUp(): void
    {
        $this->stage = new Stage();
    }

    public function testNothingIsShownBelowTheMinimum(): void
    {
        $questionnaire = Samples::questionnaire(['results' => ['minimum' => 3]]);
        $this->answer($questionnaire, 2);

        $report = $this->stage->results->of($questionnaire);
        self::assertTrue($report->withheld);
        self::assertSame(2, $report->responses);
        self::assertSame(3, $report->minimum);
        self::assertSame([], $report->questions);

        $this->answer($questionnaire, 1);
        self::assertFalse($this->stage->results->of($questionnaire)->withheld);
    }

    public function testAQuestionFewerAnsweredIsWithheldAlone(): void
    {
        $questionnaire = Samples::questionnaire(['results' => ['minimum' => 2]]);
        $this->stage->submissions->submit($questionnaire, ['age' => 'adult', 'fun' => '5', 'rules' => '1']);
        $this->stage->submissions->submit($questionnaire, ['age' => 'adult', 'fun' => '3']);
        $this->stage->submissions->submit($questionnaire, ['age' => 'minor']);

        $byName = $this->byName($this->stage->results->of($questionnaire));

        self::assertFalse($byName['age']->withheld);
        self::assertSame(3, $byName['age']->answered);
        self::assertSame(2, $byName['age']->result?->standingOf('adult')?->score);
        self::assertSame(1, $byName['age']->result->standingOf('minor')?->score);
        self::assertFalse($byName['fun']->withheld);
        self::assertSame(2, $byName['fun']->answered);
        self::assertSame(4.0, $byName['fun']->result?->rating?->mean());
        self::assertTrue($byName['rules']->withheld);
        self::assertSame(1, $byName['rules']->answered);
        self::assertNull($byName['rules']->result);
        self::assertTrue($byName['grid']->withheld);
        self::assertSame(0, $byName['grid']->answered);
        self::assertArrayNotHasKey('email', $byName);
        self::assertArrayNotHasKey('comment', $byName);
        self::assertArrayNotHasKey('nickname', $byName);
    }

    public function testEveryCountedTypeIsCounted(): void
    {
        $questionnaire = Samples::questionnaire(['results' => ['minimum' => 1]]);
        $this->stage->submissions->submit($questionnaire, ['age' => 'adult', 'fun' => '4', 'genres' => ['horror', 'scifi'], 'order' => ['b', 'a', 'c'], 'grid' => ['food' => 'good', 'room' => 'bad'], 'again' => '1', 'recommend' => '10']);
        $this->stage->submissions->submit($questionnaire, ['age' => 'adult', 'fun' => '2', 'genres' => ['horror'], 'order' => ['a', 'b'], 'grid' => ['food' => 'good', 'room' => 'good'], 'recommend' => '3']);

        $byName = $this->byName($this->stage->results->of($questionnaire));

        foreach (['fun' => 'scale', 'genres' => 'choices', 'order' => 'borda', 'again' => 'choices', 'recommend' => 'nps'] as $name => $method) {
            self::assertSame($method, $byName[$name]->result?->method, $name);
        }

        self::assertSame(2, $byName['genres']->result?->standingOf('horror')?->score);
        self::assertSame(1, $byName['genres']->result->standingOf('scifi')?->score);
        self::assertSame(['a', 'b'], $byName['order']->result?->winners());
        self::assertSame(1, $byName['again']->result?->standingOf('yes')?->score);
        self::assertSame(1, $byName['again']->result->standingOf('no')?->score);
        self::assertSame(0.0, $byName['recommend']->result?->netPromoter?->score());
        self::assertNull($byName['grid']->result);
        self::assertSame(2, $byName['grid']->answered);
        self::assertSame(2, $byName['grid']->rows['food']->standingOf('good')?->score);
        self::assertSame(1, $byName['grid']->rows['room']->standingOf('bad')?->score);
        self::assertSame(['region', 'genres', 'order', 'again', 'recommend', 'age', 'fun', 'rules', 'grid'], array_values(array_intersect(['region', 'genres', 'order', 'again', 'recommend', 'age', 'fun', 'rules', 'grid'], array_keys($byName))));
    }

    public function testTheResultsOfASubjectCountItsResponsesOnly(): void
    {
        $questionnaire = Questionnaire::fromArray(Samples::colour(['results' => ['minimum' => 1]]));
        $this->stage->connection->insert('phil_survey_responses', ['id' => 'x1', 'questionnaire' => 'colour', 'subject' => 'session:1', 'ending' => 'thanks', 'answers' => '{"colour":"red"}', 'computed' => '[]', 'submitted_at' => '2026-11-21 00:00:00']);
        $this->stage->connection->insert('phil_survey_responses', ['id' => 'x2', 'questionnaire' => 'colour', 'subject' => 'session:2', 'ending' => 'thanks', 'answers' => '{"colour":"blue"}', 'computed' => '[]', 'submitted_at' => '2026-11-21 00:00:00']);

        $report = $this->stage->results->of($questionnaire, 'session:1');
        self::assertSame(1, $report->responses);
        self::assertSame(['red'], $report->questions[0]->result?->winners());
        self::assertSame(2, $this->stage->results->of($questionnaire)->responses);
    }

    public function testAnAnswerThatNoLongerFitsIsLeftOut(): void
    {
        $questionnaire = Samples::questionnaire(['results' => ['minimum' => 1]]);

        foreach (['{"age":"elder","genres":["horror","horror"],"order":"a","recommend":11,"grid":"bad"}', '{"age":"adult","genres":["horror",3],"order":["a","b","c","d"]}', '{"age":"adult","genres":[]}', '{"age":2.5}'] as $at => $answers) {
            $this->stage->connection->insert('phil_survey_responses', ['id' => 'x'.$at, 'questionnaire' => 'games', 'ending' => 'thanks', 'answers' => $answers, 'computed' => '[]', 'submitted_at' => '2026-11-21 00:00:00']);
        }

        $byName = $this->byName($this->stage->results->of($questionnaire));

        self::assertSame(2, $byName['age']->answered);
        self::assertSame(1, $byName['order']->answered);
        self::assertSame(['a'], $byName['order']->result?->winners());
        self::assertSame(0, $byName['genres']->answered);
        self::assertSame(0, $byName['recommend']->answered);
        self::assertSame(0, $byName['grid']->answered);
    }

    public function testTheResultsArePublicOnlyWhenTheQuestionnaireSaysSo(): void
    {
        $public = Samples::questionnaire(['results' => ['public' => true, 'minimum' => 1]]);
        $this->answer($public, 1);
        self::assertSame(1, $this->stage->results->public($public)->responses);

        $this->expectExceptionObject(SurveyRefused::resultsPrivate());
        $this->stage->results->public(Samples::questionnaire());
    }

    private function answer(Questionnaire $questionnaire, int $times): void
    {
        for ($at = 0; $at < $times; ++$at) {
            $this->stage->submissions->submit($questionnaire, ['age' => 'minor']);
        }
    }

    /**
     * @return array<string, QuestionResult>
     */
    private function byName(ResultsReport $report): array
    {
        $byName = [];

        foreach ($report->questions as $result) {
            $byName[$result->question->name] = $result;
        }

        return $byName;
    }
}
