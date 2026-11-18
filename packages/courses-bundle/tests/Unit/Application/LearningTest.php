<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CoursesBundle\Application\ActivityStatus;
use Phil\CoursesBundle\Application\Availabilities;
use Phil\CoursesBundle\Application\Enrollments;
use Phil\CoursesBundle\Application\LaunchWindow;
use Phil\CoursesBundle\Application\Learning;
use Phil\CoursesBundle\Application\RetryReview;
use Phil\CoursesBundle\Application\SecondChances;
use Phil\CoursesBundle\Domain\Event\ActivityProgressed;
use Phil\CoursesBundle\Domain\Event\ActivitySatisfied;
use Phil\CoursesBundle\Domain\Event\CourseCompleted;
use Phil\CoursesBundle\Domain\Event\LearnerEnrolled;
use Phil\CoursesBundle\Domain\Event\RetryGranted;
use Phil\CoursesBundle\Domain\Event\RetryRefused;
use Phil\CoursesBundle\Domain\Event\RetryRequested;
use Phil\CoursesBundle\Domain\Event\RetryWaived;
use Phil\CoursesBundle\Domain\Exception\ActivityUnavailable;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Exception\UnknownRecord;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Module;
use Phil\CoursesBundle\Domain\Model\RetryRequest;
use Phil\CoursesBundle\Domain\Progress\AvailabilityStatus;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;
use Phil\CoursesBundle\Domain\Progress\RetryDecision;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\ModuleId;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;
use Phil\CoursesBundle\Domain\ValueObject\Release;
use Phil\CoursesBundle\Domain\ValueObject\RetryRequestId;
use Phil\CoursesBundle\Domain\ValueObject\Score;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Enrollments::class)]
#[CoversClass(Availabilities::class)]
#[CoversClass(ActivityStatus::class)]
#[CoversClass(Learning::class)]
#[CoversClass(SecondChances::class)]
#[CoversClass(RetryReview::class)]
#[CoversClass(RetryWaived::class)]
#[CoversClass(ActivityUnavailable::class)]
final class LearningTest extends TestCase
{
    public function testALearnerEnrollsOnceTakesTheActivitiesAndCompletesTheCourse(): void
    {
        $store = new Store();
        $course = $store->courses->create('Safety');
        $module = $store->courses->addModule($course->id, 'Basics');
        $page = $store->courses->addActivity($module->id, 'Read me', 'page', ['body' => 'Hello']);
        $optional = $store->courses->addActivity($module->id, 'Extra', 'page', [], CompletionRequirement::Completed, false);
        $glance = $store->courses->addActivity($module->id, 'Glance', 'page', [], CompletionRequirement::NotApplicable);
        $learner = LearnerId::of('ada');

        $enrollment = $store->enrollments->enroll($course->id, $learner);
        self::assertSame($enrollment->id->value, $store->enrollments->enroll($course->id, $learner)->id->value);
        self::assertSame($enrollment->id->value, $store->enrollments->of($course->id, $learner)?->id->value);
        self::assertSame($enrollment->id->value, $store->enrollments->get($enrollment->id)->id->value);
        self::assertCount(1, $store->events->of(LearnerEnrolled::class));
        self::assertSame(['ada', $course->id->value], [$store->events->of(LearnerEnrolled::class)[0]->learner, $store->events->of(LearnerEnrolled::class)[0]->course]);
        self::assertSame(0, $store->enrollments->percent($enrollment));

        self::assertSame(LaunchWindow::Page, $store->learning->launch($enrollment, $page->id)->window);
        self::assertNotNull($store->enrollments->progresses($enrollment)[$page->id->value]->startedAt);
        self::assertFalse($store->enrollments->progresses($enrollment)[$page->id->value]->isSatisfied());

        $store->learning->launch($enrollment, $glance->id);
        self::assertTrue($store->enrollments->progresses($enrollment)[$glance->id->value]->isSatisfied());
        self::assertSame(50, $store->enrollments->percent($enrollment));

        $store->clock->modify('+1 hour');
        $store->learning->complete($enrollment, $page->id);
        self::assertSame(100, $store->enrollments->percent($enrollment));
        self::assertTrue($store->enrollments->get($enrollment->id)->isCompleted());
        self::assertSame('2026-11-13T11:00:00+00:00', $store->enrollments->get($enrollment->id)->completedAt?->format(\DATE_ATOM));
        self::assertCount(1, $store->events->of(CourseCompleted::class));
        self::assertSame([$glance->id->value, $page->id->value], array_map(static fn (ActivitySatisfied $event): string => $event->activity, $store->events->of(ActivitySatisfied::class)));
        self::assertFalse($store->events->of(ActivitySatisfied::class)[0]->waived);
        self::assertCount(3, $store->events->of(ActivityProgressed::class));

        $store->learning->complete($enrollment, $optional->id);
        self::assertCount(1, $store->events->of(CourseCompleted::class));
        self::assertSame(AvailabilityStatus::Satisfied, $store->availabilities->of($enrollment, $page->id)->availability->status);
        self::assertSame(LaunchWindow::Page, $store->learning->launch($enrollment, $page->id)->window);
    }

    public function testACourseWithoutRequiredActivitiesIsDoneOnlyOnceCompleted(): void
    {
        $store = new Store();
        $course = $store->courses->create('Optional');
        $store->courses->addActivity($store->courses->addModule($course->id, 'M')->id, 'A', 'page', [], CompletionRequirement::Completed, false);
        $enrollment = $store->enrollments->enroll($course->id, LearnerId::of('ada'));

        self::assertSame(0, $store->enrollments->percent($enrollment));
        $enrollment->complete($store->clock->now());
        self::assertSame(100, $store->enrollments->percent($enrollment));
    }

    public function testAnUnknownCourseOrEnrollmentIsReported(): void
    {
        $store = new Store();

        try {
            $store->enrollments->enroll(CourseId::of('c'), LearnerId::of('ada'));
            self::fail('The enrollment is accepted.');
        } catch (UnknownRecord $unknownRecord) {
            self::assertSame('No course "c" is known.', $unknownRecord->getMessage());
        }

        $this->expectException(UnknownRecord::class);
        $this->expectExceptionMessageIsOrContains('No enrollment "e" is known.');

        $store->enrollments->get(EnrollmentId::of('e'));
    }

    public function testReleasesOpenInTheSiteTimeZone(): void
    {
        $store = new Store();
        $course = $store->courses->create('Drip');
        $later = $store->courses->addModule($course->id, 'Week 2', Release::daysAfterEnrollment(7));
        $now = $store->courses->addModule($course->id, 'Now');
        $inModule = $store->courses->addActivity($later->id, 'Week 2 lesson', 'page');
        $dated = $store->courses->addActivity($now->id, 'Dated', 'page', [], CompletionRequirement::Completed, true, null, Release::on(new DateTimeImmutable('2026-12-01 00:00:00 UTC')));
        $both = $store->courses->addActivity($later->id, 'Later still', 'page', [], CompletionRequirement::Completed, true, null, Release::daysAfterEnrollment(9));
        $enrollment = $store->enrollments->enroll($course->id, LearnerId::of('ada'));

        $statuses = $store->availabilities->outline($enrollment);
        self::assertSame(['Week 2 lesson', 'Later still', 'Dated'], array_map(static fn (ActivityStatus $status): string => $status->activity->title, $statuses));
        self::assertSame(AvailabilityStatus::Scheduled, $statuses[0]->availability->status);
        // Enrolled on the 13th at 05:00 in Toronto: day 7 opens at midnight of the 20th there.
        self::assertSame('2026-11-20T05:00:00+00:00', $statuses[0]->availability->opensAt?->format(\DATE_ATOM));
        self::assertSame('2026-11-22T05:00:00+00:00', $statuses[1]->availability->opensAt?->format(\DATE_ATOM));
        self::assertSame('2026-12-01T00:00:00+00:00', $statuses[2]->availability->opensAt?->format(\DATE_ATOM));
        self::assertSame('Week 2', $statuses[0]->module->title);
        self::assertNull($statuses[0]->progress);

        try {
            $store->learning->launch($enrollment, $inModule->id);
            self::fail('The activity is launched.');
        } catch (ActivityUnavailable $activityUnavailable) {
            self::assertSame('This activity is not open: it is scheduled.', $activityUnavailable->getMessage());
        }

        $store->clock->modify('2026-11-20 05:00:00 UTC');
        self::assertSame(AvailabilityStatus::Open, $store->availabilities->of($enrollment, $inModule->id)->availability->status);
        self::assertSame(AvailabilityStatus::Scheduled, $store->availabilities->of($enrollment, $both->id)->availability->status);
        self::assertSame(AvailabilityStatus::Scheduled, $store->availabilities->of($enrollment, $dated->id)->availability->status);
    }

    public function testAnImposedOrderLocksUntilTheEarlierRequiredActivityIsSatisfied(): void
    {
        $store = new Store();
        $course = $store->courses->create('Ordered', '', true);
        $module = $store->courses->addModule($course->id, 'M');
        $optional = $store->courses->addActivity($module->id, 'Optional', 'page', [], CompletionRequirement::Completed, false);
        $first = $store->courses->addActivity($module->id, 'First', 'page');
        $second = $store->courses->addActivity($module->id, 'Second', 'page');
        $third = $store->courses->addActivity($module->id, 'Third', 'page');
        $enrollment = $store->enrollments->enroll($course->id, LearnerId::of('ada'));

        $statuses = $store->availabilities->outline($enrollment);
        self::assertSame([AvailabilityStatus::Open, AvailabilityStatus::Open, AvailabilityStatus::Locked, AvailabilityStatus::Locked], array_map(static fn (ActivityStatus $status): AvailabilityStatus => $status->availability->status, $statuses));
        self::assertSame($first->id->value, $statuses[2]->availability->waitingFor?->value);
        self::assertSame($first->id->value, $statuses[3]->availability->waitingFor?->value);

        try {
            $store->learning->complete($enrollment, $second->id);
            self::fail('The activity is completed.');
        } catch (ActivityUnavailable $activityUnavailable) {
            self::assertSame('This activity is not open: it is locked.', $activityUnavailable->getMessage());
        }

        $store->learning->complete($enrollment, $first->id);
        self::assertSame($second->id->value, $store->availabilities->of($enrollment, $third->id)->availability->waitingFor?->value);
        self::assertSame(AvailabilityStatus::Open, $store->availabilities->of($enrollment, $second->id)->availability->status);
        self::assertSame(AvailabilityStatus::Open, $store->availabilities->of($enrollment, $optional->id)->availability->status);

        $course->imposeOrder(false);
        $store->courseStore->save($course);
        self::assertSame(AvailabilityStatus::Open, $store->availabilities->of($enrollment, $third->id)->availability->status);
    }

    public function testAnActivityOutsideTheCourseOrOfAMissingModuleIsUnknown(): void
    {
        $store = new Store();
        $course = $store->courses->create('C');
        $store->courses->addActivity($store->courses->addModule($course->id, 'M')->id, 'A', 'page');
        $store->courseStore->saveActivity(new Activity(ActivityId::of('orphan'), $course->id, ModuleId::of('gone'), 'Orphan', 'page', 0));

        $enrollment = $store->enrollments->enroll($course->id, LearnerId::of('ada'));

        self::assertCount(1, $store->availabilities->outline($enrollment));

        $this->expectException(UnknownRecord::class);
        $this->expectExceptionMessageIsOrContains('No activity "orphan" is known.');

        $store->availabilities->of($enrollment, ActivityId::of('orphan'));
    }

    public function testAnOutlineOfAGoneCourseIsUnknown(): void
    {
        $store = new Store();
        $course = $store->courses->create('C');
        $enrollment = $store->enrollments->enroll($course->id, LearnerId::of('ada'));
        $store->courses->remove($course->id);

        $this->expectException(UnknownRecord::class);

        $store->availabilities->outline($enrollment);
    }

    public function testAContentReportedActivityIsNotMarkedDoneByHand(): void
    {
        $store = new Store();
        $course = $store->courses->create('C');
        $unit = $store->courses->addActivity($store->courses->addModule($course->id, 'M')->id, 'Unit', 'cmi5', ['au_id' => 'https://example.org/au', 'url' => 'https://example.net/au/']);
        $enrollment = $store->enrollments->enroll($course->id, LearnerId::of('ada'));

        $this->expectException(ActivityUnavailable::class);
        $this->expectExceptionMessageIsOrContains('This activity reports its own completion.');

        $store->learning->complete($enrollment, $unit->id);
    }

    public function testALearnerOutOfTriesAsksForASecondChanceWhichIsGrantedOrRefused(): void
    {
        $store = new Store();
        $course = $store->courses->create('Exam', '', true);
        $module = $store->courses->addModule($course->id, 'M');
        $exam = $store->courses->addActivity($module->id, 'Exam', 'page', [], CompletionRequirement::Passed, true, 1);
        $after = $store->courses->addActivity($module->id, 'After', 'page');
        $enrollment = $store->enrollments->enroll($course->id, LearnerId::of('ada'));

        try {
            $store->secondChances->request($enrollment, $exam->id, 'Too early');
            self::fail('The request is accepted.');
        } catch (ActivityUnavailable $activityUnavailable) {
            self::assertSame('A second chance is asked for once every try is failed.', $activityUnavailable->getMessage());
        }

        $store->enrollments->record($enrollment, $exam, Outcome::failed(Score::scaled(0.2)));
        self::assertSame(AvailabilityStatus::RetryNeeded, $store->availabilities->of($enrollment, $exam->id)->availability->status);
        self::assertSame(AvailabilityStatus::Locked, $store->availabilities->of($enrollment, $after->id)->availability->status);

        $request = $store->secondChances->request($enrollment, $exam->id, 'I was ill.');
        self::assertSame(AvailabilityStatus::RetryRequested, $store->availabilities->of($enrollment, $exam->id)->availability->status);
        self::assertSame([$request->id->value], array_map(static fn (RetryRequest $waiting): string => $waiting->id->value, $store->secondChances->waiting()));
        self::assertSame([$request->id->value, $enrollment->id->value, $exam->id->value], [$store->events->of(RetryRequested::class)[0]->request, $store->events->of(RetryRequested::class)[0]->enrollment, $store->events->of(RetryRequested::class)[0]->activity]);

        try {
            $store->secondChances->request($enrollment, $exam->id, 'Again');
            self::fail('The request is accepted twice.');
        } catch (ActivityUnavailable $activityUnavailable) {
            self::assertSame('A second chance is asked for already.', $activityUnavailable->getMessage());
        }

        $granted = $store->secondChances->grant($request->id, 'teacher-1');
        self::assertSame(RetryDecision::Granted, $granted->decision);
        self::assertSame('teacher-1', $store->events->of(RetryGranted::class)[0]->decidedBy);
        self::assertSame(1, $store->enrollmentStore->progress($enrollment->id, $exam->id)?->extraTries);
        self::assertSame(AvailabilityStatus::Open, $store->availabilities->of($enrollment, $exam->id)->availability->status);
        self::assertSame([], $store->secondChances->waiting());

        try {
            $store->secondChances->refuse($request->id, 'teacher-2');
            self::fail('The request is decided twice.');
        } catch (InvalidCourse $invalidCourse) {
            self::assertSame(\sprintf('The request "%s" is decided already.', $request->id->value), $invalidCourse->getMessage());
        }

        self::assertSame('', $store->retryStore->find($request->id)?->note);

        $store->enrollments->record($enrollment, $exam, Outcome::failed());
        $again = $store->secondChances->request($enrollment, $exam->id, 'Once more');
        self::assertSame(RetryDecision::Refused, $store->secondChances->refuse($again->id, 'teacher-2')->decision);
        self::assertSame('teacher-2', $store->events->of(RetryRefused::class)[0]->decidedBy);
        self::assertSame(AvailabilityStatus::RetryNeeded, $store->availabilities->of($enrollment, $exam->id)->availability->status);

        $store->enrollments->waive($enrollment, $exam);
        self::assertSame(AvailabilityStatus::Satisfied, $store->availabilities->of($enrollment, $exam->id)->availability->status);
        self::assertTrue(array_last($store->events->of(ActivitySatisfied::class))?->waived);
        self::assertSame(AvailabilityStatus::Open, $store->availabilities->of($enrollment, $after->id)->availability->status);
    }

    public function testADecisionNamesAKnownRequestAndATeacher(): void
    {
        $store = new Store();

        try {
            $store->secondChances->grant(RetryRequestId::of('r'), 'teacher-1');
            self::fail('The request is granted.');
        } catch (UnknownRecord $unknownRecord) {
            self::assertSame('No request "r" is known.', $unknownRecord->getMessage());
        }

        $this->expectException(InvalidCourse::class);
        $this->expectExceptionMessageIsOrContains('A teacher identifier is 1 to 190');

        $store->secondChances->refuse(RetryRequestId::of('r'), 'teacher@example.org');
    }

    public function testTheWaitingRequestsArePagedTheOldestFirst(): void
    {
        $store = new Store();
        $course = $store->courses->create('Exam');
        $exam = $store->courses->addActivity($store->courses->addModule($course->id, 'M')->id, 'Exam', 'page', [], CompletionRequirement::Passed, true, 1);
        $ids = [];

        foreach (['ada', 'bob', 'cy'] as $name) {
            $enrollment = $store->enrollments->enroll($course->id, LearnerId::of($name));
            $store->enrollments->record($enrollment, $exam, Outcome::failed());
            $ids[] = $store->secondChances->request($enrollment, $exam->id, $name)->id->value;
            $store->clock->modify('+1 minute');
        }

        self::assertSame($ids, array_map(static fn (RetryRequest $request): string => $request->id->value, $store->secondChances->waiting()));
        self::assertSame([$ids[0]], array_map(static fn (RetryRequest $request): string => $request->id->value, $store->secondChances->waiting(0)));
        self::assertCount(3, $store->secondChances->waiting(1000));
    }

    public function testAWaiverDecidesARequestAndSatisfiesTheActivityWithTheTeachersNote(): void
    {
        $store = new Store();
        $course = $store->courses->create('Exam', '', true);
        $module = $store->courses->addModule($course->id, 'M');
        $exam = $store->courses->addActivity($module->id, 'Exam', 'page', [], CompletionRequirement::Passed, true, 2);
        $after = $store->courses->addActivity($module->id, 'After', 'page');
        $enrollment = $store->enrollments->enroll($course->id, LearnerId::of('ada'));
        $store->enrollments->record($enrollment, $exam, Outcome::failed());
        $store->enrollments->record($enrollment, $exam, Outcome::failed());

        $request = $store->secondChances->request($enrollment, $exam->id, 'I was ill.');

        $waived = $store->secondChances->waive($request->id, 'teacher-1', '  Done at the workshop. ');
        self::assertSame([RetryDecision::Waived, 'Done at the workshop.', 'teacher-1'], [$waived->decision, $waived->note, $waived->decidedBy]);
        self::assertSame('Done at the workshop.', $store->retryStore->find($request->id)?->note);
        $event = $store->events->of(RetryWaived::class)[0];
        self::assertSame([$request->id->value, $enrollment->id->value, $exam->id->value, 'teacher-1'], [$event->request, $event->enrollment, $event->activity, $event->decidedBy]);
        self::assertTrue($store->enrollmentStore->progress($enrollment->id, $exam->id)?->waived);
        self::assertSame(AvailabilityStatus::Open, $store->availabilities->of($enrollment, $after->id)->availability->status);
        self::assertSame([], $store->secondChances->review());

        $store->enrollments->record($enrollment, $after, Outcome::failed());
        $graceEnrollment = $store->enrollments->enroll($course->id, LearnerId::of('grace'));
        $store->enrollments->record($graceEnrollment, $exam, Outcome::failed());
        $store->enrollments->record($graceEnrollment, $exam, Outcome::failed());

        $second = $store->secondChances->request($graceEnrollment, $exam->id, '');
        self::assertSame('Kept.', $store->secondChances->grant($second->id, 'teacher-1', 'Kept.')->note);

        $store->enrollments->record($graceEnrollment, $exam, Outcome::failed());
        $third = $store->secondChances->request($graceEnrollment, $exam->id, '');
        self::assertSame('No.', $store->secondChances->refuse($third->id, 'teacher-1', 'No.')->note);

        try {
            $store->secondChances->waive($third->id, 'teacher-1');
            self::fail('The request is decided twice.');
        } catch (InvalidCourse $invalidCourse) {
            self::assertStringContainsString('is decided already', $invalidCourse->getMessage());
        }

        self::assertFalse($store->enrollmentStore->progress($graceEnrollment->id, $exam->id)?->waived);
        self::assertCount(1, $store->events->of(RetryWaived::class));
    }

    public function testTheReviewOfTheRequestsNamesTheCourseTheActivityAndTheTries(): void
    {
        $store = new Store();
        $course = $store->courses->create('Exam');
        $module = $store->courses->addModule($course->id, 'M');
        $limited = $store->courses->addActivity($module->id, 'Limited', 'page', [], CompletionRequirement::Passed, true, 1);
        $enrollment = $store->enrollments->enroll($course->id, LearnerId::of('ada'));
        $store->enrollments->record($enrollment, $limited, Outcome::failed());
        $first = $store->secondChances->request($enrollment, $limited->id, 'I was ill.');
        $store->clock->modify('+1 minute');
        $grace = $store->enrollments->enroll($course->id, LearnerId::of('grace'));
        $store->enrollments->record($grace, $limited, Outcome::failed());
        $second = $store->secondChances->request($grace, $limited->id, '');
        $store->secondChances->grant($second->id, 'teacher-1');
        $store->enrollments->record($grace, $limited, Outcome::failed());

        $third = $store->secondChances->request($grace, $limited->id, 'Again');
        $store->retryStore->save(new RetryRequest(RetryRequestId::of('gone-enrollment'), EnrollmentId::of('gone'), $limited->id, '', $store->clock->now()));
        $store->retryStore->save(new RetryRequest(RetryRequestId::of('gone-activity'), $enrollment->id, ActivityId::of('gone'), '', $store->clock->now()));

        $reviews = $store->secondChances->review();
        self::assertSame([$first->id->value, $third->id->value], array_map(static fn (RetryReview $review): string => $review->request->id->value, $reviews));
        self::assertSame(['Exam', 'Limited', 'ada', 1, 1], [$reviews[0]->course->title, $reviews[0]->activity->title, $reviews[0]->enrollment->learner->value, $reviews[0]->failures(), $reviews[0]->tries()]);
        self::assertSame([2, 2], [$reviews[1]->failures(), $reviews[1]->tries()]);
        self::assertCount(1, $store->secondChances->review(1));

        $open = $store->courses->addActivity($module->id, 'Open', 'page');
        self::assertNull(new RetryReview($first, $enrollment, $course, $open, null)->tries());
        self::assertSame(0, new RetryReview($first, $enrollment, $course, $open, null)->failures());
        self::assertSame(1, new RetryReview($first, $enrollment, $course, $limited, null)->tries());
    }

    public function testAModuleOfAnotherCourseIsLeftOut(): void
    {
        $store = new Store();
        $course = $store->courses->create('C');
        $store->courseStore->saveModule(new Module(ModuleId::of('mx'), CourseId::of('other'), 'Other', 0));
        $store->courses->addActivity($store->courses->addModule($course->id, 'M')->id, 'A', 'page');

        self::assertCount(1, $store->availabilities->outline($store->enrollments->enroll($course->id, LearnerId::of('ada'))));
    }
}
