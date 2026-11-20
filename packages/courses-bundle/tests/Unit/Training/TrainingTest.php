<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Training;

use DateTimeImmutable;
use LogicException;
use Phil\CoursesBundle\Application\Enrollments;
use Phil\CoursesBundle\Application\Training\Assignments;
use Phil\CoursesBundle\Application\Training\TrainingCompletions;
use Phil\CoursesBundle\Application\Training\TrainingReport;
use Phil\CoursesBundle\Application\Training\TrainingRun;
use Phil\CoursesBundle\Application\Training\TrainingStatus;
use Phil\CoursesBundle\Application\Training\Transcripts;
use Phil\CoursesBundle\Domain\Event\CourseCompleted;
use Phil\CoursesBundle\Domain\Event\CreditsGranted;
use Phil\CoursesBundle\Domain\Event\EnrollmentRenewed;
use Phil\CoursesBundle\Domain\Event\ObligationOverdue;
use Phil\CoursesBundle\Domain\Event\ObligationRenewed;
use Phil\CoursesBundle\Domain\Event\TrainingAssigned;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Exception\UnknownRecord;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Assignment;
use Phil\CoursesBundle\Domain\Model\Course;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Training\AssigneeKind;
use Phil\CoursesBundle\Domain\Training\CreditUnit;
use Phil\CoursesBundle\Domain\Training\ObligationState;
use Phil\CoursesBundle\Domain\Training\ReminderStage;
use Phil\CoursesBundle\Domain\ValueObject\AssignmentId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\Credits;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalAssignmentRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalEnrollmentRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalTranscriptRepository;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\DatePoint;

/**
 * The mandatory training over SQLite: courses assigned to learners and groups, the reminders of the runs, the
 * renewals of the recertifications, and the transcripts with their credits.
 */
#[CoversClass(Assignments::class)]
#[CoversClass(TrainingRun::class)]
#[CoversClass(TrainingReport::class)]
#[CoversClass(TrainingStatus::class)]
#[CoversClass(Transcripts::class)]
#[CoversClass(TrainingCompletions::class)]
#[CoversClass(DbalAssignmentRepository::class)]
#[CoversClass(DbalTranscriptRepository::class)]
#[CoversClass(DbalEnrollmentRepository::class)]
#[CoversClass(Enrollments::class)]
final class TrainingTest extends TestCase
{
    private Store $store;

    private Course $course;

    private Activity $page;

    protected function setUp(): void
    {
        $this->store = new Store();
        $this->course = $this->store->courses->create('Fire safety');
        $module = $this->store->courses->addModule($this->course->id, 'Basics');
        $this->page = $this->store->courses->addActivity($module->id, 'Exits', 'page');
    }

    public function testACourseAssignedToALearnerEnrollsAndTellsThem(): void
    {
        $assignment = $this->store->assignments->assign($this->course->id, AssigneeKind::Learner, 'ada', $this->due('+30 days'), 12, LearnerId::of('grace'));

        self::assertEquals($assignment, $this->store->assignments->get($assignment->id));
        self::assertInstanceOf(Enrollment::class, $this->store->enrollments->of($this->course->id, LearnerId::of('ada')));
        self::assertSame(['assigned ada '.$this->course->id->value.' 1'], $this->store->notices->take());
        self::assertSame($assignment->id->value, $this->store->events->of(TrainingAssigned::class)[0]->assignment);
        self::assertSame($this->course->id->value, $this->store->events->of(TrainingAssigned::class)[0]->course);

        $statuses = $this->store->assignments->of(LearnerId::of('ada'));
        self::assertCount(1, $statuses);
        self::assertSame([$this->course->id->value, ObligationState::Pending, 1], [$statuses[0]->course->id->value, $statuses[0]->state, $statuses[0]->obligation->cycle]);
        self::assertSame([], $this->store->assignments->of(LearnerId::of('bob')));
        self::assertSame([$assignment->id->value], array_map(static fn (Assignment $found): string => $found->id->value, $this->store->assignmentStore->ofCourse($this->course->id)));
        self::assertSame([$assignment->id->value], array_map(static fn (Assignment $found): string => $found->id->value, $this->store->assignmentStore->to(AssigneeKind::Learner, 'ada')));
        self::assertSame([], $this->store->assignmentStore->to(AssigneeKind::Group, 'ada'));
        self::assertSame([], $this->store->assignmentStore->ofGroups(10));

        $this->store->assignments->remove($assignment->id);
        self::assertNull($this->store->assignmentStore->find($assignment->id));
        self::assertSame([], $this->store->assignments->of(LearnerId::of('ada')));
        $this->expectException(UnknownRecord::class);
        $this->store->assignments->get($assignment->id);
    }

    public function testAnUnknownCourseOrAMissingAdapterIsRefused(): void
    {
        try {
            $this->store->assignments->assign(CourseId::generate(), AssigneeKind::Learner, 'ada', $this->due('+1 day'));
            self::fail('Assigned.');
        } catch (UnknownRecord $unknownRecord) {
            self::assertStringContainsString('course', $unknownRecord->getMessage());
        }

        try {
            $this->store->assignments->assign($this->course->id, AssigneeKind::Unit, 'north', $this->due('+1 day'));
            self::fail('Assigned.');
        } catch (InvalidCourse $invalidCourse) {
            self::assertSame('No AssigneeMembers service names the members of a unit.', $invalidCourse->getMessage());
        }

        $this->store->members->groups['crew'] = ['ada', 'bob', 'cy', 'dee'];

        try {
            $this->store->assignments->assign($this->course->id, AssigneeKind::Group, 'crew', $this->due('+1 day'));
            self::fail('Assigned.');
        } catch (InvalidCourse $invalidCourse) {
            self::assertSame('An assignment reaches 3 members at most.', $invalidCourse->getMessage());
        }

        self::assertSame([], $this->store->assignmentStore->ofCourse($this->course->id));
        self::assertSame([], $this->store->notices->sent);
    }

    public function testAGroupReachesItsMembersAndItsNewOnesAtEachRun(): void
    {
        $this->store->members->groups['crew'] = ['ada', 'bob', 'ada'];
        $done = $this->store->enrollments->enroll($this->course->id, LearnerId::of('bob'));
        $this->store->enrollments->record($done, $this->page, Outcome::completed());
        $assignment = $this->store->assignments->assign($this->course->id, AssigneeKind::Group, 'crew', $this->due('+30 days'), 12);

        self::assertSame(['assigned ada '.$this->course->id->value.' 1'], $this->store->notices->take());
        $bob = $this->store->assignmentStore->obligation($assignment->id, LearnerId::of('bob'));
        self::assertSame(['2026-11-13 10:00', '2027-11-13 10:00'], [$bob?->completedAt?->format('Y-m-d H:i'), $bob?->renewsAt?->format('Y-m-d H:i')]);
        self::assertSame(ObligationState::Completed, $this->store->assignments->of(LearnerId::of('bob'))[0]->state);
        self::assertSame([$assignment->id->value], array_map(static fn (Assignment $found): string => $found->id->value, $this->store->assignmentStore->ofGroups(10)));
        self::assertSame([], $this->store->assignmentStore->ofGroups(10, 1));

        $this->store->members->groups['crew'] = ['ada', 'bob', 'cy'];
        self::assertSame(1, $this->store->assignments->synchronize($assignment));
        self::assertSame(0, $this->store->assignments->synchronize($assignment));
        self::assertSame(['assigned cy '.$this->course->id->value.' 1'], $this->store->notices->take());
    }

    public function testTheRunRemindsOnceAStageAndTellsWhoAssignedOnceOverdue(): void
    {
        $due = $this->due('+10 days');
        $assignment = $this->store->assignments->assign($this->course->id, AssigneeKind::Learner, 'ada', $due, null, LearnerId::of('grace'));
        $this->store->assignments->assign($this->course->id, AssigneeKind::Learner, 'bob', $due);
        $this->store->notices->take();

        $course = $this->course->id->value;

        self::assertEquals(new TrainingReport(0, 0, 0, 0), $this->store->run->run());
        self::assertSame([], $this->store->assignments->attention());

        $this->store->clock->modify('+3 days');
        self::assertEquals(new TrainingReport(0, 2, 0, 0), $this->store->run->run());
        self::assertEqualsCanonicalizing(['due_soon ada '.$course.' 1', 'due_soon bob '.$course.' 1'], $this->store->notices->take());
        self::assertEquals(new TrainingReport(0, 0, 0, 0), $this->store->run->run());
        self::assertSame([ObligationState::DueSoon, ObligationState::DueSoon], array_map(static fn (TrainingStatus $status): ObligationState => $status->state, $this->store->assignments->attention()));
        self::assertCount(1, $this->store->assignments->attention(1, 1));

        $this->store->clock->modify('+6 days');
        $this->store->run->run();
        self::assertEqualsCanonicalizing(['due ada '.$course.' 1', 'due bob '.$course.' 1'], $this->store->notices->take());

        $this->store->clock->modify('+1 day');
        $this->store->run->run();
        self::assertEqualsCanonicalizing(['overdue ada '.$course.' 1', 'overdue_manager grace '.$course.' 1', 'overdue bob '.$course.' 1'], $this->store->notices->take());
        self::assertCount(2, $this->store->events->of(ObligationOverdue::class));
        self::assertSame([$assignment->id->value, 'ada', $course], [$this->store->events->of(ObligationOverdue::class)[0]->assignment, $this->store->events->of(ObligationOverdue::class)[0]->learner, $this->store->events->of(ObligationOverdue::class)[0]->course]);
        self::assertSame([ReminderStage::BeforeDue, ReminderStage::AtDue, ReminderStage::Overdue], $this->store->assignmentStore->obligation($assignment->id, LearnerId::of('ada'))?->reminders);
        self::assertSame(ObligationState::Overdue, $this->store->assignments->of(LearnerId::of('ada'))[0]->state);
        self::assertEquals(new TrainingReport(0, 0, 0, 0), $this->store->run->run());
    }

    public function testACompletionEntersTheTranscriptAndARecertificationRenewsIt(): void
    {
        $this->store->transcripts->credit($this->course->id, Credits::of('1.5', CreditUnit::Ceu));
        self::assertSame('1.5', $this->store->transcripts->credits($this->course->id)?->amount());
        $assignment = $this->store->assignments->assign($this->course->id, AssigneeKind::Learner, 'ada', $this->due('+30 days'), 12);
        $ada = LearnerId::of('ada');
        $enrollment = $this->store->enrollments->of($this->course->id, $ada) ?? throw new LogicException('Not enrolled.');
        $this->store->clock->modify('+2 days');
        $this->complete($enrollment);

        $obligation = $this->store->assignmentStore->obligation($assignment->id, $ada);
        self::assertSame(['2026-11-15 10:00', '2027-11-15 10:00'], [$obligation?->completedAt?->format('Y-m-d H:i'), $obligation?->renewsAt?->format('Y-m-d H:i')]);
        $transcript = $this->store->transcripts->of($ada);
        self::assertCount(1, $transcript->entries);
        self::assertSame(['Fire safety', '1.5', 'ceu', $assignment->id->value, 1, '2026-11-15 10:00'], [$transcript->entries[0]->courseTitle, $transcript->entries[0]->credits?->amount(), $transcript->entries[0]->credits?->unit->value, $transcript->entries[0]->assignment?->value, $transcript->entries[0]->cycle, $transcript->entries[0]->completedAt->format('Y-m-d H:i')]);
        self::assertSame([$transcript->entries[0]->id->value, 'ada', $this->course->id->value], [$this->store->events->of(CreditsGranted::class)[0]->entry, $this->store->events->of(CreditsGranted::class)[0]->learner, $this->store->events->of(CreditsGranted::class)[0]->course]);

        $this->store->clock->modify('+1 year');
        $this->store->clock->modify('-1 second');
        self::assertEquals(new TrainingReport(0, 0, 0, 0), $this->store->run->run());
        $this->store->notices->take();
        $this->store->clock->modify('+1 second');
        self::assertEquals(new TrainingReport(0, 0, 1, 0), $this->store->run->run());
        self::assertSame(['renewed ada '.$this->course->id->value.' 2'], $this->store->notices->take());
        self::assertSame([$assignment->id->value, 'ada', $this->course->id->value], [$this->store->events->of(ObligationRenewed::class)[0]->assignment, $this->store->events->of(ObligationRenewed::class)[0]->learner, $this->store->events->of(ObligationRenewed::class)[0]->course]);
        $renewed = $this->store->assignmentStore->obligation($assignment->id, $ada);
        self::assertSame([2, '2027-12-15 10:00', null, null], [$renewed?->cycle, $renewed?->dueAt->format('Y-m-d H:i'), $renewed?->completedAt, $renewed?->renewsAt]);
        $reopened = $this->store->enrollments->get($enrollment->id);
        self::assertNull($reopened->completedAt);
        self::assertSame([], $this->store->enrollments->progresses($reopened));
        self::assertSame($enrollment->registration, $reopened->registration);
        self::assertSame([$enrollment->id->value, $this->course->id->value, 'ada'], [$this->store->events->of(EnrollmentRenewed::class)[0]->enrollment, $this->store->events->of(EnrollmentRenewed::class)[0]->course, $this->store->events->of(EnrollmentRenewed::class)[0]->learner]);

        $this->store->transcripts->credit($this->course->id, null);
        self::assertNull($this->store->transcripts->credits($this->course->id));
        $this->complete($reopened);
        $entries = $this->store->transcripts->of($ada)->entries;
        self::assertCount(2, $entries);
        self::assertSame([2, null, 1], [$entries[0]->cycle, $entries[0]->credits, $entries[1]->cycle]);
    }

    public function testARenewalWithoutAnEnrollmentStillOpensTheNextCycle(): void
    {
        $assignment = $this->store->assignments->assign($this->course->id, AssigneeKind::Learner, 'ada', $this->due('+30 days'), 1);
        $obligation = $this->store->assignmentStore->obligation($assignment->id, LearnerId::of('ada')) ?? throw new LogicException('No obligation.');
        $obligation->complete($this->store->clock->now(), $this->store->clock->now()->modify('+1 day'));

        $this->store->assignmentStore->saveObligation($obligation);
        $this->store->connection->delete('phil_course_enrollment', ['learner' => 'ada']);
        $this->store->clock->modify('+1 day');

        self::assertSame(1, $this->store->run->run()->renewed);
        self::assertSame([], $this->store->events->of(EnrollmentRenewed::class));
        self::assertSame(2, $this->store->assignmentStore->obligation($assignment->id, LearnerId::of('ada'))?->cycle);
    }

    public function testTheRunReadsTheObligationsABatchAtATime(): void
    {
        $this->store->members->groups['crew'] = ['ada', 'bob', 'cy'];
        $this->store->assignments->assign($this->course->id, AssigneeKind::Group, 'crew', $this->due('+3 days'));
        $this->store->assignments->assign($this->course->id, AssigneeKind::Group, 'crew', $this->due('+3 days'));
        $this->store->assignments->assign($this->course->id, AssigneeKind::Group, 'crew', $this->due('+3 days'));
        $this->store->connection->insert('phil_course_assignment', ['id' => AssignmentId::generate()->value, 'course_id' => $this->course->id->value, 'kind' => 'unit', 'assignee' => 'north', 'due_at' => '2026-12-01 00:00:00', 'assigned_at' => '2026-11-13 10:00:00']);
        $this->store->notices->take();
        $this->store->members->groups['crew'] = ['ada', 'bob', 'cy', 'dee'];

        // Three assignments of three members, a batch of two: every obligation is read once, in pages of two.
        self::assertEquals(new TrainingReport(0, 9, 0, 4), $this->store->run->run());
        self::assertCount(9, $this->store->notices->take());
        $this->store->members->groups['crew'] = ['ada', 'bob'];
        self::assertEquals(new TrainingReport(0, 0, 0, 1), $this->store->run->run());
    }

    public function testACompletionOfAnEnrollmentGoneOrNotCompletedIsIgnored(): void
    {
        $this->store->completions->__invoke(new CourseCompleted(EnrollmentId::generate()->value, $this->course->id->value, 'ada'));
        $enrollment = $this->store->enrollments->enroll($this->course->id, LearnerId::of('ada'));
        $this->store->completions->__invoke(new CourseCompleted($enrollment->id->value, $this->course->id->value, 'ada'));

        self::assertSame([], $this->store->transcripts->of(LearnerId::of('ada'))->entries);
        $this->expectException(UnknownRecord::class);
        $this->store->transcripts->credit(CourseId::generate(), null);
    }

    public function testATranscriptKeepsTheTitleOfACourseGone(): void
    {
        $enrollment = $this->store->enrollments->enroll($this->course->id, LearnerId::of('ada'));
        $this->store->courses->remove($this->course->id);
        $entry = $this->store->transcripts->record($enrollment, new DateTimeImmutable('2026-11-13 10:00:00 UTC'));

        self::assertSame([$this->course->id->value, null, null, null], [$entry->courseTitle, $entry->credits, $entry->assignment, $entry->cycle]);
        self::assertSame($this->course->id->value, $this->store->transcripts->of(LearnerId::of('ada'))->entries[0]->courseTitle);
        self::assertSame([], $this->store->assignments->of(LearnerId::of('ada')));

        $this->store->transcriptStore->removeOf(LearnerId::of('ada'));
        self::assertSame([], $this->store->transcripts->of(LearnerId::of('ada'))->entries);
    }

    private function complete(Enrollment $enrollment): void
    {
        $this->store->enrollments->record($enrollment, $this->page, Outcome::completed());
        $events = $this->store->events->of(CourseCompleted::class);
        $this->store->completions->__invoke($events[\count($events) - 1]);
    }

    private function due(string $after): DatePoint
    {
        return $this->store->clock->now()->modify($after);
    }
}
