<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Unit\Runtime;

use Phil\ElearningPackages\Exception\RuntimeRefused;
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
final class CmiData12Test extends TestCase
{
    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function reads(): iterable
    {
        yield 'the version' => ['cmi._version', '3.4', 0];
        yield 'the children of the core' => ['cmi.core._children', 'student_id,student_name,lesson_location,credit,lesson_status,entry,score,total_time,lesson_mode,exit,session_time', 0];
        yield 'the children of the score' => ['cmi.core.score._children', 'raw,min,max', 0];
        yield 'the children of the interactions' => ['cmi.interactions._children', 'id,objectives,time,type,correct_responses,weighting,student_response,result,latency', 0];
        yield 'the children of the objectives' => ['cmi.objectives._children', 'id,score,status', 0];
        yield 'the children of the student data' => ['cmi.student_data._children', 'mastery_score,max_time_allowed,time_limit_action', 0];
        yield 'the children of the preferences' => ['cmi.student_preference._children', 'audio,language,speed,text', 0];
        yield 'a status never set' => ['cmi.core.lesson_status', 'not attempted', 0];
        yield 'a location never set' => ['cmi.core.lesson_location', '', 0];
        yield 'a raw score never set' => ['cmi.core.score.raw', '', 0];
        yield 'an element without a default' => ['cmi.core.exit', '', 404];
        yield 'the total time' => ['cmi.core.total_time', '0000:00:00.00', 0];
        yield 'the credit' => ['cmi.core.credit', 'credit', 0];
        yield 'the mode' => ['cmi.core.lesson_mode', 'normal', 0];
        yield 'an audio preference' => ['cmi.student_preference.audio', '0', 0];
        yield 'a count' => ['cmi.interactions._count', '0', 0];
        yield 'the count of the objectives' => ['cmi.objectives._count', '0', 0];
        yield 'a session time' => ['cmi.core.session_time', '', 404];
        yield 'an interaction, write only' => ['cmi.interactions.0.id', '', 404];
        yield 'an objective past the count' => ['cmi.objectives.0.id', '', 201];
        yield 'the count of an interaction past the count' => ['cmi.interactions.0.objectives._count', '', 201];
        yield 'an element the data model lacks' => ['cmi.core.mood', '', 401];
        yield 'a parent' => ['cmi.core', '', 401];
        yield 'a misspelt keyword' => ['cmi.core._childs', '', 401];
        yield 'children of an element' => ['cmi.core.lesson_status._children', '', 202];
        yield 'children of nothing' => ['cmi.nothing._children', '', 401];
        yield 'a count of a non collection' => ['cmi.core._count', '', 203];
        yield 'a count of nothing' => ['cmi.nothing._count', '', 401];
        yield 'the version of a part' => ['cmi.core._version', '', 401];
        yield 'nothing' => ['', '', 201];
        yield 'an upper case name' => ['cmi.Core.lesson_status', '', 401];
        yield 'a leading zero' => ['cmi.interactions.00.id', '', 401];
        yield 'an empty segment' => ['cmi..core', '', 401];
        yield 'a keyword in the middle' => ['cmi._count.core', '', 401];
    }

    #[DataProvider('reads')]
    public function testAnElementReadsAsScorm12Answers(string $element, string $value, int $error): void
    {
        $result = new CmiData(ScormVersion::Scorm12)->get($element);

        self::assertSame([$value, $error], [$result->value, $result->error]);
        self::assertSame(0 !== $error, $result->failed());
    }

    /**
     * @return iterable<string, array{string, int|float|string|null, int}>
     */
    public static function writes(): iterable
    {
        yield 'a status' => ['cmi.core.lesson_status', 'incomplete', 0];
        yield 'not attempted' => ['cmi.core.lesson_status', 'not attempted', 405];
        yield 'a status of 2004' => ['cmi.core.lesson_status', 'unknown', 405];
        yield 'a raw score' => ['cmi.core.score.raw', '85.5', 0];
        yield 'a blank score' => ['cmi.core.score.raw', '', 0];
        yield 'a number' => ['cmi.core.score.raw', 72, 0];
        yield 'a decimal number' => ['cmi.core.score.raw', 72.5, 0];
        yield 'an infinite number' => ['cmi.core.score.raw', \INF, 405];
        yield 'null' => ['cmi.core.score.raw', null, 405];
        yield 'a score over 100' => ['cmi.core.score.raw', '100.01', 405];
        yield 'a score of 100' => ['cmi.core.score.max', '100', 0];
        yield 'a score of 0' => ['cmi.core.score.min', '0', 0];
        yield 'a negative score' => ['cmi.core.score.raw', '-1', 405];
        yield 'a word as a score' => ['cmi.core.score.raw', 'high', 405];
        yield 'an exit' => ['cmi.core.exit', 'suspend', 0];
        yield 'an empty exit' => ['cmi.core.exit', '', 0];
        yield 'a normal exit' => ['cmi.core.exit', 'normal', 405];
        yield 'a session time' => ['cmi.core.session_time', '0001:02:03.45', 0];
        yield 'a short session time' => ['cmi.core.session_time', '01:02:03', 0];
        yield 'a session time in ISO' => ['cmi.core.session_time', 'PT1H', 405];
        yield 'a session time of 60 minutes' => ['cmi.core.session_time', '00:60:00', 405];
        yield 'suspend data' => ['cmi.suspend_data', str_repeat('x', 4096), 0];
        yield 'too much suspend data' => ['cmi.suspend_data', str_repeat('x', 4097), 405];
        yield 'suspend data in characters' => ['cmi.suspend_data', str_repeat('é', 4096), 0];
        yield 'bytes that are not UTF-8' => ['cmi.suspend_data', "\xff", 405];
        yield 'a location' => ['cmi.core.lesson_location', 'page-3', 0];
        yield 'a student id' => ['cmi.core.student_id', 'x', 403];
        yield 'the total time' => ['cmi.core.total_time', '0001:00:00', 403];
        yield 'the launch data' => ['cmi.launch_data', 'x', 403];
        yield 'a comment' => ['cmi.comments', 'Clear', 0];
        yield 'a keyword' => ['cmi.core._children', 'x', 402];
        yield 'the version' => ['cmi._version', '4', 402];
        yield 'a count' => ['cmi.interactions._count', '3', 402];
        yield 'a count of nothing' => ['cmi.nothing._count', '3', 401];
        yield 'the version of a part' => ['cmi.core._version', '4', 401];
        yield 'an element the data model lacks' => ['cmi.core.mood', 'x', 401];
        yield 'nothing' => ['', 'x', 201];
        yield 'an audio preference' => ['cmi.student_preference.audio', '-1', 0];
        yield 'an audio preference too low' => ['cmi.student_preference.audio', '-2', 405];
        yield 'a decimal audio preference' => ['cmi.student_preference.audio', '1.5', 405];
        yield 'a speed' => ['cmi.student_preference.speed', '-100', 0];
        yield 'a speed too high' => ['cmi.student_preference.speed', '101', 405];
        yield 'a text preference' => ['cmi.student_preference.text', '1', 0];
        yield 'a text preference too high' => ['cmi.student_preference.text', '2', 405];
        yield 'an interaction' => ['cmi.interactions.0.id', 'q1', 0];
        yield 'an interaction with a space' => ['cmi.interactions.0.id', 'q 1', 405];
        yield 'an interaction past the next' => ['cmi.interactions.1.id', 'q2', 201];
        yield 'an interaction before its identifier' => ['cmi.interactions.0.result', 'correct', 0];
        yield 'a result of 2004' => ['cmi.interactions.0.result', 'incorrect', 405];
        yield 'a numeric result' => ['cmi.interactions.0.result', '0.5', 0];
        yield 'an interaction time' => ['cmi.interactions.0.time', '23:59:59', 0];
        yield 'an interaction time past midnight' => ['cmi.interactions.0.time', '24:00:00', 405];
        yield 'an interaction type' => ['cmi.interactions.0.type', 'likert', 0];
        yield 'an interaction type of 2004' => ['cmi.interactions.0.type', 'other', 405];
        yield 'a weighting' => ['cmi.interactions.0.weighting', '-2.5', 0];
        yield 'a correct response' => ['cmi.interactions.0.correct_responses.0.pattern', 'a,b', 0];
        yield 'an objective of an interaction' => ['cmi.interactions.0.objectives.0.id', 'o1', 0];
        yield 'an objective' => ['cmi.objectives.0.id', 'o1', 0];
        yield 'an objective status' => ['cmi.objectives.0.status', 'not attempted', 0];
        yield 'an objective score' => ['cmi.objectives.0.score.raw', '50', 0];
    }

    #[DataProvider('writes')]
    public function testAnElementIsWrittenAsScorm12Answers(string $element, int|float|string|null $value, int $error): void
    {
        $result = new CmiData(ScormVersion::Scorm12)->set($element, $value);

        self::assertSame(['', $error], [$result->value, $result->error]);
    }

    public function testAValueWrittenIsReadBackAndCounted(): void
    {
        $data = new CmiData(ScormVersion::Scorm12);
        $data->set('cmi.objectives.0.id', 'o1');
        $data->set('cmi.objectives.1.id', 'o2');
        $data->set('cmi.objectives.1.id', 'o3');
        $data->set('cmi.interactions.0.id', 'q1');
        $data->set('cmi.interactions.0.objectives.0.id', 'o1');
        $data->set('cmi.core.score.raw', 72);

        self::assertSame('o3', $data->get('cmi.objectives.1.id')->value);
        self::assertSame('2', $data->get('cmi.objectives._count')->value);
        self::assertSame('1', $data->get('cmi.interactions.0.objectives._count')->value);
        self::assertSame('raw,min,max', $data->get('cmi.objectives.1.score._children')->value);
        self::assertSame(ScormError::INVALID_ARGUMENT_12, $data->get('cmi.objectives.2.score._children')->error);
        self::assertSame('72', $data->get('cmi.core.score.raw')->value);
        self::assertSame(2, $data->count('cmi.objectives'));
        self::assertSame(0, $data->count('cmi.interactions.1.objectives'));
        self::assertSame(['cmi.objectives.0.id' => 'o1', 'cmi.objectives.1.id' => 'o3', 'cmi.interactions.0.id' => 'q1', 'cmi.interactions.0.objectives.0.id' => 'o1', 'cmi.core.score.raw' => '72'], $data->values());
        self::assertSame('q1', $data->value('cmi.interactions.0.id'));
        self::assertNull($data->value('cmi.interactions.1.id'));
    }

    public function testACollectionStopsAtItsBound(): void
    {
        $data = new CmiData(ScormVersion::Scorm12);

        for ($index = 0; $index < 100; ++$index) {
            self::assertSame(0, $data->set('cmi.objectives.'.$index.'.id', 'o'.$index)->error);
        }

        self::assertSame(ScormError::GENERAL, $data->set('cmi.objectives.100.id', 'o100')->error);
        self::assertSame('The collection holds 100 elements at most.', $data->set('cmi.objectives.100.id', 'o100')->diagnostic);
    }

    public function testTheCorrectResponsesOfAnInteractionAreBoundedByTen(): void
    {
        $data = new CmiData(ScormVersion::Scorm12);

        for ($index = 0; $index < 10; ++$index) {
            self::assertSame(0, $data->set('cmi.interactions.0.correct_responses.'.$index.'.pattern', 'p'.$index)->error);
        }

        self::assertSame(ScormError::GENERAL, $data->set('cmi.interactions.0.correct_responses.10.pattern', 'p')->error);
    }

    public function testTheDataOfAnAttemptIsBoundedInBytes(): void
    {
        $data = new CmiData(ScormVersion::Scorm12, maxBytes: 40);

        self::assertSame(0, $data->set('cmi.suspend_data', str_repeat('a', 24))->error);
        self::assertSame(0, $data->set('cmi.suspend_data', str_repeat('b', 24))->error);
        self::assertSame(ScormError::GENERAL, $data->set('cmi.suspend_data', str_repeat('c', 25))->error);
        self::assertSame('The tracking data of an attempt holds 40 bytes at most.', $data->set('cmi.core.lesson_location', 'x')->diagnostic);

        $data->forget('cmi.suspend_data');
        $data->forget('cmi.suspend_data');

        self::assertSame(0, $data->set('cmi.core.lesson_location', str_repeat('x', 16))->error);
        self::assertSame(ScormError::GENERAL, $data->set('cmi.comments', '')->error);
    }

    public function testTheValuesKeptAreReadBack(): void
    {
        $data = new CmiData(ScormVersion::Scorm12, ['cmi.interactions.1.id' => 'q2', 'cmi.core.lesson_status' => 'passed']);

        self::assertSame('2', $data->get('cmi.interactions._count')->value);
        self::assertSame('passed', $data->get('cmi.core.lesson_status')->value);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function refusedValues(): iterable
    {
        yield 'a value that is not text' => [['cmi.core.lesson_status' => 3], 'The tracking data kept holds an element or a value that is not text.'];
        yield 'an element that is a number' => [[3 => 'x'], 'The tracking data kept holds an element or a value that is not text.'];
        yield 'an unknown element' => [['cmi.mood' => 'x'], 'The tracking data kept names "cmi.mood", which is not an element of SCORM 1.2.'];
        yield 'a keyword' => [['cmi.interactions._count' => '1'], 'The tracking data kept names "cmi.interactions._count", which is not an element of SCORM 1.2.'];
        yield 'a name that is not one' => [['cmi..x' => '1'], 'The tracking data kept names "cmi..x", which is not an element of SCORM 1.2.'];
    }

    /**
     * @param array<array-key, mixed> $values
     */
    #[DataProvider('refusedValues')]
    public function testValuesKeptThatAreNotTrackingDataAreRefused(array $values, string $message): void
    {
        $this->expectException(RuntimeRefused::class);
        $this->expectExceptionMessageIs($message);

        new CmiData(ScormVersion::Scorm12, $values);
    }

    public function testThePlatformWritesWhatTheContentCannot(): void
    {
        $data = new CmiData(ScormVersion::Scorm12);
        $data->put('cmi.core.student_id', 'learner-1');

        self::assertSame('learner-1', $data->get('cmi.core.student_id')->value);

        $this->expectException(RuntimeRefused::class);
        $this->expectExceptionMessageIs('"cmi.core._count" is not an element of SCORM 1.2.');

        $data->put('cmi.core._count', '1');
    }

    public function testThePlatformWritesOnlyElements(): void
    {
        $this->expectException(RuntimeRefused::class);
        $this->expectExceptionMessageIs('"cmi.mood" is not an element of SCORM 1.2.');

        new CmiData(ScormVersion::Scorm12)->put('cmi.mood', '1');
    }

    public function testThePlatformWritesOnlyNames(): void
    {
        $this->expectException(RuntimeRefused::class);

        new CmiData(ScormVersion::Scorm12)->put('', '1');
    }

    /**
     * @return iterable<string, array{array<string, string>, string, string, ?float}>
     */
    public static function statuses(): iterable
    {
        yield 'nothing' => [[], 'not attempted', 'unknown', null];
        yield 'passed with a raw score' => [['cmi.core.lesson_status' => 'passed', 'cmi.core.score.raw' => '80'], 'completed', 'passed', 0.8];
        yield 'a score within its range' => [['cmi.core.score.raw' => '15', 'cmi.core.score.min' => '10', 'cmi.core.score.max' => '20'], 'not attempted', 'unknown', 0.5];
        yield 'a blank score' => [['cmi.core.score.raw' => ''], 'not attempted', 'unknown', null];
    }

    /**
     * @param array<string, string> $values
     */
    #[DataProvider('statuses')]
    public function testTheStatusReadsTheLessonStatusAndTheScore(array $values, string $completion, string $success, ?float $score): void
    {
        $status = new CmiData(ScormVersion::Scorm12, $values)->status();

        self::assertSame([$completion, $success, $score], [$status->completion, $status->success, $status->score]);
    }
}
