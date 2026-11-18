<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use LogicException;
use Phil\CoursesBundle\Domain\Cmi5\Cmi5Verb;
use Phil\CoursesBundle\Domain\Cmi5\SessionState;
use Phil\CoursesBundle\Domain\Course\CourseOrigin;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Block;
use Phil\CoursesBundle\Domain\Model\Course;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\LaunchSession;
use Phil\CoursesBundle\Domain\Model\Module;
use Phil\CoursesBundle\Domain\Model\Progress;
use Phil\CoursesBundle\Domain\Model\RetryRequest;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;
use Phil\CoursesBundle\Domain\Progress\RetryDecision;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\BlockId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\LaunchSessionId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\ModuleId;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;
use Phil\CoursesBundle\Domain\ValueObject\Release;
use Phil\CoursesBundle\Domain\ValueObject\RetryRequestId;
use Phil\CoursesBundle\Domain\ValueObject\Score;
use Phil\ElearningPackages\PackageFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Course::class)]
#[CoversClass(Module::class)]
#[CoversClass(Activity::class)]
#[CoversClass(Block::class)]
#[CoversClass(Enrollment::class)]
#[CoversClass(Progress::class)]
#[CoversClass(RetryRequest::class)]
#[CoversClass(LaunchSession::class)]
#[CoversClass(CompletionRequirement::class)]
#[CoversClass(SessionState::class)]
#[CoversClass(Cmi5Verb::class)]
final class ModelsTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-11-13 05:00:00-05:00');
    }

    public function testACourseKeepsItsValuesInUtc(): void
    {
        $course = new Course(CourseId::of('c1'), ' Navigation ', $this->now, ' Charts ', CourseOrigin::Cmi5, 'https://example.org/c', true);

        self::assertSame('Navigation', $course->title);
        self::assertSame('Charts', $course->description);
        self::assertSame('2026-11-13T10:00:00+00:00', $course->createdAt->format(\DATE_ATOM));
        self::assertSame(CourseOrigin::Cmi5, $course->origin);
        self::assertSame('https://example.org/c', $course->source);
        self::assertTrue($course->ordered);

        $course->rename('Tides', 'Tables');
        $course->imposeOrder(false);
        self::assertSame('Tides', $course->title);
        self::assertSame('Tables', $course->description);
        self::assertFalse($course->ordered);

        $default = new Course(CourseId::of('c2'), 'A', $this->now);
        self::assertSame('', $default->description);
        self::assertSame(CourseOrigin::Native, $default->origin);
        self::assertNull($default->source);
        self::assertFalse($default->ordered);
        self::assertSame(str_repeat('é', 10_000), new Course(CourseId::of('c3'), 'A', $this->now, str_repeat('é', 10_000))->description);
        self::assertSame(str_repeat('s', 255), new Course(CourseId::of('c3'), 'A', $this->now, '', CourseOrigin::Native, str_repeat('s', 255))->source);
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function wrongCourses(): iterable
    {
        $now = new DateTimeImmutable('2026-11-13 10:00 UTC');

        yield 'long description' => [static fn (): Course => new Course(CourseId::of('c'), 'A', $now, str_repeat('é', 10_001)), 'A description is 10000 characters at most.'];
        yield 'renamed long' => [static fn () => new Course(CourseId::of('c'), 'A', $now)->rename('A', str_repeat('é', 10_001)), 'A description is 10000 characters at most.'];
        yield 'empty source' => [static fn (): Course => new Course(CourseId::of('c'), 'A', $now, '', CourseOrigin::Native, ''), 'The source of a course is 1 to 255 bytes.'];
        yield 'long source' => [static fn (): Course => new Course(CourseId::of('c'), 'A', $now, '', CourseOrigin::Native, str_repeat('s', 256)), 'The source of a course is 1 to 255 bytes.'];
        yield 'activity tries' => [static fn (): Activity => self::activity(maxAttempts: 0), 'An activity allows 1 to 100 tries, or no limit.'];
        yield 'activity many tries' => [static fn (): Activity => self::activity(maxAttempts: 101), 'An activity allows 1 to 100 tries, or no limit.'];
        yield 'activity required tries' => [static fn () => self::activity()->require(CompletionRequirement::Passed, true, 0), 'An activity allows 1 to 100 tries, or no limit.'];
        yield 'activity required many tries' => [static fn () => self::activity()->require(CompletionRequirement::Passed, true, 101), 'An activity allows 1 to 100 tries, or no limit.'];
        yield 'activity empty source' => [static fn (): Activity => self::activity(source: ''), 'The source of an activity is 1 to 255 bytes.'];
        yield 'activity long source' => [static fn (): Activity => self::activity(source: str_repeat('s', 256)), 'The source of an activity is 1 to 255 bytes.'];
        yield 'activity type' => [static fn (): Activity => new Activity(ActivityId::of('a'), CourseId::of('c'), ModuleId::of('m'), 'A', 'a type', 0), 'An activity type identifier is 1 to 64'];
        yield 'activity list setting' => [static fn (): Activity => self::activity(['a' => ['b']]), 'The settings of an activity are named scalar values.'];
        yield 'activity unnamed setting' => [static fn (): Activity => self::activity(['b']), 'The settings of an activity are named scalar values.'];
        yield 'activity empty name' => [static fn (): Activity => self::activity(['' => 'b']), 'The settings of an activity are named scalar values.'];
        yield 'activity long settings' => [static fn (): Activity => self::activity(['body' => str_repeat('a', 65_536)]), 'The settings of an activity are 65536 bytes at most, in JSON.'];
        yield 'block title' => [static fn (): Block => new Block(BlockId::of('b'), CourseId::of('c'), ' ', 0, 'https://example.org/b'), 'A title is 1 to 255 characters.'];
        yield 'block empty source' => [static fn (): Block => new Block(BlockId::of('b'), CourseId::of('c'), 'B', 0, ''), 'The source of a block is 1 to 255 bytes.'];
        yield 'block long source' => [static fn (): Block => new Block(BlockId::of('b'), CourseId::of('c'), 'B', 0, str_repeat('s', 256)), 'The source of a block is 1 to 255 bytes.'];
        yield 'note' => [static fn () => new RetryRequest(RetryRequestId::of('r'), EnrollmentId::of('e'), ActivityId::of('a'), '', $now)->decide(RetryDecision::Granted, 't', $now, str_repeat('é', 2001)), 'The note of a decision is 2000 characters at most.'];
        yield 'note kept' => [static fn (): RetryRequest => new RetryRequest(RetryRequestId::of('r'), EnrollmentId::of('e'), ActivityId::of('a'), '', $now, RetryDecision::Refused, $now, 't', str_repeat('é', 2001)), 'The note of a decision is 2000 characters at most.'];
        yield 'reason' => [static fn (): RetryRequest => new RetryRequest(RetryRequestId::of('r'), EnrollmentId::of('e'), ActivityId::of('a'), str_repeat('é', 2001), $now), 'The reason of a request is 2000 characters at most.'];
    }

    /**
     * @param callable(): mixed $build
     */
    #[DataProvider('wrongCourses')]
    public function testAWrongValueIsRefused(callable $build, string $message): void
    {
        $this->expectException(InvalidCourse::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    public function testAModuleIsScheduled(): void
    {
        $module = new Module(ModuleId::of('m'), CourseId::of('c'), ' Basics ', 2);

        self::assertSame('Basics', $module->title);
        self::assertSame(2, $module->position);
        self::assertTrue($module->release->isImmediate());

        $module->schedule(Release::daysAfterEnrollment(2));
        self::assertSame(2, $module->release->days);
    }

    public function testAnActivityKeepsItsSettingsAndRequirement(): void
    {
        $activity = self::activity(['path' => 'a/b.html', 'score' => 0.5, 'flag' => true, 'none' => null, 'count' => 3], source: 'item-1');

        self::assertSame('Saws', $activity->title);
        self::assertSame('scorm', $activity->type);
        self::assertSame('a/b.html', $activity->setting('path'));
        self::assertSame('a/b.html', $activity->stringSetting('path'));
        self::assertNull($activity->stringSetting('score'));
        self::assertNull($activity->stringSetting('missing'));
        self::assertNull($activity->setting('missing'));
        self::assertSame(0.5, $activity->setting('score'));
        self::assertSame('item-1', $activity->source);
        self::assertSame(CompletionRequirement::Completed, $activity->requirement);
        self::assertTrue($activity->required);
        self::assertNull($activity->maxAttempts);
        self::assertTrue($activity->release->isImmediate());

        $activity->require(CompletionRequirement::Passed, false, 3);
        $activity->schedule(Release::daysAfterEnrollment(1));
        self::assertSame(CompletionRequirement::Passed, $activity->requirement);
        self::assertFalse($activity->required);
        self::assertSame(3, $activity->maxAttempts);
        self::assertSame(1, $activity->release->days);
        self::assertSame(100, self::activity(maxAttempts: 100)->maxAttempts);
        self::assertSame(1, self::activity(maxAttempts: 1)->maxAttempts);
        self::assertSame(str_repeat('s', 255), self::activity(source: str_repeat('s', 255))->source);
        self::assertCount(1, self::activity(['body' => str_repeat('a', 65_524)])->settings);
    }

    public function testAnEnrollmentHasARegistrationAndCompletesOnce(): void
    {
        $enrollment = new Enrollment(EnrollmentId::of('e'), CourseId::of('c'), LearnerId::of('ada'), $this->now);

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $enrollment->registration);
        self::assertSame('2026-11-13T10:00:00+00:00', $enrollment->enrolledAt->format(\DATE_ATOM));
        self::assertFalse($enrollment->isCompleted());
        self::assertTrue($enrollment->complete($this->now->modify('+1 day')));
        self::assertFalse($enrollment->complete($this->now->modify('+2 days')));
        self::assertSame('2026-11-14T10:00:00+00:00', $enrollment->completedAt?->format(\DATE_ATOM));
        self::assertTrue($enrollment->isCompleted());

        $kept = new Enrollment(EnrollmentId::of('e'), CourseId::of('c'), LearnerId::of('ada'), $this->now, 'reg', $this->now);
        self::assertSame('reg', $kept->registration);
        self::assertSame('+00:00', $kept->completedAt?->format('P'));
    }

    /**
     * @return iterable<string, array{CompletionRequirement, bool, ?bool, bool}>
     */
    public static function requirements(): iterable
    {
        foreach ([[false, null], [true, null], [false, true], [false, false], [true, true], [true, false]] as [$completed, $success]) {
            $label = ($completed ? 'completed' : 'not completed').' '.var_export($success, true);
            yield 'Completed '.$label => [CompletionRequirement::Completed, $completed, $success, $completed];
            yield 'Passed '.$label => [CompletionRequirement::Passed, $completed, $success, true === $success];
            yield 'CompletedAndPassed '.$label => [CompletionRequirement::CompletedAndPassed, $completed, $success, $completed && true === $success];
            yield 'CompletedOrPassed '.$label => [CompletionRequirement::CompletedOrPassed, $completed, $success, $completed || true === $success];
            yield 'NotApplicable '.$label => [CompletionRequirement::NotApplicable, $completed, $success, true];
        }
    }

    #[DataProvider('requirements')]
    public function testARequirementIsMetAsCmi5Says(CompletionRequirement $requirement, bool $completed, ?bool $success, bool $met): void
    {
        self::assertSame($met, $requirement->isMetBy($completed, $success));
    }

    public function testProgressKeepsWhatTheLearnerReached(): void
    {
        $progress = new Progress(EnrollmentId::of('e'), ActivityId::of('a'), $this->now);
        $later = $this->now->modify('+1 hour');

        self::assertNull(new Progress(EnrollmentId::of('e'), ActivityId::of('a'), $this->now)->startedAt);
        self::assertFalse($progress->apply(Outcome::failed(Score::scaled(0.4)), CompletionRequirement::CompletedAndPassed, $this->now));
        self::assertSame('2026-11-13T10:00:00+00:00', $progress->startedAt?->format(\DATE_ATOM));
        self::assertFalse($progress->success);
        self::assertSame(1, $progress->failures);
        self::assertSame(0.4, $progress->score?->scaled);
        self::assertTrue($progress->isOutOfTries(1));
        self::assertFalse($progress->isOutOfTries(2));
        self::assertFalse($progress->isOutOfTries(null));

        $progress->grantTry($later);
        self::assertSame(1, $progress->extraTries);
        self::assertFalse($progress->isOutOfTries(1));
        self::assertSame('2026-11-13T11:00:00+00:00', $progress->updatedAt->format(\DATE_ATOM));

        self::assertFalse($progress->apply(Outcome::passed(Score::scaled(0.3)), CompletionRequirement::CompletedAndPassed, $later));
        self::assertTrue($progress->success);
        self::assertSame(0.4, $progress->score?->scaled);
        self::assertFalse($progress->isOutOfTries(1));
        self::assertFalse($progress->apply(Outcome::failed(), CompletionRequirement::CompletedAndPassed, $later));
        self::assertTrue($progress->success);
        self::assertSame(2, $progress->failures);
        self::assertFalse($progress->isOutOfTries(1));

        self::assertTrue($progress->apply(new Outcome(true, null, Score::scaled(0.9)), CompletionRequirement::CompletedAndPassed, $later));
        self::assertTrue($progress->completed);
        self::assertSame(0.9, $progress->score->scaled);
        self::assertTrue($progress->isSatisfied());
        self::assertSame('2026-11-13T11:00:00+00:00', $progress->satisfiedAt?->format(\DATE_ATOM));
        self::assertFalse($progress->apply(Outcome::completed(), CompletionRequirement::CompletedAndPassed, $later->modify('+1 day')));
        self::assertSame('2026-11-13T10:00:00+00:00', $progress->startedAt?->format(\DATE_ATOM));
        self::assertSame('2026-11-13T11:00:00+00:00', $progress->satisfiedAt->format(\DATE_ATOM));
        self::assertFalse($progress->isOutOfTries(1));
    }

    public function testAWaiverSatisfiesOnce(): void
    {
        $progress = new Progress(EnrollmentId::of('e'), ActivityId::of('a'), $this->now, startedAt: $this->now);

        self::assertTrue($progress->waive($this->now));
        self::assertTrue($progress->waived);
        self::assertFalse($progress->waive($this->now->modify('+1 day')));
        self::assertSame('2026-11-13T10:00:00+00:00', $progress->satisfiedAt?->format(\DATE_ATOM));
        self::assertSame('2026-11-14T10:00:00+00:00', $progress->updatedAt->format(\DATE_ATOM));
        self::assertFalse($progress->satisfy(CompletionRequirement::NotApplicable, $this->now));

        $fresh = new Progress(EnrollmentId::of('e'), ActivityId::of('a'), $this->now);
        $fresh->start($this->now->modify('+1 hour'));
        $fresh->start($this->now->modify('+2 hours'));
        self::assertSame('2026-11-13T11:00:00+00:00', $fresh->startedAt?->format(\DATE_ATOM));
        self::assertSame('2026-11-13T12:00:00+00:00', $fresh->updatedAt->format(\DATE_ATOM));
        self::assertTrue($fresh->satisfy(CompletionRequirement::NotApplicable, $this->now));
        self::assertFalse(new Progress(EnrollmentId::of('e'), ActivityId::of('a'), $this->now)->satisfy(CompletionRequirement::Completed, $this->now));
    }

    public function testARequestIsDecidedOnce(): void
    {
        $request = new RetryRequest(RetryRequestId::of('r'), EnrollmentId::of('e'), ActivityId::of('a'), '  I was ill. ', $this->now);

        self::assertSame('I was ill.', $request->reason);
        self::assertTrue($request->isPending());
        self::assertSame(RetryDecision::Pending, $request->decision);
        self::assertSame('+00:00', $request->requestedAt->format('P'));

        $request->decide(RetryDecision::Granted, 'teacher-1', $this->now);
        self::assertFalse($request->isPending());
        self::assertSame(RetryDecision::Granted, $request->decision);
        self::assertSame('teacher-1', $request->decidedBy);
        self::assertSame('2026-11-13T10:00:00+00:00', $request->decidedAt?->format(\DATE_ATOM));
        self::assertSame(str_repeat('é', 2000), new RetryRequest(RetryRequestId::of('r'), EnrollmentId::of('e'), ActivityId::of('a'), str_repeat('é', 2000), $this->now)->reason);

        $refused = new RetryRequest(RetryRequestId::of('r'), EnrollmentId::of('e'), ActivityId::of('a'), '', $this->now, RetryDecision::Pending, $this->now);
        self::assertSame('+00:00', $refused->decidedAt?->format('P'));
        $refused->decide(RetryDecision::Refused, 'teacher-1', $this->now);
        self::assertSame(RetryDecision::Refused, $refused->decision);

        self::assertSame('', $refused->note);
        $waived = new RetryRequest(RetryRequestId::of('r'), EnrollmentId::of('e'), ActivityId::of('a'), '', $this->now);
        $waived->decide(RetryDecision::Waived, 'teacher-1', $this->now, '  Done elsewhere. ');
        self::assertSame([RetryDecision::Waived, 'Done elsewhere.'], [$waived->decision, $waived->note]);
        self::assertSame(str_repeat('é', 2000), new RetryRequest(RetryRequestId::of('r'), EnrollmentId::of('e'), ActivityId::of('a'), '', $this->now, RetryDecision::Granted, $this->now, 't', str_repeat('é', 2000))->note);

        try {
            new RetryRequest(RetryRequestId::of('r'), EnrollmentId::of('e'), ActivityId::of('a'), '', $this->now)->decide(RetryDecision::Pending, 'teacher-1', $this->now);
            self::fail('A request is decided as pending.');
        } catch (LogicException $logicException) {
            self::assertSame('A request is decided by a grant, a refusal or a waiver.', $logicException->getMessage());
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('The request "r" is decided already.');

        $request->decide(RetryDecision::Refused, 'teacher-2', $this->now);
    }

    public function testABlockKeepsItsPlaceItsParentAndItsSource(): void
    {
        $block = new Block(BlockId::of('b'), CourseId::of('c'), '  Tides ', 3, 'https://example.org/blocks/tides', BlockId::of('p'));

        self::assertSame(['Tides', 3, 'https://example.org/blocks/tides', 'p', 'c'], [$block->title, $block->position, $block->source, $block->parent?->value, $block->course->value]);
        self::assertNull(new Block(BlockId::of('b'), CourseId::of('c'), 'T', 0, str_repeat('s', 255))->parent);
        self::assertSame('b', self::activity(block: BlockId::of('b'))->block?->value);
        self::assertNull(self::activity()->block);
    }

    public function testASessionGivesItsTokenOnceAndEnds(): void
    {
        $session = new LaunchSession(LaunchSessionId::of('s'), EnrollmentId::of('e'), ActivityId::of('a'), $this->now, $this->now->modify('+1 hour'));

        self::assertSame('+00:00', $session->launchedAt->format('P'));
        self::assertSame('2026-11-13T11:00:00+00:00', $session->expiresAt->format(\DATE_ATOM));
        self::assertTrue($session->isUsable($this->now));
        self::assertFalse($session->isUsable($this->now->modify('+1 hour')));
        self::assertTrue($session->fetch());
        self::assertFalse($session->fetch());
        self::assertTrue($session->fetched);

        $session->initialize();
        self::assertSame(SessionState::Initialized, $session->state);
        self::assertTrue($session->isUsable($this->now));

        $session->end(SessionState::Terminated, $this->now);
        self::assertSame(SessionState::Terminated, $session->state);
        self::assertSame('2026-11-13T10:00:00+00:00', $session->endedAt?->format(\DATE_ATOM));
        self::assertFalse($session->isUsable($this->now));
        self::assertSame('+00:00', new LaunchSession(LaunchSessionId::of('s'), EnrollmentId::of('e'), ActivityId::of('a'), $this->now, $this->now, false, SessionState::Abandoned, $this->now)->endedAt?->format('P'));
    }

    public function testTheStatesAndVerbsOfCmi5(): void
    {
        self::assertTrue(SessionState::Launched->isOpen());
        self::assertTrue(SessionState::Initialized->isOpen());
        self::assertFalse(SessionState::Terminated->isOpen());
        self::assertFalse(SessionState::Abandoned->isOpen());

        $platform = array_map(static fn (Cmi5Verb $verb): string => $verb->display(), array_values(array_filter(Cmi5Verb::cases(), static fn (Cmi5Verb $verb): bool => $verb->isIssuedByThePlatform())));
        $movesOn = array_map(static fn (Cmi5Verb $verb): string => $verb->display(), array_values(array_filter(Cmi5Verb::cases(), static fn (Cmi5Verb $verb): bool => $verb->movesOn())));
        self::assertSame(['launched', 'abandoned', 'waived', 'satisfied'], $platform);
        self::assertSame(['completed', 'passed', 'failed'], $movesOn);
    }

    public function testAFormatHasItsOrigin(): void
    {
        self::assertSame(CourseOrigin::Scorm12, CourseOrigin::of(PackageFormat::Scorm12));
        self::assertSame(CourseOrigin::Scorm2004, CourseOrigin::of(PackageFormat::Scorm2004));
        self::assertSame(CourseOrigin::Cmi5, CourseOrigin::of(PackageFormat::Cmi5));
    }

    /**
     * @param array<array-key, mixed> $settings
     */
    private static function activity(array $settings = [], ?int $maxAttempts = null, ?string $source = null, ?BlockId $block = null): Activity
    {
        return new Activity(ActivityId::of('a'), CourseId::of('c'), ModuleId::of('m'), ' Saws ', 'scorm', 0, $settings, CompletionRequirement::Completed, true, $maxAttempts, null, $source, $block);
    }
}
