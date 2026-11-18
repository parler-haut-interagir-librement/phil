<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Unit\Runtime;

use Phil\ElearningPackages\Exception\RuntimeRefused;
use Phil\ElearningPackages\Runtime\CmiAttempt;
use Phil\ElearningPackages\Runtime\CmiCommit;
use Phil\ElearningPackages\Runtime\CmiData;
use Phil\ElearningPackages\Runtime\Entry;
use Phil\ElearningPackages\Runtime\LaunchSettings;
use Phil\ElearningPackages\Runtime\ScormError;
use Phil\ElearningPackages\Runtime\ScormVersion;
use Phil\ElearningPackages\Runtime\SessionEnd;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CmiAttempt::class)]
#[CoversClass(CmiCommit::class)]
#[CoversClass(LaunchSettings::class)]
#[CoversClass(SessionEnd::class)]
#[CoversClass(Entry::class)]
final class AttemptTest extends TestCase
{
    public function testASessionOfScorm12BeginsWithWhatThePlatformSets(): void
    {
        $data = new CmiData(ScormVersion::Scorm12, ['cmi.core.exit' => 'suspend', 'cmi.core.session_time' => '00:01:00', 'cmi.core.lesson_status' => 'incomplete']);
        CmiAttempt::begin($data, new LaunchSettings('learner-1', 'Ana', false, 'review', 'start=3', 0.8, 0.5, '00:30:00', 'exit,message'), Entry::Resume);

        self::assertSame([
            'cmi.core.lesson_status' => 'incomplete',
            'cmi.core.student_id' => 'learner-1',
            'cmi.core.student_name' => 'Ana',
            'cmi.core.credit' => 'no-credit',
            'cmi.core.lesson_mode' => 'review',
            'cmi.core.entry' => 'resume',
            'cmi.launch_data' => 'start=3',
            'cmi.student_data.mastery_score' => '80',
            'cmi.student_data.max_time_allowed' => '0000:30:00.00',
            'cmi.student_data.time_limit_action' => 'exit,message',
        ], $data->values());
    }

    public function testAFirstSessionOfScorm12IsNotAttempted(): void
    {
        $data = new CmiData(ScormVersion::Scorm12);
        CmiAttempt::begin($data, new LaunchSettings('learner-1', maxTimeAllowed: 'PT30M'), Entry::AbInitio);

        self::assertSame(['cmi.core.student_id' => 'learner-1', 'cmi.core.student_name' => '', 'cmi.core.credit' => 'credit', 'cmi.core.lesson_mode' => 'normal', 'cmi.core.entry' => 'ab-initio', 'cmi.core.lesson_status' => 'not attempted'], $data->values());
    }

    public function testASessionOfScorm2004BeginsWithWhatThePlatformSets(): void
    {
        $data = new CmiData(ScormVersion::Scorm2004, ['cmi.exit' => 'suspend', 'cmi.session_time' => 'PT1M', 'adl.nav.request' => 'continue']);
        CmiAttempt::begin($data, new LaunchSettings('learner-1', 'Ana', launchData: str_repeat('d', 4100), masteryScore: 0.125, completionThreshold: 0.75, maxTimeAllowed: 'PT1H', timeLimitAction: 'continue,message'), Entry::Other);

        self::assertSame('learner-1', $data->value('cmi.learner_id'));
        self::assertSame('Ana', $data->value('cmi.learner_name'));
        self::assertSame('credit', $data->value('cmi.credit'));
        self::assertSame('normal', $data->value('cmi.mode'));
        self::assertSame('', $data->value('cmi.entry'));
        self::assertSame(4000, mb_strlen((string) $data->value('cmi.launch_data')));
        self::assertSame('0.125', $data->value('cmi.scaled_passing_score'));
        self::assertSame('0.75', $data->value('cmi.completion_threshold'));
        self::assertSame('PT1H0M0S', $data->value('cmi.max_time_allowed'));
        self::assertSame('continue,message', $data->value('cmi.time_limit_action'));
        self::assertNull($data->value('cmi.exit'));
        self::assertNull($data->value('cmi.session_time'));
        self::assertNull($data->value('adl.nav.request'));
        self::assertNull($data->value('cmi.core.lesson_status'));

        $whole = new CmiData(ScormVersion::Scorm2004);
        CmiAttempt::begin($whole, new LaunchSettings('learner-1', masteryScore: 1.0, completionThreshold: 0.0, maxTimeAllowed: '01:00:00'), Entry::AbInitio);

        self::assertSame('1', $whole->value('cmi.scaled_passing_score'));
        self::assertSame('0', $whole->value('cmi.completion_threshold'));
        self::assertNull($whole->value('cmi.max_time_allowed'));
        self::assertNull($whole->value('cmi.launch_data'));
    }

    /**
     * @return iterable<string, array{array<string, string>, string, bool, bool, string}>
     */
    public static function ends12(): iterable
    {
        yield 'a status never set' => [[], 'completed', false, true, '0000:00:00.00'];
        yield 'not attempted' => [['cmi.core.lesson_status' => 'not attempted'], 'completed', false, true, '0000:00:00.00'];
        yield 'incomplete' => [['cmi.core.lesson_status' => 'incomplete', 'cmi.core.session_time' => '00:10:00'], 'incomplete', false, false, '0000:10:00.00'];
        yield 'suspended' => [['cmi.core.lesson_status' => 'completed', 'cmi.core.exit' => 'suspend'], 'completed', true, false, '0000:00:00.00'];
        yield 'a mastery score reached' => [['cmi.core.lesson_status' => 'incomplete', 'cmi.student_data.mastery_score' => '80', 'cmi.core.score.raw' => '80', 'cmi.core.credit' => 'credit'], 'passed', false, true, '0000:00:00.00'];
        yield 'a mastery score missed' => [['cmi.core.lesson_status' => 'passed', 'cmi.student_data.mastery_score' => '80', 'cmi.core.score.raw' => '79.9', 'cmi.core.credit' => 'credit'], 'failed', false, true, '0000:00:00.00'];
        yield 'a mastery score without credit' => [['cmi.core.lesson_status' => 'incomplete', 'cmi.student_data.mastery_score' => '80', 'cmi.core.score.raw' => '90', 'cmi.core.credit' => 'no-credit'], 'incomplete', false, false, '0000:00:00.00'];
        yield 'a mastery score without a raw score' => [['cmi.core.lesson_status' => 'incomplete', 'cmi.student_data.mastery_score' => '80', 'cmi.core.score.raw' => '', 'cmi.core.credit' => 'credit'], 'incomplete', false, false, '0000:00:00.00'];
        yield 'a blank mastery score' => [['cmi.core.lesson_status' => 'incomplete', 'cmi.student_data.mastery_score' => '', 'cmi.core.score.raw' => '90', 'cmi.core.credit' => 'credit'], 'incomplete', false, false, '0000:00:00.00'];
        yield 'a time added' => [['cmi.core.lesson_status' => 'browsed', 'cmi.core.total_time' => '0001:00:00.00', 'cmi.core.session_time' => '0000:30:00.5'], 'browsed', false, false, '0001:30:00.50'];
        yield 'a session time that is not one' => [['cmi.core.lesson_status' => 'failed', 'cmi.core.total_time' => '0001:00:00.00'], 'failed', false, true, '0001:00:00.00'];
    }

    /**
     * @param array<string, string> $values
     */
    #[DataProvider('ends12')]
    public function testASessionOfScorm12EndsByItsRules(array $values, string $status, bool $suspended, bool $ended, string $total): void
    {
        $data = new CmiData(ScormVersion::Scorm12, $values);
        $end = CmiAttempt::end($data);

        self::assertSame([$status, $suspended, $ended, $total], [$data->value('cmi.core.lesson_status'), $end->suspended, $end->attemptEnded, $data->value('cmi.core.total_time')]);
        self::assertSame($total, $end->totalTime->timespan());
        self::assertNull($end->navigation);
    }

    /**
     * @return iterable<string, array{array<string, string>, bool, bool, ?string, string}>
     */
    public static function ends2004(): iterable
    {
        yield 'a normal exit' => [['cmi.exit' => 'normal', 'cmi.session_time' => 'PT5M'], false, true, null, 'PT0H5M0S'];
        yield 'no exit' => [['cmi.completion_status' => 'incomplete'], false, true, null, 'PT0H0M0S'];
        yield 'suspended' => [['cmi.exit' => 'suspend', 'cmi.total_time' => 'PT1H', 'cmi.session_time' => 'PT0.5S'], true, false, null, 'PT1H0M0.50S'];
        yield 'a navigation request' => [['adl.nav.request' => 'continue'], false, true, 'continue', 'PT0H0M0S'];
        yield 'no navigation request' => [['adl.nav.request' => '_none_'], false, true, null, 'PT0H0M0S'];
    }

    /**
     * @param array<string, string> $values
     */
    #[DataProvider('ends2004')]
    public function testASessionOfScorm2004EndsByItsRules(array $values, bool $suspended, bool $ended, ?string $navigation, string $total): void
    {
        $data = new CmiData(ScormVersion::Scorm2004, $values);
        $end = CmiAttempt::end($data);

        self::assertSame([$suspended, $ended, $navigation, $total], [$end->suspended, $end->attemptEnded, $end->navigation, $data->value('cmi.total_time')]);
    }

    public function testAnEndReadsTheStatus(): void
    {
        $end = CmiAttempt::end(new CmiData(ScormVersion::Scorm2004, ['cmi.completion_status' => 'completed', 'cmi.success_status' => 'passed', 'cmi.score.scaled' => '0.9']));

        self::assertSame(['completed', 'passed', 0.9], [$end->status->completion, $end->status->success, $end->status->score]);
    }

    /**
     * @return iterable<string, array{array{learnerId: string, mode?: string, masteryScore?: float, completionThreshold?: float, timeLimitAction?: string}, string}>
     */
    public static function refusedSettings(): iterable
    {
        yield 'no learner' => [['learnerId' => ''], 'The learner of a SCORM session is named by 1 to 255 printable characters, without a space.'];
        yield 'a learner with a space' => [['learnerId' => 'a b'], 'The learner of a SCORM session is named by 1 to 255 printable characters, without a space.'];
        yield 'a long learner' => [['learnerId' => str_repeat('a', 256)], 'The learner of a SCORM session is named by 1 to 255 printable characters, without a space.'];
        yield 'a mode' => [['learnerId' => 'a', 'mode' => 'test'], 'The mode of a SCORM session is "normal", "review" or "browse".'];
        yield 'a mastery score' => [['learnerId' => 'a', 'masteryScore' => 1.01], 'A mastery score and a completion threshold are from 0 to 1.'];
        yield 'a negative mastery score' => [['learnerId' => 'a', 'masteryScore' => -0.01], 'A mastery score and a completion threshold are from 0 to 1.'];
        yield 'a threshold' => [['learnerId' => 'a', 'completionThreshold' => 1.5], 'A mastery score and a completion threshold are from 0 to 1.'];
        yield 'a negative threshold' => [['learnerId' => 'a', 'completionThreshold' => -0.5], 'A mastery score and a completion threshold are from 0 to 1.'];
        yield 'an action' => [['learnerId' => 'a', 'timeLimitAction' => 'stop'], 'The time limit action is one SCORM names, such as "continue,no message".'];
    }

    /**
     * @param array{learnerId: string, mode?: string, masteryScore?: float, completionThreshold?: float, timeLimitAction?: string} $arguments
     */
    #[DataProvider('refusedSettings')]
    public function testSettingsOutOfTheirBoundsAreRefused(array $arguments, string $message): void
    {
        $this->expectException(RuntimeRefused::class);
        $this->expectExceptionMessageIs($message);

        new LaunchSettings(...$arguments);
    }

    public function testSettingsAtTheirBoundsAreKept(): void
    {
        $settings = new LaunchSettings(str_repeat('a', 255), mode: 'browse', masteryScore: 0.0, completionThreshold: 1.0);

        self::assertSame([0.0, 1.0, 'browse'], [$settings->masteryScore, $settings->completionThreshold, $settings->mode]);
    }

    public function testACommitKeepsTheValuesTheDataModelAccepts(): void
    {
        $data = new CmiData(ScormVersion::Scorm2004);
        $errors = CmiCommit::apply($data, [
            'cmi.location' => 'page-2',
            'cmi.learner_id' => 'other',
            'cmi.interactions.0.id' => 'q1',
            'cmi.interactions.0.type' => 'true-false',
            'cmi.interactions.0.learner_response' => 'maybe',
            'cmi.score.raw' => 12,
            5 => 'x',
        ]);

        self::assertSame(['cmi.learner_id' => ScormError::READ_ONLY, 'cmi.interactions.0.learner_response' => ScormError::TYPE_MISMATCH, '5' => ScormError::UNDEFINED_ELEMENT], $errors);
        self::assertSame(['cmi.location' => 'page-2', 'cmi.interactions.0.id' => 'q1', 'cmi.interactions.0.type' => 'true-false', 'cmi.score.raw' => '12'], $data->values());
    }

    public function testACommitNamesAtMostItsBound(): void
    {
        $values = [];

        for ($index = 0; $index <= CmiCommit::MAX_VALUES; ++$index) {
            $values['cmi.location'.str_repeat('x', $index % 2).$index] = 'p';
        }

        $values['cmi.location'] = 'kept';
        $data = new CmiData(ScormVersion::Scorm2004);
        $errors = CmiCommit::apply($data, ['cmi.location' => 'first'] + $values);

        self::assertCount(CmiCommit::MAX_VALUES, $errors);
        self::assertSame(ScormError::GENERAL_SET, end($errors));
        self::assertSame('first', $data->value('cmi.location'));
        self::assertSame([], CmiCommit::apply($data, []));
    }
}
