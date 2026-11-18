<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Scorm;

use Phil\CoursesBundle\Application\ActivityType\ScormActivity;
use Phil\CoursesBundle\Application\ContentUrls;
use Phil\CoursesBundle\Application\Launch;
use Phil\CoursesBundle\Application\LaunchWindow;
use Phil\CoursesBundle\Application\Scorm\CommitAnswer;
use Phil\CoursesBundle\Application\Scorm\ScormLaunch;
use Phil\CoursesBundle\Application\Scorm\ScormRuntime;
use Phil\CoursesBundle\Application\Scorm\ScormTokens;
use Phil\CoursesBundle\Domain\Event\ActivityProgressed;
use Phil\CoursesBundle\Domain\Event\ActivitySatisfied;
use Phil\CoursesBundle\Domain\Event\ScormSessionEnded;
use Phil\CoursesBundle\Domain\Exception\ActivityUnavailable;
use Phil\CoursesBundle\Domain\Exception\AttemptRefused;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\Progress;
use Phil\CoursesBundle\Domain\Model\ScormAttempt;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;
use Phil\CoursesBundle\Domain\Scorm\AttemptState;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\ScormAttemptId;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalScormAttemptRepository;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use Phil\ElearningPackages\Runtime\ScormVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScormRuntime::class)]
#[CoversClass(ScormTokens::class)]
#[CoversClass(ScormLaunch::class)]
#[CoversClass(CommitAnswer::class)]
#[CoversClass(ScormAttempt::class)]
#[CoversClass(AttemptState::class)]
#[CoversClass(AttemptRefused::class)]
#[CoversClass(ScormSessionEnded::class)]
#[CoversClass(ScormAttemptId::class)]
#[CoversClass(DbalScormAttemptRepository::class)]
#[CoversClass(ScormActivity::class)]
#[CoversClass(Launch::class)]
final class ScormRuntimeTest extends TestCase
{
    private Store $store;

    protected function setUp(): void
    {
        $this->store = new Store();
    }

    public function testAFirstLaunchBeginsAnAttemptOfScorm12(): void
    {
        [$activity, $enrollment] = $this->activity('scorm12', ['mastery_score' => 0.7, 'launch_data' => 'start=1', 'max_time_allowed' => '00:30:00', 'time_limit_action' => 'continue,message'], CompletionRequirement::Passed);
        $launch = $this->store->scorm->launch($activity, $enrollment);

        self::assertSame([1, ScormVersion::Scorm12, false], [$launch->number, $launch->version, $launch->resumed]);
        self::assertSame(['ada', '', 'credit', 'normal', 'ab-initio', 'start=1', 'not attempted', '70', '0000:30:00.00', 'continue,message'], array_values(array_intersect_key($launch->values, array_flip([
            'cmi.core.student_id', 'cmi.core.student_name', 'cmi.core.credit', 'cmi.core.lesson_mode', 'cmi.core.entry', 'cmi.launch_data',
            'cmi.core.lesson_status', 'cmi.student_data.mastery_score', 'cmi.student_data.max_time_allowed', 'cmi.student_data.time_limit_action',
        ]))));
        self::assertSame('1.2', $launch->model['version']);
        self::assertSame(['version' => '1.2', 'api' => 'API', 'sco' => '/x/a.html', 'values' => $launch->values, 'model' => $launch->model], $launch->state('/x/a.html'));
        self::assertSame([$launch->attempt->value, 1], [$this->store->scormTokens->verify($launch->token)[0]->value, $this->store->scormTokens->verify($launch->token)[1]]);

        $attempt = $this->store->attemptStore->find($launch->attempt);
        self::assertNotNull($attempt);
        self::assertSame([AttemptState::Running, 1, 1], [$attempt->state, $attempt->session, $attempt->number]);
    }

    public function testACommitKeepsWhatTheDataModelAcceptsAndRecordsACompletion(): void
    {
        [$activity, $enrollment] = $this->activity('scorm2004');
        $launch = $this->store->scorm->launch($activity, $enrollment);
        $this->forgetEvents();

        $answer = $this->store->scorm->commit($launch->token, ['cmi.location' => 'p2', 'cmi.learner_id' => 'eve'], false, LearnerId::of('ada'));
        self::assertSame(['cmi.learner_id' => 404], $answer->errors);
        self::assertFalse($answer->ended);
        self::assertNotNull($answer->token);
        self::assertSame([], $this->events());

        $this->store->clock->modify('+1 hour');
        $completed = $this->store->scorm->commit($answer->token, ['cmi.completion_status' => 'completed', 'cmi.score.scaled' => '0.5'], false);
        self::assertSame([ActivityProgressed::class, ActivitySatisfied::class], \array_slice($this->events(), 0, 2));
        $progress = $this->store->enrollments->progresses($enrollment)[$activity->id->value];
        self::assertSame([true, null, 0.5], [$progress->completed, $progress->success, $progress->score?->scaled]);

        $this->forgetEvents();
        $this->store->scorm->commit((string) $completed->token, ['cmi.location' => 'p3'], false);
        self::assertSame([], $this->events());

        $attempt = $this->store->attemptStore->find($launch->attempt);
        self::assertNotNull($attempt);
        self::assertSame(['p3', 'completed', 0.5], [$attempt->data['cmi.location'], $attempt->status->completion, $attempt->status->score]);
        self::assertSame('2026-11-13 11:00:00', $attempt->updatedAt->format('Y-m-d H:i:s'));
    }

    public function testAPassIsRecordedOnceAndAFailureOnlyWhenTheAttemptEnds(): void
    {
        [$activity, $enrollment] = $this->activity('scorm2004', ['scaled_passing_score' => 0.8], CompletionRequirement::Passed, 2);
        $launch = $this->store->scorm->launch($activity, $enrollment);
        $suspended = $this->store->scorm->commit($launch->token, ['cmi.score.scaled' => '0.5', 'cmi.exit' => 'suspend'], true);
        self::assertSame([true, null], [$suspended->ended, $suspended->token]);
        $progress = $this->store->enrollments->progresses($enrollment)[$activity->id->value];
        self::assertSame([0, null], [$progress->failures, $progress->success]);

        $resumed = $this->store->scorm->launch($activity, $enrollment);
        self::assertTrue($resumed->resumed);
        self::assertSame('resume', $resumed->values['cmi.entry']);
        $this->store->scorm->commit($resumed->token, ['cmi.exit' => 'normal'], true);
        $progress = $this->store->enrollments->progresses($enrollment)[$activity->id->value];
        self::assertSame([1, false], [$progress->failures, $progress->success]);

        $second = $this->store->scorm->launch($activity, $enrollment);
        self::assertSame([2, 'ab-initio'], [$second->number, $second->values['cmi.entry']]);
        $this->store->scorm->commit($second->token, ['cmi.score.scaled' => '0.9'], false);
        $progress = $this->store->enrollments->progresses($enrollment)[$activity->id->value];
        self::assertSame([1, true], [$progress->failures, $progress->success]);
        self::assertTrue($progress->isSatisfied());
    }

    public function testASessionOfScorm12LeftIncompleteGoesOnWithTheSameData(): void
    {
        [$activity, $enrollment] = $this->activity('scorm12');
        $launch = $this->store->scorm->launch($activity, $enrollment);
        $this->store->scorm->commit($launch->token, ['cmi.core.lesson_status' => 'incomplete', 'cmi.suspend_data' => 'p=4', 'cmi.core.session_time' => '00:10:00'], true);

        self::assertSame(AttemptState::Paused, $this->store->attemptStore->find($launch->attempt)?->state);
        self::assertSame([ScormSessionEnded::class], array_map(static fn (object $event): string => $event::class, array_values(array_filter($this->store->events->events, static fn (object $event): bool => $event instanceof ScormSessionEnded))));

        $next = $this->store->scorm->launch($activity, $enrollment);
        self::assertSame([1, '', 'p=4', '0000:10:00.00', false], [$next->number, $next->values['cmi.core.entry'], $next->values['cmi.suspend_data'], $next->values['cmi.core.total_time'], $next->resumed]);
        self::assertArrayNotHasKey('cmi.core.session_time', $next->values);
    }

    public function testASessionLeftRunningIsEndedByTheNextLaunch(): void
    {
        [$activity, $enrollment] = $this->activity('scorm12');
        $launch = $this->store->scorm->launch($activity, $enrollment);
        $this->store->scorm->commit($launch->token, ['cmi.core.lesson_status' => 'passed'], false);

        $next = $this->store->scorm->launch($activity, $enrollment);
        self::assertSame(2, $next->number);
        $ended = $this->store->attemptStore->find($launch->attempt);
        self::assertNotNull($ended);
        self::assertSame(AttemptState::Ended, $ended->state);
        self::assertNotNull($ended->endedAt);

        try {
            $this->store->scorm->commit($launch->token, [], false);
            self::fail('A session of an ended attempt commits.');
        } catch (AttemptRefused $attemptRefused) {
            self::assertTrue($attemptRefused->closed);
            self::assertSame('This session of the attempt is over: launch the activity again.', $attemptRefused->getMessage());
        }
    }

    public function testASessionWithoutCreditRecordsNothing(): void
    {
        [$activity, $enrollment] = $this->activity('scorm12');
        $launch = $this->store->scorm->launch($activity, $enrollment);
        $attempt = $this->store->attemptStore->find($launch->attempt);
        self::assertNotNull($attempt);
        $attempt->open(['cmi.core.credit' => 'no-credit', 'cmi.core.lesson_mode' => 'normal'] + $attempt->data, $this->store->clock->now());
        $this->store->attemptStore->save($attempt);
        $token = $this->store->scormTokens->issue($attempt->id, $attempt->session);

        $this->store->scorm->commit($token, ['cmi.core.lesson_status' => 'passed'], false);
        $this->store->scorm->commit($token, [], true);
        self::assertArrayNotHasKey($activity->id->value, array_filter($this->store->enrollments->progresses($enrollment), static fn (Progress $progress): bool => $progress->completed));

        $browse = $this->store->scorm->launch($activity, $enrollment);
        $attempt = $this->store->attemptStore->find($browse->attempt);
        self::assertNotNull($attempt);
        $attempt->open(['cmi.core.lesson_mode' => 'browse'] + $attempt->data, $this->store->clock->now());
        $this->store->attemptStore->save($attempt);
        $this->store->scorm->commit($this->store->scormTokens->issue($attempt->id, $attempt->session), ['cmi.core.lesson_status' => 'passed'], true);
        self::assertArrayNotHasKey($activity->id->value, array_filter($this->store->enrollments->progresses($enrollment), static fn (Progress $progress): bool => $progress->completed));
    }

    public function testACommitOfAnotherLearnerOrAnUnknownAttemptIsRefused(): void
    {
        [$activity, $enrollment] = $this->activity('scorm2004');
        $launch = $this->store->scorm->launch($activity, $enrollment);

        foreach ([
            [$launch->token, LearnerId::of('eve'), "This attempt is another learner's."],
            [$this->store->scormTokens->issue(ScormAttemptId::of('01890000-0000-7000-8000-000000000000'), 1), null, 'The attempt of this token is unknown.'],
            ['forged', null, 'The attempt token is not one this site gives.'],
        ] as [$token, $learner, $message]) {
            try {
                $this->store->scorm->commit($token, [], false, $learner);
                self::fail($message);
            } catch (AttemptRefused $attemptRefused) {
                self::assertSame([$message, false], [$attemptRefused->getMessage(), $attemptRefused->closed]);
            }
        }

        self::assertNotNull($this->store->scorm->commit($launch->token, [], false, LearnerId::of('ada'))->token);
    }

    public function testAnActivityWithoutAVersionOrWithAWrongValueIsNotLaunched(): void
    {
        [$activity, $enrollment] = $this->activity(null);

        try {
            $this->store->scorm->launch($activity, $enrollment);
            self::fail('An activity without a version is launched.');
        } catch (ActivityUnavailable $activityUnavailable) {
            self::assertSame(\sprintf('The SCORM activity "%s" names no version of SCORM.', $activity->id->value), $activityUnavailable->getMessage());
        }

        [$wrong, $other] = $this->activity('scorm12', ['time_limit_action' => 'stop'], learner: 'eve');
        $this->expectException(ActivityUnavailable::class);
        $this->expectExceptionMessageIs('The time limit action is one SCORM names, such as "continue,no message".');

        $this->store->scorm->launch($wrong, $other);
    }

    public function testScoresAndThresholdsOutOfBoundsAreBroughtBack(): void
    {
        [$activity, $enrollment] = $this->activity('scorm2004', ['scaled_passing_score' => 1.5, 'completion_threshold' => -1]);
        $launch = $this->store->scorm->launch($activity, $enrollment);

        self::assertSame(['1', '0'], [$launch->values['cmi.scaled_passing_score'], $launch->values['cmi.completion_threshold']]);
    }

    public function testTheActivityTypeLaunchesThroughTheRunTime(): void
    {
        [$activity, $enrollment] = $this->activity('scorm2004');
        $type = new ScormActivity(new ContentUrls(Store::CONTENT), $this->store->scorm);
        $launch = $type->launch($activity, $enrollment);

        self::assertFalse($type->completesManually());
        self::assertTrue(new ScormActivity(new ContentUrls(Store::CONTENT))->completesManually());
        self::assertSame(LaunchWindow::Embedded, $launch->window);
        self::assertSame(Store::CONTENT.'/'.$activity->course->value.'/a.html', $launch->url);
        self::assertInstanceOf(ScormLaunch::class, $launch->scorm);
        self::assertNull(new ScormActivity(new ContentUrls(Store::CONTENT))->launch($activity, $enrollment)->scorm);
    }

    public function testATokenExpiresAndIsBoundToItsSignature(): void
    {
        $tokens = new ScormTokens($this->store->clock, Store::SECRET, 600);
        $id = ScormAttemptId::of('01890000-0000-7000-8000-000000000000');
        $token = $tokens->issue($id, 7);

        self::assertSame([$id->value, 7], [$tokens->verify($token)[0]->value, $tokens->verify($token)[1]]);
        self::assertMatchesRegularExpression('/^01890000-0000-7000-8000-000000000000\.7\.\d+\.[A-Za-z0-9_-]{43}$/', $token);

        foreach ([
            new ScormTokens($this->store->clock, 'another secret', 600)->issue($id, 7) => 'The attempt token is not one this site gives.',
            str_replace('.7.', '.8.', $token) => 'The attempt token is not one this site gives.',
            $token.'x' => 'The attempt token is not one this site gives.',
            str_repeat('a', 201) => 'The attempt token is not one this site gives.',
            str_replace('01890000-0000-7000-8000-000000000000', '------------------------------------', $tokens->issue($id, 7)) => 'The attempt token is not one this site gives.',
        ] as $refused => $message) {
            try {
                $tokens->verify($refused);
                self::fail('The token is accepted: '.$refused);
            } catch (AttemptRefused $attemptRefused) {
                self::assertSame($message, $attemptRefused->getMessage());
            }
        }

        $this->store->clock->modify('+600 seconds');
        self::assertSame(7, $tokens->verify($token)[1]);
        $this->store->clock->modify('+1 second');
        $this->expectException(AttemptRefused::class);
        $this->expectExceptionMessageIs('The attempt token expired: launch the activity again.');
        $tokens->verify($token);
    }

    /**
     * @return list<string>
     *
     * @phpstan-impure
     */
    private function events(): array
    {
        return array_map(static fn (object $event): string => $event::class, $this->store->events->events);
    }

    private function forgetEvents(): void
    {
        $this->store->events->events = [];
    }

    /**
     * @param array<string, float|int|string> $settings
     *
     * @return array{0: Activity, 1: Enrollment}
     */
    private function activity(?string $version, array $settings = [], CompletionRequirement $requirement = CompletionRequirement::CompletedOrPassed, ?int $tries = null, string $learner = 'ada'): array
    {
        $course = $this->store->courses->create('C'.$learner);
        $module = $this->store->courses->addModule($course->id, 'M');
        $activity = $this->store->courses->addActivity($module->id, 'A', 'scorm', ['path' => 'a.html'] + (null === $version ? [] : ['scorm_version' => $version]) + $settings, $requirement, true, $tries);

        return [$activity, $this->store->enrollments->enroll($course->id, LearnerId::of($learner))];
    }
}
