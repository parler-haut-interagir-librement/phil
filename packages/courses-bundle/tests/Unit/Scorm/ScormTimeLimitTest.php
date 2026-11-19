<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Scorm;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CoursesBundle\Application\ActivityType\ScormActivity;
use Phil\CoursesBundle\Application\ContentUrls;
use Phil\CoursesBundle\Application\Scorm\CommitAnswer;
use Phil\CoursesBundle\Application\Scorm\ScormRuntime;
use Phil\CoursesBundle\Application\Scorm\TimeLimit;
use Phil\CoursesBundle\Domain\Event\ScormSessionEnded;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\Progress;
use Phil\CoursesBundle\Domain\Model\ScormAttempt;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;
use Phil\CoursesBundle\Domain\Scorm\AttemptState;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\ModuleId;
use Phil\CoursesBundle\Domain\ValueObject\ScormAttemptId;
use Phil\CoursesBundle\Infrastructure\Doctrine\CourseTables;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalScormAttemptRepository;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use Phil\ElearningPackages\Runtime\Duration;
use Phil\ElearningPackages\Runtime\RuntimeStatus;
use Phil\ElearningPackages\Runtime\ScormVersion;
use Phil\ElearningPackages\Runtime\SessionEnd;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The time limit of a SCORM attempt, enforced by the server's clock: the time handed to the content, the deadline, the
 * commit that reaches it, a commit too late, the sessions closed without a word, and the attempts resumed past it.
 */
#[CoversClass(TimeLimit::class)]
#[CoversClass(ScormRuntime::class)]
#[CoversClass(ScormAttempt::class)]
#[CoversClass(CommitAnswer::class)]
#[CoversClass(ScormActivity::class)]
#[CoversClass(DbalScormAttemptRepository::class)]
final class ScormTimeLimitTest extends TestCase
{
    private Store $store;

    protected function setUp(): void
    {
        $this->store = new Store();
    }

    /**
     * @param array<string, int|string>                                              $settings
     * @param ?array{allowed: int, action: string, deadline: ?int, duration: string} $expected
     */
    #[DataProvider('limits')]
    public function testTheLimitIsTheShorterOfTheManifestAndTheActivity(string $version, array $settings, ?array $expected): void
    {
        $scorm = ScormVersion::from($version);
        $activity = new Activity(ActivityId::generate(), CourseId::generate(), ModuleId::generate(), 'A', 'scorm', 0, $settings);
        $limit = TimeLimit::of($activity, $scorm);

        self::assertSame($expected, $limit instanceof TimeLimit ? ['allowed' => $limit->allowed, 'action' => $limit->action, 'deadline' => $limit->deadline, 'duration' => $limit->duration($scorm)] : null);
    }

    /**
     * @return iterable<string, array{string, array<string, int|string>, ?array{allowed: int, action: string, deadline: ?int, duration: string}}>
     */
    public static function limits(): iterable
    {
        yield 'none' => ['1.2', [], null];
        yield 'a time of nothing' => ['1.2', ['max_time_allowed' => '00:00:00', 'time_limit_action' => 'exit,message'], null];
        yield 'a time that is not one' => ['2004', ['max_time_allowed' => 'soon'], null];
        yield 'the manifest, continuing' => ['1.2', ['max_time_allowed' => '00:30:00'], ['allowed' => 1800, 'action' => 'continue,no message', 'deadline' => null, 'duration' => '0000:30:00.00']];
        yield 'the manifest, exiting' => ['2004', ['max_time_allowed' => 'PT30M', 'time_limit_action' => 'exit,message'], ['allowed' => 1800, 'action' => 'exit,message', 'deadline' => 1800, 'duration' => 'PT0H30M0S']];
        yield 'the activity alone' => ['2004', ['time_limit' => 600], ['allowed' => 600, 'action' => 'exit,message', 'deadline' => 600, 'duration' => 'PT0H10M0S']];
        yield 'the activity, shorter than a manifest continuing' => ['1.2', ['time_limit' => 600, 'max_time_allowed' => '00:30:00', 'time_limit_action' => 'continue,message'], ['allowed' => 600, 'action' => 'exit,message', 'deadline' => 600, 'duration' => '0000:10:00.00']];
        yield 'the activity, shorter than a manifest exiting' => ['1.2', ['time_limit' => 600, 'max_time_allowed' => '00:30:00', 'time_limit_action' => 'exit,no message'], ['allowed' => 600, 'action' => 'exit,no message', 'deadline' => 600, 'duration' => '0000:10:00.00']];
        yield 'the activity, as long as the manifest' => ['1.2', ['time_limit' => 1800, 'max_time_allowed' => '00:30:00'], ['allowed' => 1800, 'action' => 'exit,message', 'deadline' => 1800, 'duration' => '0000:30:00.00']];
        yield 'the manifest continuing, shorter than the activity' => ['1.2', ['time_limit' => 3600, 'max_time_allowed' => '00:30:00', 'time_limit_action' => 'continue,message'], ['allowed' => 1800, 'action' => 'continue,message', 'deadline' => 3600, 'duration' => '0000:30:00.00']];
        yield 'the manifest exiting, shorter than the activity' => ['2004', ['time_limit' => 3601, 'max_time_allowed' => 'PT1H', 'time_limit_action' => 'exit,message'], ['allowed' => 3600, 'action' => 'exit,message', 'deadline' => 3600, 'duration' => 'PT1H0M0S']];
        yield 'a limit that is not a number of seconds' => ['1.2', ['time_limit' => '600', 'max_time_allowed' => '00:01:00'], ['allowed' => 60, 'action' => 'continue,no message', 'deadline' => null, 'duration' => '0000:01:00.00']];
        yield 'a limit of nothing' => ['1.2', ['time_limit' => 0], null];
        yield 'a limit past the longest' => ['1.2', ['time_limit' => 36_000_000], ['allowed' => 35_999_999, 'action' => 'exit,message', 'deadline' => 35_999_999, 'duration' => '9999:59:59.00']];
    }

    public function testTheActivityChecksItsOwnLimit(): void
    {
        $type = new ScormActivity(new ContentUrls(Store::CONTENT));
        self::assertSame(['path' => 'a.html', 'time_limit' => 600], $type->settings(['path' => 'a.html', 'time_limit' => 600]));
        self::assertSame(['path' => 'a.html', 'time_limit' => 1], $type->settings(['path' => 'a.html', 'time_limit' => 1]));
        self::assertSame(['path' => 'a.html', 'time_limit' => 35_999_999], $type->settings(['path' => 'a.html', 'time_limit' => 35_999_999]));
        self::assertSame(['path' => 'a.html', 'time_limit' => null], $type->settings(['path' => 'a.html', 'time_limit' => null]));

        foreach ([0, -5, '600', 1.5, 36_000_000] as $wrong) {
            try {
                $type->settings(['path' => 'a.html', 'time_limit' => $wrong]);
                self::fail('A time limit is taken: '.var_export($wrong, true));
            } catch (InvalidCourse $invalidCourse) {
                self::assertSame('The "time_limit" of a SCORM activity is a number of seconds, from 1 to 35999999.', $invalidCourse->getMessage());
            }
        }
    }

    public function testTheCommitThatReachesTheDeadlineEndsTheAttempt(): void
    {
        [$activity, $enrollment] = $this->activity('scorm12', ['time_limit' => 600, 'max_time_allowed' => '00:30:00'], tries: 2);
        $launch = $this->store->scorm->launch($activity, $enrollment);
        self::assertSame([600, '0000:10:00.00', 'exit,message'], [$launch->remaining, $launch->values['cmi.student_data.max_time_allowed'], $launch->values['cmi.student_data.time_limit_action']]);
        self::assertSame(600, $launch->state('/a.html')['remaining']);

        $this->store->clock->modify('+299 seconds');
        $first = $this->store->scorm->commit($launch->token, ['cmi.core.lesson_status' => 'incomplete', 'cmi.core.session_time' => '00:01:00'], false);
        self::assertSame([301, false, false], [$first->remaining, $first->ended, $first->timeUp]);
        self::assertNotNull($first->token);

        $this->store->clock->modify('+301 seconds');
        $before = \count($this->store->events->events);
        $last = $this->store->scorm->commit($first->token, ['cmi.suspend_data' => 'q=9', 'cmi.core.exit' => 'suspend'], false);
        self::assertSame([[], null, true, null, false, 0, true], [$last->errors, $last->token, $last->ended, $last->next, $last->exit, $last->remaining, $last->timeUp]);

        $attempt = $this->store->attemptStore->find($launch->attempt);
        self::assertNotNull($attempt);
        self::assertSame([AttemptState::Ended, 600, 'time-out', 'q=9', 'incomplete'], [$attempt->state, $attempt->elapsed, $attempt->data['cmi.core.exit'], $attempt->data['cmi.suspend_data'], $attempt->data['cmi.core.lesson_status']]);
        $events = array_values(array_filter(\array_slice($this->store->events->events, $before), static fn (object $event): bool => $event instanceof ScormSessionEnded));
        self::assertCount(1, $events);
        self::assertTrue($events[0]->attemptEnded);
        $progress = $this->store->enrollments->progresses($enrollment)[$activity->id->value];
        self::assertSame([1, false, false], [$progress->failures, $progress->success, $progress->completed]);

        $next = $this->store->scorm->launch($activity, $enrollment);
        self::assertSame([2, 600, 'ab-initio'], [$next->number, $next->remaining, $next->values['cmi.core.entry']]);
    }

    public function testACommitWithinTheGraceIsKeptAndALaterOneIsNot(): void
    {
        [$activity, $enrollment] = $this->activity('scorm2004', ['time_limit' => 600]);
        $launch = $this->store->scorm->launch($activity, $enrollment);
        $this->store->clock->modify('+630 seconds');
        $kept = $this->store->scorm->commit($launch->token, ['cmi.location' => 'late', 'cmi.completion_status' => 'completed'], false);
        self::assertSame([true, true, -30], [$kept->ended, $kept->timeUp, $kept->remaining]);
        self::assertSame(['late', 'time-out'], [$this->store->attemptStore->find($launch->attempt)?->data['cmi.location'], $this->store->attemptStore->find($launch->attempt)?->data['cmi.exit']]);
        $progress = $this->store->enrollments->progresses($enrollment)[$activity->id->value];
        self::assertSame([0, null, true], [$progress->failures, $progress->success, $progress->completed]);

        [$other, $eve] = $this->activity('scorm2004', ['time_limit' => 600], learner: 'eve');
        $late = $this->store->scorm->launch($other, $eve);
        $answer = $this->store->scorm->commit($late->token, ['cmi.location' => 'p1'], false);
        $this->store->clock->modify('+631 seconds');
        $before = \count($this->store->events->events);
        $refused = $this->store->scorm->commit((string) $answer->token, ['cmi.location' => 'too late', 'cmi.completion_status' => 'completed', 'adl.nav.request' => 'continue'], true);
        self::assertSame([[], null, true, null, false, -31, true], [$refused->errors, $refused->token, $refused->ended, $refused->next, $refused->exit, $refused->remaining, $refused->timeUp]);

        $attempt = $this->store->attemptStore->find($late->attempt);
        self::assertNotNull($attempt);
        self::assertSame([AttemptState::Ended, 'p1', 'time-out', 631], [$attempt->state, $attempt->data['cmi.location'], $attempt->data['cmi.exit'], $attempt->elapsed]);
        self::assertArrayNotHasKey('cmi.completion_status', $attempt->data);
        self::assertCount(1, array_filter(\array_slice($this->store->events->events, $before), static fn (object $event): bool => $event instanceof ScormSessionEnded));
        $progress = $this->store->enrollments->progresses($eve)[$other->id->value];
        self::assertSame([1, false, false], [$progress->failures, $progress->success, $progress->completed]);
    }

    public function testTheTimeBetweenTwoSessionsIsNotCounted(): void
    {
        [$activity, $enrollment] = $this->activity('scorm2004', ['max_time_allowed' => 'PT10M', 'time_limit_action' => 'exit,no message']);
        $launch = $this->store->scorm->launch($activity, $enrollment);
        $this->store->clock->modify('+240 seconds');
        $this->store->scorm->commit($launch->token, ['cmi.exit' => 'suspend', 'cmi.session_time' => 'PT4M'], true);
        $this->store->clock->modify('+3 days');

        $resumed = $this->store->scorm->launch($activity, $enrollment);
        self::assertSame([true, 360, 1], [$resumed->resumed, $resumed->remaining, $resumed->number]);
        $this->store->clock->modify('+100 seconds');
        $answer = $this->store->scorm->commit($resumed->token, [], false);
        self::assertSame([260, false], [$answer->remaining, $answer->ended]);
    }

    public function testAManifestThatContinuesIsNotEnforced(): void
    {
        [$activity, $enrollment] = $this->activity('scorm12', ['max_time_allowed' => '00:01:00', 'time_limit_action' => 'continue,message']);
        $launch = $this->store->scorm->launch($activity, $enrollment);
        self::assertNull($launch->remaining);
        $this->store->clock->modify('+2 hours');
        $answer = $this->store->scorm->commit($launch->token, ['cmi.core.lesson_location' => 'p2'], false);
        self::assertSame([null, false, false], [$answer->remaining, $answer->ended, $answer->timeUp]);
        $end = $this->store->scorm->commit((string) $answer->token, ['cmi.core.lesson_status' => 'incomplete'], true);
        self::assertSame([null, false], [$end->remaining, $end->timeUp]);
        self::assertSame(AttemptState::Paused, $this->store->attemptStore->find($launch->attempt)?->state);
    }

    public function testASessionLeftRunningPastALowerLimitEndsByItsTime(): void
    {
        [$activity, $enrollment] = $this->activity('scorm12', ['time_limit' => 600]);
        $launch = $this->store->scorm->launch($activity, $enrollment);
        $this->store->clock->modify('+500 seconds');
        $this->store->scorm->commit($launch->token, ['cmi.core.lesson_status' => 'incomplete'], false);
        $this->settings($activity, ['time_limit' => 300]);
        $this->store->clock->modify('+1 hour');

        $next = $this->store->scorm->launch($this->store->courses->activity($activity->id), $enrollment);
        self::assertSame([2, 300], [$next->number, $next->remaining]);
        $ended = $this->store->attemptStore->find($launch->attempt);
        self::assertNotNull($ended);
        self::assertSame([AttemptState::Ended, 500, 'time-out', '2026-11-13 10:08:20'], [$ended->state, $ended->elapsed, $ended->data['cmi.core.exit'], $ended->endedAt?->format('Y-m-d H:i:s')]);
        self::assertSame(1, $this->store->enrollments->progresses($enrollment)[$activity->id->value]->failures);
    }

    public function testASessionLeftRunningWithinItsLimitGoesOn(): void
    {
        [$activity, $enrollment] = $this->activity('scorm12', ['time_limit' => 600]);
        $launch = $this->store->scorm->launch($activity, $enrollment);
        $this->store->clock->modify('+540 seconds');
        $this->store->scorm->commit($launch->token, ['cmi.core.lesson_status' => 'incomplete'], false);
        $this->store->clock->modify('+1 hour');

        $next = $this->store->scorm->launch($activity, $enrollment);
        self::assertSame([1, 60, ''], [$next->number, $next->remaining, $next->values['cmi.core.entry']]);
        self::assertSame(540, $this->store->attemptStore->find($launch->attempt)?->elapsed);
    }

    public function testAnAttemptResumedPastALowerLimitEnds(): void
    {
        [$activity, $enrollment] = $this->activity('scorm2004', ['time_limit' => 600], CompletionRequirement::Passed);
        $launch = $this->store->scorm->launch($activity, $enrollment);
        $this->store->clock->modify('+400 seconds');
        $this->store->scorm->commit($launch->token, ['cmi.exit' => 'suspend'], true);
        $this->settings($activity, ['time_limit' => 400]);
        $this->store->clock->modify('+1 day');

        $next = $this->store->scorm->launch($this->store->courses->activity($activity->id), $enrollment);
        self::assertSame([2, 400, false], [$next->number, $next->remaining, $next->resumed]);
        $expired = $this->store->attemptStore->find($launch->attempt);
        self::assertSame([AttemptState::Ended, '2026-11-14 10:06:40'], [$expired?->state, $expired?->endedAt?->format('Y-m-d H:i:s')]);
        self::assertSame(1, $this->store->enrollments->progresses($enrollment)[$activity->id->value]->failures);

        [$done, $eve] = $this->activity('scorm2004', ['time_limit' => 600], learner: 'eve');
        $passed = $this->store->scorm->launch($done, $eve);
        $this->store->scorm->commit($passed->token, ['cmi.completion_status' => 'completed', 'cmi.exit' => 'suspend'], true);
        $this->settings($done, ['time_limit' => 1]);
        $this->store->clock->modify('+1 second');
        $this->store->scorm->launch($this->store->courses->activity($done->id), $eve);

        $progress = $this->store->enrollments->progresses($eve)[$done->id->value];
        self::assertSame([0, true, null], [$progress->failures, $progress->completed, $progress->success]);
    }

    public function testAnAttemptWithoutCreditEndedByItsTimeRecordsNothing(): void
    {
        [$activity, $enrollment] = $this->activity('scorm12', ['time_limit' => 60]);
        $launch = $this->store->scorm->launch($activity, $enrollment);
        $attempt = $this->store->attemptStore->find($launch->attempt);
        self::assertNotNull($attempt);
        $attempt->open(['cmi.core.credit' => 'no-credit'] + $attempt->data, $this->store->clock->now());
        $this->store->attemptStore->save($attempt);
        $this->store->clock->modify('+61 seconds');
        $this->store->scorm->commit($this->store->scormTokens->issue($attempt->id, $attempt->session), [], false);
        self::assertSame([], array_filter($this->store->enrollments->progresses($enrollment), static fn (Progress $progress): bool => $progress->failures > 0));

        [$other, $eve] = $this->activity('scorm12', ['time_limit' => 600], learner: 'eve');
        $suspended = $this->store->scorm->launch($other, $eve);
        $kept = $this->store->attemptStore->find($suspended->attempt);
        self::assertNotNull($kept);
        $kept->open(['cmi.core.credit' => 'no-credit'] + $kept->data, $this->store->clock->now());
        $this->store->attemptStore->save($kept);
        $this->store->clock->modify('+100 seconds');
        $this->store->scorm->commit($this->store->scormTokens->issue($kept->id, $kept->session), ['cmi.core.exit' => 'suspend'], true);
        $this->settings($other, ['time_limit' => 50]);
        $this->store->scorm->launch($this->store->courses->activity($other->id), $eve);
        self::assertSame([], array_filter($this->store->enrollments->progresses($eve), static fn (Progress $progress): bool => $progress->failures > 0));
        self::assertSame(AttemptState::Ended, $this->store->attemptStore->find($suspended->attempt)?->state);
    }

    public function testTheAttemptCountsTheSecondsItsSessionsRan(): void
    {
        $at = new DateTimeImmutable('2026-11-13 10:00:00', new DateTimeZone('America/Toronto'));
        $attempt = new ScormAttempt(ScormAttemptId::generate(), EnrollmentId::of('e'), ActivityId::of('a'), 1, ScormVersion::Scorm12, $at, AttemptState::Suspended);
        self::assertSame([0, null], [$attempt->spent($at->modify('+1 hour')), $attempt->sessionOpenedAt]);

        $attempt->open([], $at);
        self::assertSame(['UTC', 0, 90], [$attempt->sessionOpenedAt?->getTimezone()->getName(), $attempt->spent($at->modify('-1 minute')), $attempt->spent($at->modify('+90 seconds'))]);
        $attempt->expire($at->modify('+2 minutes'));
        self::assertSame([AttemptState::Ended, 0, '15:02'], [$attempt->state, $attempt->spent($at->modify('+1 hour')), $attempt->endedAt?->format('H:i')]);

        $restored = new ScormAttempt(ScormAttemptId::generate(), EnrollmentId::of('e'), ActivityId::of('a'), 1, ScormVersion::Scorm12, $at, elapsed: 30, sessionOpenedAt: $at);
        self::assertSame([45, 'UTC'], [$restored->spent($at->modify('+15 seconds')), $restored->sessionOpenedAt?->getTimezone()->getName()]);
    }

    public function testTheSessionThatEndsAddsItsSecondsToThoseOfTheEarlierOnes(): void
    {
        $at = new DateTimeImmutable('2026-11-13 10:00:00', new DateTimeZone('UTC'));
        $end = new SessionEnd(new RuntimeStatus(), Duration::zero(), false, false);

        $running = new ScormAttempt(ScormAttemptId::generate(), EnrollmentId::of('e'), ActivityId::of('a'), 1, ScormVersion::Scorm12, $at, AttemptState::Running, 1, elapsed: 30, sessionOpenedAt: $at);
        $running->end([], $end, $at->modify('+15 seconds'));
        self::assertSame([AttemptState::Paused, 45], [$running->state, $running->elapsed]);

        $unopened = new ScormAttempt(ScormAttemptId::generate(), EnrollmentId::of('e'), ActivityId::of('a'), 1, ScormVersion::Scorm12, $at, AttemptState::Running, 1, elapsed: 30);
        self::assertSame(30, $unopened->spent($at->modify('+1 hour')));
        $unopened->end([], $end, $at->modify('+1 hour'));
        self::assertSame(30, $unopened->elapsed);

        $paused = new ScormAttempt(ScormAttemptId::generate(), EnrollmentId::of('e'), ActivityId::of('a'), 1, ScormVersion::Scorm12, $at, AttemptState::Paused, 1, elapsed: 30, sessionOpenedAt: $at);
        $paused->end([], $end, $at->modify('+15 seconds'));
        self::assertSame(30, $paused->elapsed);
    }

    public function testASecondsCountBelowZeroInTheStoreReadsAsZero(): void
    {
        [$activity, $enrollment] = $this->activity('scorm12', ['time_limit' => 600]);
        $launch = $this->store->scorm->launch($activity, $enrollment);
        $this->store->connection->update(CourseTables::SCORM_ATTEMPT, ['elapsed' => -5], ['id' => $launch->attempt->value]);

        self::assertSame(0, $this->store->attemptStore->find($launch->attempt)?->elapsed);
    }

    public function testTheSessionEndedByTheDeadlineLeavesNoNavigationRequestBehind(): void
    {
        [$activity, $enrollment] = $this->activity('scorm2004', ['time_limit' => 600]);
        $launch = $this->store->scorm->launch($activity, $enrollment);
        $this->store->clock->modify('+601 seconds');
        $answer = $this->store->scorm->commit($launch->token, ['adl.nav.request' => 'continue', 'cmi.completion_status' => 'incomplete'], false);

        self::assertTrue($answer->timeUp);
        $attempt = $this->store->attemptStore->find($launch->attempt);
        self::assertNotNull($attempt);
        self::assertArrayNotHasKey('adl.nav.request', $attempt->data);
        self::assertSame('time-out', $attempt->data['cmi.exit']);
    }

    public function testAnAttemptThatRanOutOfTimeKeepsWhatTheContentReported(): void
    {
        [$passed, $ada] = $this->activity('scorm2004', ['time_limit' => 600], CompletionRequirement::Completed);
        $launch = $this->store->scorm->launch($passed, $ada);
        $this->store->connection->update(CourseTables::SCORM_ATTEMPT, ['state' => 'suspended', 'completion' => 'incomplete', 'success' => 'passed', 'elapsed' => 100], ['id' => $launch->attempt->value]);
        $this->settings($passed, ['time_limit' => 1]);
        $this->store->clock->modify('+1 day');
        $this->store->scorm->launch($this->store->courses->activity($passed->id), $ada);

        $progress = $this->store->enrollments->progresses($ada)[$passed->id->value];
        self::assertSame([true, 0], [$progress->success, $progress->failures]);
    }

    /**
     * @param array<string, int|string> $settings
     *
     * @return array{0: Activity, 1: Enrollment}
     */
    private function activity(string $version, array $settings, CompletionRequirement $requirement = CompletionRequirement::CompletedOrPassed, ?int $tries = null, string $learner = 'ada'): array
    {
        $course = $this->store->courses->create('C'.$learner);
        $module = $this->store->courses->addModule($course->id, 'M');
        $activity = $this->store->courses->addActivity($module->id, 'A', 'scorm', ['path' => 'a.html', 'scorm_version' => $version] + $settings, $requirement, true, $tries);

        return [$activity, $this->store->enrollments->enroll($course->id, LearnerId::of($learner))];
    }

    /**
     * @param array<string, int> $settings
     */
    private function settings(Activity $activity, array $settings): void
    {
        $this->store->connection->update(CourseTables::ACTIVITY, ['settings' => json_encode($settings + $activity->settings, \JSON_THROW_ON_ERROR)], ['id' => $activity->id->value]);
    }
}
