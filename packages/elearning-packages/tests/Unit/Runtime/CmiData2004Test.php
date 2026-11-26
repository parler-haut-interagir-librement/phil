<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Unit\Runtime;

use Phil\ElearningPackages\Runtime\CmiData;
use Phil\ElearningPackages\Runtime\CmiRules;
use Phil\ElearningPackages\Runtime\DataModel;
use Phil\ElearningPackages\Runtime\ElementDefinition;
use Phil\ElearningPackages\Runtime\ElementName;
use Phil\ElearningPackages\Runtime\RuntimeResult;
use Phil\ElearningPackages\Runtime\ScormError;
use Phil\ElearningPackages\Runtime\ScormVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CmiData::class)]
#[CoversClass(CmiRules::class)]
#[CoversClass(ElementName::class)]
#[CoversClass(RuntimeResult::class)]
#[CoversClass(DataModel::class)]
#[CoversClass(ElementDefinition::class)]
#[CoversClass(ScormError::class)]
final class CmiData2004Test extends TestCase
{
    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function reads(): iterable
    {
        yield 'the version' => ['cmi._version', '1.0', 0];
        yield 'the children of the score' => ['cmi.score._children', 'scaled,raw,min,max', 0];
        yield 'the children of the objectives' => ['cmi.objectives._children', 'id,score,success_status,completion_status,progress_measure,description', 0];
        yield 'the children of the interactions' => ['cmi.interactions._children', 'id,type,objectives,timestamp,correct_responses,weighting,learner_response,result,latency,description', 0];
        yield 'the children of the comments' => ['cmi.comments_from_learner._children', 'comment,location,timestamp', 0];
        yield 'the children of the comments of the platform' => ['cmi.comments_from_lms._children', 'comment,location,timestamp', 0];
        yield 'the children of the preferences' => ['cmi.learner_preference._children', 'audio_level,language,delivery_speed,audio_captioning', 0];
        yield 'a location never set' => ['cmi.location', '', 403];
        yield 'suspend data never set' => ['cmi.suspend_data', '', 403];
        yield 'a score never set' => ['cmi.score.scaled', '', 403];
        yield 'a completion never set' => ['cmi.completion_status', 'unknown', 0];
        yield 'a success never set' => ['cmi.success_status', 'unknown', 0];
        yield 'the total time' => ['cmi.total_time', 'PT0H0M0S', 0];
        yield 'the time limit action' => ['cmi.time_limit_action', 'continue,no message', 0];
        yield 'an audio level' => ['cmi.learner_preference.audio_level', '1', 0];
        yield 'a navigation request' => ['adl.nav.request', '_none_', 0];
        yield 'a valid continue' => ['adl.nav.request_valid.continue', 'unknown', 0];
        yield 'a valid choice' => ['adl.nav.request_valid.choice.{target=intro.page}', 'unknown', 0];
        yield 'an exit' => ['cmi.exit', '', 405];
        yield 'a session time' => ['cmi.session_time', '', 405];
        yield 'an interaction past the count' => ['cmi.interactions.0.id', '', 301];
        yield 'an element the data model lacks' => ['cmi.mood', '', 401];
        yield 'nothing' => ['', '', 301];
        yield 'children of an element' => ['cmi.location._children', '', 301];
        yield 'a count of a non collection' => ['cmi.score._count', '', 301];
        yield 'the count of the comments' => ['cmi.comments_from_lms._count', '0', 0];
    }

    #[DataProvider('reads')]
    public function testAnElementReadsAsScorm2004Answers(string $element, string $value, int $error): void
    {
        $result = new CmiData(ScormVersion::Scorm2004)->get($element);

        self::assertSame([$value, $error], [$result->value, $result->error]);
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function writes(): iterable
    {
        yield 'a completion' => ['cmi.completion_status', 'completed', 0];
        yield 'not attempted' => ['cmi.completion_status', 'not attempted', 0];
        yield 'a completion of 1.2' => ['cmi.completion_status', 'passed', 406];
        yield 'a success' => ['cmi.success_status', 'failed', 0];
        yield 'a scaled score' => ['cmi.score.scaled', '-1', 0];
        yield 'a scaled score over one' => ['cmi.score.scaled', '1.0000001', 407];
        yield 'a raw score over a hundred' => ['cmi.score.raw', '250', 0];
        yield 'a progress measure' => ['cmi.progress_measure', '0.5', 0];
        yield 'a progress measure below zero' => ['cmi.progress_measure', '-0.1', 407];
        yield 'an audio level' => ['cmi.learner_preference.audio_level', '7.5', 0];
        yield 'a negative audio level' => ['cmi.learner_preference.audio_level', '-1', 407];
        yield 'a language' => ['cmi.learner_preference.language', 'fr-CA', 0];
        yield 'an empty language' => ['cmi.learner_preference.language', '', 0];
        yield 'a language that is not one' => ['cmi.learner_preference.language', 'français', 406];
        yield 'a captioning' => ['cmi.learner_preference.audio_captioning', '-1', 0];
        yield 'a captioning of two' => ['cmi.learner_preference.audio_captioning', '2', 406];
        yield 'an exit' => ['cmi.exit', 'normal', 0];
        yield 'a session time' => ['cmi.session_time', 'PT1H30M5.25S', 0];
        yield 'a session time in days' => ['cmi.session_time', 'P1DT2H', 0];
        yield 'a session time of nothing' => ['cmi.session_time', 'PT', 406];
        yield 'a session time of 1.2' => ['cmi.session_time', '0001:00:00', 406];
        yield 'suspend data' => ['cmi.suspend_data', str_repeat('x', 64000), 0];
        yield 'too much suspend data' => ['cmi.suspend_data', str_repeat('x', 64001), 406];
        yield 'a location' => ['cmi.location', str_repeat('x', 1000), 0];
        yield 'a long location' => ['cmi.location', str_repeat('x', 1001), 406];
        yield 'a learner id' => ['cmi.learner_id', 'x', 404];
        yield 'a keyword' => ['cmi.score._children', 'x', 404];
        yield 'the version' => ['cmi._version', '2', 404];
        yield 'nothing' => ['', 'x', 351];
        yield 'an element the data model lacks' => ['cmi.mood', 'x', 401];
        yield 'an objective' => ['cmi.objectives.0.id', 'urn:example:objective-1', 0];
        yield 'an identifier with a space' => ['cmi.objectives.0.id', 'objective 1', 406];
        yield 'an urn without a namespace' => ['cmi.objectives.0.id', 'urn::x', 406];
        yield 'an objective before its identifier' => ['cmi.objectives.0.success_status', 'passed', 408];
        yield 'an interaction before its identifier' => ['cmi.interactions.0.type', 'choice', 408];
        yield 'an objective of an interaction before it' => ['cmi.interactions.0.objectives.0.id', 'o1', 408];
        yield 'an objective past the next' => ['cmi.objectives.1.id', 'o2', 351];
        yield 'a comment' => ['cmi.comments_from_learner.0.comment', '{lang=fr}Très clair', 0];
        yield 'a comment in no language' => ['cmi.comments_from_learner.0.comment', '{lang=}Clear', 406];
        yield 'a comment timestamp' => ['cmi.comments_from_learner.0.timestamp', '2026-11-18T10:15:30.5-05:00', 0];
        yield 'a timestamp in 2039' => ['cmi.comments_from_learner.0.timestamp', '2039-01-01', 406];
        yield 'a comment of the platform' => ['cmi.comments_from_lms.0.comment', 'x', 404];
        yield 'a navigation request' => ['adl.nav.request', 'continue', 0];
        yield 'a choice' => ['adl.nav.request', '{target=intro}choice', 0];
        yield 'a choice without a target' => ['adl.nav.request', 'choice', 406];
        yield 'a valid choice' => ['adl.nav.request_valid.choice.{target=intro}', 'true', 404];
    }

    #[DataProvider('writes')]
    public function testAnElementIsWrittenAsScorm2004Answers(string $element, string $value, int $error): void
    {
        self::assertSame($error, new CmiData(ScormVersion::Scorm2004)->set($element, $value)->error);
    }

    /**
     * @return iterable<string, array{string, string, string, int}>
     */
    public static function responses(): iterable
    {
        yield 'true' => ['true-false', 'learner_response', 'true', 0];
        yield 'a true-false of 1.2' => ['true-false', 'learner_response', 't', 406];
        yield 'choices' => ['choice', 'learner_response', 'a[,]b[,]c', 0];
        yield 'no choice' => ['choice', 'learner_response', '', 0];
        yield 'a choice left empty' => ['choice', 'learner_response', 'a[,]', 406];
        yield 'a sequence' => ['sequencing', 'correct_responses.0.pattern', 'a[,]b', 0];
        yield 'an empty sequence' => ['sequencing', 'correct_responses.0.pattern', '', 406];
        yield 'a likert' => ['likert', 'learner_response', 'agree', 0];
        yield 'two likerts' => ['likert', 'learner_response', 'agree[,]disagree', 406];
        yield 'a matching' => ['matching', 'correct_responses.0.pattern', 'a[.]1[,]b[.]2', 0];
        yield 'a half matching' => ['matching', 'correct_responses.0.pattern', 'a[.]1[,]b', 406];
        yield 'a number' => ['numeric', 'learner_response', '3.5', 0];
        yield 'a range as a response' => ['numeric', 'learner_response', '1[:]5', 406];
        yield 'a range' => ['numeric', 'correct_responses.0.pattern', '1[:]5', 0];
        yield 'an open range' => ['numeric', 'correct_responses.0.pattern', '[:]5', 0];
        yield 'a number as a range' => ['numeric', 'correct_responses.0.pattern', '5', 406];
        yield 'a fill-in' => ['fill-in', 'learner_response', 'any words at all', 0];
        yield 'an empty fill-in' => ['fill-in', 'learner_response', '', 0];
        yield 'fill-ins in their languages' => ['fill-in', 'learner_response', '{lang=fr}chat[,]{lang=en-CA}cat', 0];
        yield 'a fill-in of no language' => ['fill-in', 'learner_response', '{lang=}cat', 406];
        yield 'a fill-in of a malformed language' => ['fill-in', 'learner_response', '{lang=12}cat', 406];
        yield 'ten fill-ins' => ['fill-in', 'learner_response', 'a[,]b[,]c[,]d[,]e[,]f[,]g[,]h[,]i[,]j', 0];
        yield 'eleven fill-ins' => ['fill-in', 'learner_response', 'a[,]b[,]c[,]d[,]e[,]f[,]g[,]h[,]i[,]j[,]k', 406];
        yield 'a fill-in of 250 characters' => ['fill-in', 'learner_response', str_repeat('é', 250), 0];
        yield 'a fill-in of 251 characters' => ['fill-in', 'learner_response', str_repeat('é', 251), 406];
        yield 'a second fill-in of 251 characters' => ['fill-in', 'learner_response', 'a[,]'.str_repeat('b', 251), 406];
        yield 'a case and an order' => ['fill-in', 'correct_responses.0.pattern', '{case_matters=true}{order_matters=false}a[,]b', 0];
        yield 'an order and a case' => ['fill-in', 'correct_responses.0.pattern', '{order_matters=true}{case_matters=false}a', 0];
        yield 'an order alone' => ['fill-in', 'correct_responses.0.pattern', '{order_matters=true}a', 0];
        yield 'a case alone' => ['fill-in', 'correct_responses.0.pattern', '{case_matters=false}{lang=fr}a', 0];
        yield 'a case twice' => ['fill-in', 'correct_responses.0.pattern', '{case_matters=true}{case_matters=true}a', 406];
        yield 'an order twice' => ['fill-in', 'correct_responses.0.pattern', '{order_matters=true}{case_matters=true}{order_matters=true}a', 406];
        yield 'a case of no boolean' => ['fill-in', 'correct_responses.0.pattern', '{case_matters=yes}a', 406];
        yield 'eleven fill-in patterns' => ['fill-in', 'correct_responses.0.pattern', 'a[,]b[,]c[,]d[,]e[,]f[,]g[,]h[,]i[,]j[,]k', 406];
        yield 'a case in a response is text' => ['fill-in', 'learner_response', '{case_matters=true}a', 0];
        yield 'a long fill-in' => ['long-fill-in', 'learner_response', str_repeat('word[,] ', 500), 0];
        yield 'a long fill-in in its language' => ['long-fill-in', 'learner_response', '{lang=en}text', 0];
        yield 'a long fill-in of a malformed language' => ['long-fill-in', 'learner_response', '{lang=en_CA}text', 406];
        yield 'a long fill-in whose case matters' => ['long-fill-in', 'correct_responses.0.pattern', '{case_matters=true}{lang=en}text', 0];
        yield 'a long fill-in whose case matters twice' => ['long-fill-in', 'correct_responses.0.pattern', '{case_matters=true}{case_matters=true}text', 406];
        yield 'a long fill-in of no boolean' => ['long-fill-in', 'correct_responses.0.pattern', '{case_matters=no}text', 406];
        yield 'a performance' => ['performance', 'correct_responses.0.pattern', 'step[.]1', 0];
        yield 'a performance of a range' => ['performance', 'correct_responses.0.pattern', 'step[.]1[:]5[,]other[.][:]2', 0];
        yield 'a performance of a malformed range' => ['performance', 'correct_responses.0.pattern', 'step[.]a[:]b', 406];
        yield 'a performance of an answer alone' => ['performance', 'correct_responses.0.pattern', '[.]answer', 0];
        yield 'a performance of a step alone' => ['performance', 'correct_responses.0.pattern', 'step[.]', 0];
        yield 'a performance of neither' => ['performance', 'correct_responses.0.pattern', '[.]', 406];
        yield 'a second performance of neither' => ['performance', 'correct_responses.0.pattern', 'a[.]1[,][.]', 406];
        yield 'a performance without its delimiter' => ['performance', 'correct_responses.0.pattern', 'step', 406];
        yield 'a performance of a spaced step' => ['performance', 'correct_responses.0.pattern', 'a step[.]1', 406];
        yield 'a performance whose order matters' => ['performance', 'correct_responses.0.pattern', '{order_matters=false}a[.]1[,]b[.]2', 0];
        yield 'a performance whose order matters twice' => ['performance', 'correct_responses.0.pattern', '{order_matters=false}{order_matters=false}a[.]1', 406];
        yield 'a performance of 125 steps' => ['performance', 'correct_responses.0.pattern', implode('[,]', array_fill(0, 125, 's[.]1')), 0];
        yield 'a performance of 126 steps' => ['performance', 'correct_responses.0.pattern', implode('[,]', array_fill(0, 126, 's[.]1')), 406];
        yield 'a performance of an answer of 251 characters' => ['performance', 'correct_responses.0.pattern', 's[.]'.str_repeat('x', 251), 406];
        yield 'a performance response' => ['performance', 'learner_response', 'step[.]1[,][.]free text', 0];
        yield 'a performance response of a range' => ['performance', 'learner_response', 'step[.]1[:]5', 406];
        yield 'an empty performance response' => ['performance', 'learner_response', '', 406];
        yield 'a performance response of 250 steps' => ['performance', 'learner_response', implode('[,]', array_fill(0, 250, 's[.]1')), 0];
        yield 'a performance response of 251 steps' => ['performance', 'learner_response', implode('[,]', array_fill(0, 251, 's[.]1')), 406];
        yield 'any other response' => ['other', 'learner_response', '{lang=}[,][.]', 0];
        yield 'a line after a true' => ['true-false', 'learner_response', "true\n", 406];
        yield 'a second true-false pattern' => ['true-false', 'correct_responses.1.pattern', 'true', 351];
        yield 'a second choice pattern' => ['choice', 'correct_responses.1.pattern', 'b', 0];
    }

    #[DataProvider('responses')]
    public function testAResponseIsCheckedAgainstTheTypeOfItsInteraction(string $type, string $element, string $value, int $error): void
    {
        $data = new CmiData(ScormVersion::Scorm2004);
        $data->set('cmi.interactions.0.id', 'q1');
        $data->set('cmi.interactions.0.type', $type);

        if (str_ends_with($element, '1.pattern')) {
            $data->set('cmi.interactions.0.correct_responses.0.pattern', 'choice' === $type ? 'a' : 'true');
        }

        self::assertSame($error, $data->set('cmi.interactions.0.'.$element, $value)->error);
    }

    public function testATargetGivenNoValueReadsTheValueOfItsRequest(): void
    {
        $data = new CmiData(ScormVersion::Scorm2004, ['adl.nav.request_valid.choice' => 'false', 'adl.nav.request_valid.choice.{target=intro}' => 'true']);

        self::assertSame('true', $data->get('adl.nav.request_valid.choice.{target=intro}')->value);
        self::assertSame('false', $data->get('adl.nav.request_valid.choice.{target=outro}')->value);
        self::assertSame('unknown', $data->get('adl.nav.request_valid.jump.{target=intro}')->value);
        self::assertSame('unknown', new CmiData(ScormVersion::Scorm2004)->get('adl.nav.request_valid.choice.{target=intro}')->value);
    }

    public function testAResponseNeedsTheTypeOfItsInteraction(): void
    {
        $data = new CmiData(ScormVersion::Scorm2004);
        $data->set('cmi.interactions.0.id', 'q1');

        self::assertSame(ScormError::DEPENDENCY_NOT_ESTABLISHED, $data->set('cmi.interactions.0.learner_response', 'true')->error);
        self::assertSame(ScormError::DEPENDENCY_NOT_ESTABLISHED, $data->set('cmi.interactions.0.correct_responses.0.pattern', 'true')->error);
        self::assertSame('The type of the interaction is set first.', $data->set('cmi.interactions.0.learner_response', 'true')->diagnostic);
        self::assertSame(0, $data->set('cmi.interactions.0.weighting', '1')->error);
        self::assertSame('1', $data->get('cmi.interactions.0.weighting')->value);
        self::assertSame(ScormError::NOT_INITIALIZED_VALUE, $data->get('cmi.interactions.0.result')->error);
    }

    public function testTheIdentifiersOfObjectivesAreUniqueAndNeverChange(): void
    {
        $data = new CmiData(ScormVersion::Scorm2004);

        self::assertSame(0, $data->set('cmi.objectives.0.id', 'o1')->error);
        self::assertSame(0, $data->set('cmi.objectives.0.id', 'o1')->error);
        self::assertSame(ScormError::GENERAL_SET, $data->set('cmi.objectives.0.id', 'o2')->error);
        self::assertSame(0, $data->set('cmi.objectives.1.id', 'o2')->error);
        self::assertSame(ScormError::GENERAL_SET, $data->set('cmi.objectives.2.id', 'o1')->error);
        self::assertSame(ScormError::GENERAL_SET, $data->set('cmi.objectives.2.id', 'o2')->error);
        self::assertSame(0, $data->set('cmi.objectives.2.id', 'o3')->error);
        self::assertSame(0, $data->set('cmi.objectives.1.success_status', 'passed')->error);
        self::assertSame('3', $data->get('cmi.objectives._count')->value);

        $data->set('cmi.interactions.0.id', 'q1');

        self::assertSame(0, $data->set('cmi.interactions.0.objectives.0.id', 'o1')->error);
        self::assertSame(0, $data->set('cmi.interactions.0.objectives.0.id', 'o1')->error);
        self::assertSame(ScormError::GENERAL_SET, $data->set('cmi.interactions.0.objectives.1.id', 'o1')->error);
        self::assertSame(0, $data->set('cmi.interactions.0.objectives.1.id', 'o2')->error);
        self::assertSame(0, $data->set('cmi.interactions.0.objectives.0.id', 'o3')->error);
        self::assertSame('2', $data->get('cmi.interactions.0.objectives._count')->value);
        self::assertSame(0, $data->set('cmi.interactions.1.id', 'q2')->error);
        self::assertSame(0, $data->set('cmi.interactions.1.objectives.0.id', 'o1')->error);
    }

    /**
     * @return iterable<string, array{array<string, string>, string, string}>
     */
    public static function evaluations(): iterable
    {
        yield 'a completion as written' => [['cmi.completion_status' => 'incomplete'], 'incomplete', 'unknown'];
        yield 'a threshold reached' => [['cmi.completion_threshold' => '0.8', 'cmi.progress_measure' => '0.8', 'cmi.completion_status' => 'incomplete'], 'completed', 'unknown'];
        yield 'a threshold missed' => [['cmi.completion_threshold' => '0.8', 'cmi.progress_measure' => '0.79', 'cmi.completion_status' => 'completed'], 'incomplete', 'unknown'];
        yield 'a threshold without progress' => [['cmi.completion_threshold' => '0.8', 'cmi.completion_status' => 'completed'], 'unknown', 'unknown'];
        yield 'a success as written' => [['cmi.success_status' => 'passed'], 'unknown', 'passed'];
        yield 'a passing score reached' => [['cmi.scaled_passing_score' => '0.7', 'cmi.score.scaled' => '0.7', 'cmi.success_status' => 'failed'], 'unknown', 'passed'];
        yield 'a passing score missed' => [['cmi.scaled_passing_score' => '0.7', 'cmi.score.scaled' => '0.69', 'cmi.success_status' => 'passed'], 'unknown', 'failed'];
        yield 'a passing score without a score' => [['cmi.scaled_passing_score' => '0.7', 'cmi.success_status' => 'passed'], 'unknown', 'unknown'];
    }

    /**
     * @param array<string, string> $values
     */
    #[DataProvider('evaluations')]
    public function testTheStatusesAreEvaluatedAgainstTheValuesOfThePackage(array $values, string $completion, string $success): void
    {
        $data = new CmiData(ScormVersion::Scorm2004, $values);

        self::assertSame($completion, $data->get('cmi.completion_status')->value);
        self::assertSame($success, $data->get('cmi.success_status')->value);
        self::assertSame([$completion, $success], [$data->status()->completion, $data->status()->success]);
    }

    public function testTheScoreIsScaledFromWhatTheContentReports(): void
    {
        self::assertSame(0.25, new CmiData(ScormVersion::Scorm2004, ['cmi.score.scaled' => '0.25', 'cmi.score.raw' => '90'])->scaledScore());
        self::assertSame(0.5, new CmiData(ScormVersion::Scorm2004, ['cmi.score.raw' => '5', 'cmi.score.min' => '0', 'cmi.score.max' => '10'])->scaledScore());
        self::assertNull(new CmiData(ScormVersion::Scorm2004, ['cmi.score.raw' => '50'])->scaledScore());
    }
}
