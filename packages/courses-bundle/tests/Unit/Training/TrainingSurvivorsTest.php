<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Training;

use DateTimeImmutable;
use LogicException;
use Override;
use Phil\CoursesBundle\Application\Contract\AssigneeMembers;
use Phil\CoursesBundle\Application\Enrollments;
use Phil\CoursesBundle\Application\Training\Assignments;
use Phil\CoursesBundle\Application\Training\TrainingReport;
use Phil\CoursesBundle\Application\Training\TrainingRun;
use Phil\CoursesBundle\Application\Training\TrainingSettings;
use Phil\CoursesBundle\Application\Training\Transcripts;
use Phil\CoursesBundle\Domain\Event\ObligationOverdue;
use Phil\CoursesBundle\Domain\Event\TrainingAssigned;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Exception\UnknownRecord;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Course;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\Obligation;
use Phil\CoursesBundle\Domain\Model\TranscriptEntry;
use Phil\CoursesBundle\Domain\Training\AssigneeKind;
use Phil\CoursesBundle\Domain\Training\CreditUnit;
use Phil\CoursesBundle\Domain\Training\ReminderStage;
use Phil\CoursesBundle\Domain\ValueObject\AssignmentId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\Credits;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;
use Phil\CoursesBundle\Domain\ValueObject\TranscriptEntryId;
use Phil\CoursesBundle\Infrastructure\Doctrine\CourseTables;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalAssignmentRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalEnrollmentRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalTranscriptRepository;
use Phil\CoursesBundle\Infrastructure\Privacy\TrainingPrivacy;
use Phil\CoursesBundle\Tests\Unit\Fixture\Json;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\DatePoint;

/**
 * The details of the mandatory training that its scenarios do not reach: the run without a notifier or a dispatcher,
 * the bounds of the pages, the rows a removal leaves to the others, and the values the stores read back.
 */
#[CoversClass(Assignments::class)]
#[CoversClass(TrainingRun::class)]
#[CoversClass(TrainingReport::class)]
#[CoversClass(Transcripts::class)]
#[CoversClass(Enrollments::class)]
#[CoversClass(DbalAssignmentRepository::class)]
#[CoversClass(DbalTranscriptRepository::class)]
#[CoversClass(DbalEnrollmentRepository::class)]
#[CoversClass(TrainingPrivacy::class)]
#[CoversClass(Obligation::class)]
#[CoversClass(Credits::class)]
#[CoversClass(CourseTables::class)]
final class TrainingSurvivorsTest extends TestCase
{
    private Store $store;

    private Course $course;

    private Activity $page;

    protected function setUp(): void
    {
        $this->store = new Store();
        $this->course = $this->store->courses->create('Fire safety');
        $this->page = $this->store->courses->addActivity($this->store->courses->addModule($this->course->id, 'Basics')->id, 'Exits', 'page');
    }

    public function testARunReportStartsAtZero(): void
    {
        $report = new TrainingReport();

        self::assertSame([0, 0, 0, 0], [$report->reached, $report->reminded, $report->renewed, $report->skipped]);
    }

    public function testTheTrainingRunsWithoutANotifierOrADispatcher(): void
    {
        $store = $this->store;
        $enrollments = new Enrollments($store->courseStore, $store->enrollmentStore, $store->clock);
        $assignments = new Assignments($store->assignmentStore, $store->courseStore, $enrollments, $store->training, $store->clock, [$store->members]);
        $run = new TrainingRun($store->assignmentStore, $assignments, $store->enrollmentStore, $enrollments, $store->training, $store->clock);
        $transcripts = new Transcripts($store->transcriptStore, $store->courseStore);

        $assignments->assign($this->course->id, AssigneeKind::Learner, 'ada', $this->due('+1 day'), null, LearnerId::of('grace'));
        $bob = $assignments->assign($this->course->id, AssigneeKind::Learner, 'bob', $this->due('+90 days'), 1);
        $enrollment = $this->enrollmentOf('bob');
        $store->enrollments->record($enrollment, $this->page, Outcome::completed());
        $completed = $assignments->completed($enrollment, $store->clock->now());
        $entry = $transcripts->record($enrollment, $store->clock->now(), $completed);

        self::assertCount(1, $completed);
        self::assertSame($bob->id->value, $entry->assignment?->value);

        $store->clock->modify('+2 days');
        self::assertEquals(new TrainingReport(0, 1, 0, 0), $run->run());

        $store->clock->modify('+1 month');
        self::assertEquals(new TrainingReport(0, 0, 1, 0), $run->run());
        self::assertNull($store->enrollments->get($enrollment->id)->completedAt);
        self::assertSame([], $store->notices->sent);
        self::assertSame([], $store->events->of(ObligationOverdue::class));
    }

    public function testTheOneWhoAssignedIsNotToldWhenTheAssignmentIsGone(): void
    {
        $assignment = $this->store->assignments->assign($this->course->id, AssigneeKind::Learner, 'ada', $this->due('+1 day'), null, LearnerId::of('grace'));
        $this->store->notices->take();
        $this->store->connection->delete(CourseTables::ASSIGNMENT, ['id' => $assignment->id->value]);
        $this->store->clock->modify('+2 days');

        self::assertSame(1, $this->store->run->run()->reminded);
        self::assertSame(['overdue ada '.$this->course->id->value.' 1'], $this->store->notices->take());
        self::assertCount(1, $this->store->events->of(ObligationOverdue::class));
    }

    public function testEveryAssignmentAndEveryRenewalOfARunCounts(): void
    {
        $this->store->members->groups['a'] = ['ada'];
        $this->store->members->groups['b'] = ['bob'];
        $this->store->assignments->assign($this->course->id, AssigneeKind::Group, 'a', $this->due('+90 days'));
        $this->store->assignments->assign($this->course->id, AssigneeKind::Group, 'b', $this->due('+90 days'));
        $this->store->members->groups['a'] = ['ada', 'cy'];
        $this->store->members->groups['b'] = ['bob', 'dee'];

        self::assertEquals(new TrainingReport(2, 0, 0, 0), $this->store->run->run());

        foreach (['eve', 'fay'] as $learner) {
            $assignment = $this->store->assignments->assign($this->course->id, AssigneeKind::Learner, $learner, $this->due('+90 days'), 1);
            $obligation = $this->store->assignmentStore->obligation($assignment->id, LearnerId::of($learner)) ?? throw new LogicException('No obligation.');
            $obligation->complete($this->store->clock->now(), $this->store->clock->now()->modify('+1 day'));
            $this->store->assignmentStore->saveObligation($obligation);
        }

        $this->store->clock->modify('+1 day');
        self::assertEquals(new TrainingReport(0, 0, 2, 0), $this->store->run->run());
    }

    public function testAnUnknownCourseLeavesNothingBehind(): void
    {
        try {
            $this->store->assignments->assign(CourseId::generate(), AssigneeKind::Learner, 'ada', $this->due('+1 day'));
            self::fail('Assigned.');
        } catch (UnknownRecord) {
            self::assertSame(0, Store::count($this->store->connection, 'SELECT COUNT(*) FROM '.CourseTables::ASSIGNMENT));
            self::assertSame([], $this->store->events->of(TrainingAssigned::class));
        }
    }

    public function testAnAdapterThatDoesNotSupportTheKindIsPassedOver(): void
    {
        $unsupported = new class implements AssigneeMembers {
            #[Override]
            public function supports(AssigneeKind $kind): bool
            {
                return false;
            }

            #[Override]
            public function members(AssigneeKind $kind, string $assignee): iterable
            {
                return [];
            }
        };
        $assignments = new Assignments($this->store->assignmentStore, $this->store->courseStore, $this->store->enrollments, $this->store->training, $this->store->clock, [$unsupported, $this->store->members]);
        $this->store->members->groups['crew'] = ['ada'];

        $assignments->assign($this->course->id, AssigneeKind::Group, 'crew', $this->due('+30 days'));

        self::assertCount(1, $this->store->assignments->of(LearnerId::of('ada')));
    }

    public function testACompletionCompletesEveryObligationUnderTheCourse(): void
    {
        $this->store->assignments->assign($this->course->id, AssigneeKind::Learner, 'ada', $this->due('+30 days'), 12);
        $this->store->assignments->assign($this->course->id, AssigneeKind::Learner, 'ada', $this->due('+60 days'), 6);

        $enrollment = $this->enrollmentOf('ada');
        $this->store->enrollments->record($enrollment, $this->page, Outcome::completed());

        self::assertCount(2, $this->store->assignments->completed($enrollment, $this->store->clock->now()));
        self::assertSame([], $this->store->assignments->completed($enrollment, $this->store->clock->now()));
    }

    public function testThePagesHoldTheirBoundsAndTheirDefaults(): void
    {
        $settings = new TrainingSettings(7, 30, 300, 500);
        $assignments = new Assignments($this->store->assignmentStore, $this->store->courseStore, $this->store->enrollments, $settings, $this->store->clock, [$this->store->members]);
        $this->store->members->groups['all'] = array_map(static fn (int $number): string => 'learner'.$number, range(1, 201));
        $this->store->members->groups['some'] = ['ada'];
        $assignments->assign($this->course->id, AssigneeKind::Group, 'all', $this->due('+3 days'));
        $assignments->assign($this->course->id, AssigneeKind::Group, 'some', $this->due('+3 days'));

        $now = $this->store->clock->now();
        $horizon = $now->modify('+7 days');

        self::assertCount(200, $assignments->attention());
        self::assertCount(2, $assignments->attention(10, 200));
        self::assertCount(202, $this->store->assignmentStore->dueBy($horizon, 500));
        self::assertCount(1, $this->store->assignmentStore->dueBy($horizon, 0));
        self::assertCount(1, $this->store->assignmentStore->dueBy($horizon, 1));
        self::assertCount(1, $this->store->assignmentStore->ofGroups(0));
        self::assertCount(1, $this->store->assignmentStore->ofGroups(1));
        self::assertCount(2, $this->store->assignmentStore->ofGroups(5));
        self::assertCount(1, $this->store->assignmentStore->actionable($horizon, $now, 0));
        self::assertCount(1, $this->store->assignmentStore->actionable($horizon, $now, 1));
    }

    public function testARemovalLeavesTheRowsOfTheOthers(): void
    {
        $ada = $this->store->assignments->assign($this->course->id, AssigneeKind::Learner, 'ada', $this->due('+30 days'));
        $bob = $this->store->assignments->assign($this->course->id, AssigneeKind::Learner, 'bob', $this->due('+30 days'));

        $this->store->assignments->remove($ada->id);

        self::assertNull($this->store->assignmentStore->find($ada->id));
        self::assertSame([], $this->store->assignmentStore->obligationsOf(LearnerId::of('ada')));
        self::assertNotNull($this->store->assignmentStore->find($bob->id));
        self::assertCount(1, $this->store->assignmentStore->obligationsOf(LearnerId::of('bob')));
    }

    public function testRenewingAnEnrollmentForgetsItsProgressAndItsSatisfactionsOnly(): void
    {
        $adaEnrollment = $this->store->enrollments->enroll($this->course->id, LearnerId::of('ada'));
        $bobEnrollment = $this->store->enrollments->enroll($this->course->id, LearnerId::of('bob'));
        $this->store->enrollments->record($adaEnrollment, $this->page, Outcome::completed());
        $this->store->enrollments->record($bobEnrollment, $this->page, Outcome::completed());

        $now = $this->store->clock->now();
        $this->store->enrollmentStore->claimSatisfaction($adaEnrollment->id, 'unit-1', 'statement-1', $now);
        $this->store->enrollmentStore->claimSatisfaction($bobEnrollment->id, 'unit-1', 'statement-2', $now);

        $this->store->enrollments->renew($adaEnrollment);

        self::assertSame([], $this->store->enrollmentStore->satisfactions($adaEnrollment->id));
        self::assertSame(['unit-1'], $this->store->enrollmentStore->satisfactions($bobEnrollment->id));
        self::assertSame([], $this->store->enrollments->progresses($adaEnrollment));
        self::assertCount(1, $this->store->enrollments->progresses($bobEnrollment));
    }

    public function testAnEnrollmentRenewsWithoutADispatcher(): void
    {
        $enrollments = new Enrollments($this->store->courseStore, $this->store->enrollmentStore, $this->store->clock);
        $enrollment = $enrollments->enroll($this->course->id, LearnerId::of('ada'));

        $enrollments->renew($enrollment);

        self::assertNull($enrollments->get($enrollment->id)->completedAt);
    }

    public function testTheTranscriptsAndTheCreditsOfOthersStayAfterARemoval(): void
    {
        $other = $this->store->courses->create('First aid');
        $transcripts = new Transcripts($this->store->transcriptStore, $this->store->courseStore);
        $transcripts->credit($this->course->id, Credits::of('1', CreditUnit::Ceu));
        $transcripts->credit($other->id, Credits::of('2', CreditUnit::Ceu));

        $transcripts->credit($this->course->id, null);

        self::assertNull($transcripts->credits($this->course->id));
        self::assertSame('2', $transcripts->credits($other->id)?->amount());

        $transcripts->record($this->store->enrollments->enroll($this->course->id, LearnerId::of('ada')), $this->store->clock->now());
        $transcripts->record($this->store->enrollments->enroll($this->course->id, LearnerId::of('bob')), $this->store->clock->now());

        $this->store->transcriptStore->removeOf(LearnerId::of('ada'));

        self::assertSame([], $transcripts->of(LearnerId::of('ada'))->entries);
        self::assertCount(1, $transcripts->of(LearnerId::of('bob'))->entries);
    }

    public function testTheStoresReadBackTheValuesTheyHold(): void
    {
        $assignment = $this->store->assignments->assign($this->course->id, AssigneeKind::Learner, 'ada', $this->due('+30 days'));
        $ada = LearnerId::of('ada');
        $this->store->connection->executeStatement('UPDATE '.CourseTables::OBLIGATION.' SET cycle = 0');
        self::assertSame(1, $this->store->assignmentStore->obligation($assignment->id, $ada)?->cycle);

        $this->store->connection->executeStatement('UPDATE '.CourseTables::OBLIGATION." SET reminders = 'before_due,bogus,overdue'");
        self::assertSame([ReminderStage::BeforeDue, ReminderStage::Overdue], $this->store->assignmentStore->obligation($assignment->id, $ada)->reminders);

        $credits = Credits::of('1.5', CreditUnit::Ceu);
        $entries = [];

        foreach ([null, $credits, $credits, $credits] as $number => $given) {
            $entries[$number] = new TranscriptEntry(TranscriptEntryId::generate(), $ada, $this->course->id, 'Course '.$number, $this->store->clock->now(), $given, null, 3);
            $this->store->transcriptStore->save($entries[$number]);
        }

        $this->store->connection->executeStatement('UPDATE '.CourseTables::TRANSCRIPT.' SET cycle = 0 WHERE id = ?', [$entries[0]->id->value]);
        $this->store->connection->executeStatement('UPDATE '.CourseTables::TRANSCRIPT.' SET credit_hundredths = NULL WHERE id = ?', [$entries[2]->id->value]);
        $this->store->connection->executeStatement('UPDATE '.CourseTables::TRANSCRIPT." SET credit_unit = 'bogus' WHERE id = ?", [$entries[3]->id->value]);

        $read = [];

        foreach ($this->store->transcriptStore->of($ada) as $entry) {
            $read[$entry->courseTitle] = $entry;
        }

        self::assertSame(1, $read['Course 0']->cycle);
        self::assertSame(3, $read['Course 1']->cycle);
        self::assertSame('1.5', $read['Course 1']->credits?->amount());
        self::assertNull($read['Course 2']->credits);
        self::assertNull($read['Course 3']->credits);
    }

    public function testATitleIsKeptTo255Characters(): void
    {
        $ada = LearnerId::of('ada');
        $long = new TranscriptEntry(TranscriptEntryId::generate(), $ada, $this->course->id, str_repeat('é', 300), $this->store->clock->now());
        $this->store->transcriptStore->save($long);

        self::assertSame(str_repeat('é', 255), $this->store->transcriptStore->of($ada)[0]->courseTitle);
    }

    public function testTheExportGivesTheRemindersAsTheirNames(): void
    {
        $assignment = $this->store->assignments->assign($this->course->id, AssigneeKind::Learner, 'ada', $this->due('+30 days'));
        $obligation = $this->store->assignmentStore->obligation($assignment->id, LearnerId::of('ada')) ?? throw new LogicException('No obligation.');
        $obligation->remind(ReminderStage::BeforeDue);

        $this->store->assignmentStore->saveObligation($obligation);

        $export = new TrainingPrivacy($this->store->assignmentStore, $this->store->transcriptStore)->export(DataSubject::fromString('ada'));

        self::assertSame(['before_due'], Json::at($export, 'obligations', 0, 'reminders'));
        self::assertSame([], new TrainingPrivacy($this->store->assignmentStore, $this->store->transcriptStore)->export(DataSubject::fromString('bob')));
    }

    public function testACompletionWithoutARenewalHasNoRenewalDate(): void
    {
        $now = new DateTimeImmutable('2026-11-13 10:00:00 UTC');
        $obligation = new Obligation(AssignmentId::generate(), $this->course->id, LearnerId::of('ada'), $now->modify('+1 day'), $now);

        self::assertTrue($obligation->complete($now, null));
        self::assertNull($obligation->renewsAt);
    }

    public function testCreditsAreWrittenAsANumberAndNothingAround(): void
    {
        self::assertSame(['1.5', '10', '0.25', '9999.99'], array_map(static fn (string $amount): string => Credits::of($amount, CreditUnit::Ceu)->amount(), ['1.5', '10', '0.25', '9999.99']));
        self::assertSame(1050, Credits::of('10.5', CreditUnit::Ceu)->hundredths);

        foreach (["1.5\n", "10\n", 'x1.5', '1.5x', '.5', '1.', '12345', '1.234'] as $amount) {
            try {
                Credits::of($amount, CreditUnit::Ceu);
                self::fail('Accepted: '.$amount);
            } catch (InvalidCourse $invalidCourse) {
                self::assertSame('Credits are written as a number of two decimals at most, such as "1.5".', $invalidCourse->getMessage());
            }
        }
    }

    private function enrollmentOf(string $learner): Enrollment
    {
        return $this->store->enrollments->of($this->course->id, LearnerId::of($learner)) ?? throw new LogicException('Not enrolled.');
    }

    private function due(string $after): DatePoint
    {
        return $this->store->clock->now()->modify($after);
    }
}
