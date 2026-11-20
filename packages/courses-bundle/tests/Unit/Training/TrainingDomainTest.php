<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Training;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CoursesBundle\Application\Training\Recertification;
use Phil\CoursesBundle\Application\Training\TrainingSettings;
use Phil\CoursesBundle\Application\Training\Transcript;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Model\Assignment;
use Phil\CoursesBundle\Domain\Model\Obligation;
use Phil\CoursesBundle\Domain\Model\TranscriptEntry;
use Phil\CoursesBundle\Domain\Training\AssigneeKind;
use Phil\CoursesBundle\Domain\Training\CreditUnit;
use Phil\CoursesBundle\Domain\Training\ObligationState;
use Phil\CoursesBundle\Domain\Training\ReminderStage;
use Phil\CoursesBundle\Domain\Training\TrainingNotice;
use Phil\CoursesBundle\Domain\ValueObject\AssignmentId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\Credits;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\TranscriptEntryId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The values of the mandatory training: credits, assignments, obligations with their states, reminders and renewals,
 * the notices, the renewal dates, the settings and the transcript with its exports.
 */
#[CoversClass(Credits::class)]
#[CoversClass(Assignment::class)]
#[CoversClass(Obligation::class)]
#[CoversClass(TranscriptEntry::class)]
#[CoversClass(TrainingNotice::class)]
#[CoversClass(Recertification::class)]
#[CoversClass(TrainingSettings::class)]
#[CoversClass(Transcript::class)]
#[CoversClass(AssignmentId::class)]
#[CoversClass(TranscriptEntryId::class)]
final class TrainingDomainTest extends TestCase
{
    public function testCreditsAreExactHundredths(): void
    {
        self::assertSame([150, '1.5'], [Credits::of('1.5', CreditUnit::Hours)->hundredths, Credits::of('1.5', CreditUnit::Hours)->amount()]);
        self::assertSame(['10', '0.25', '0.05', '9999.99', '0.01', '1.1'], [Credits::of('10', CreditUnit::Ceu)->amount(), Credits::of('0.25', CreditUnit::Ceu)->amount(), Credits::of('0.05', CreditUnit::Ceu)->amount(), Credits::of('9999.99', CreditUnit::Ceu)->amount(), Credits::hundredths(1, CreditUnit::Ceu)->amount(), Credits::of('1.10', CreditUnit::Ceu)->amount()]);
        self::assertSame(CreditUnit::Ceu, Credits::of('2', CreditUnit::Ceu)->unit);
        self::assertSame(['123.4', '0'], [Credits::write(12_340), Credits::write(0)]);

        foreach (['', '1.', '.5', '1.234', '-1', '10000', 'one', '1,5'] as $amount) {
            try {
                Credits::of($amount, CreditUnit::Hours);
                self::fail('Accepted: '.$amount);
            } catch (InvalidCourse $invalidCourse) {
                self::assertSame('Credits are written as a number of two decimals at most, such as "1.5".', $invalidCourse->getMessage());
            }
        }

        foreach ([0, Credits::MAX_HUNDREDTHS + 1] as $hundredths) {
            try {
                Credits::hundredths($hundredths, CreditUnit::Hours);
                self::fail('Accepted: '.$hundredths);
            } catch (InvalidCourse $invalidCourse) {
                self::assertSame('Credits are from 0.01 to 9999.99.', $invalidCourse->getMessage());
            }
        }

        $this->expectException(InvalidCourse::class);
        Credits::of('0.00', CreditUnit::Hours);
    }

    public function testAnAssignmentIsChecked(): void
    {
        $at = new DateTimeImmutable('2026-11-13 10:00:00', new DateTimeZone('America/Toronto'));
        $assignment = new Assignment(AssignmentId::generate(), CourseId::generate(), AssigneeKind::Group, 'safety-team', $at, $at, 120, LearnerId::of('grace'));
        self::assertSame(['+00:00', '15:00', 120, 'grace', 'safety-team'], [$assignment->dueAt->format('P'), $assignment->assignedAt->format('H:i'), $assignment->renewMonths, $assignment->assignedBy?->value, $assignment->assignee]);
        self::assertSame(1, new Assignment(AssignmentId::generate(), CourseId::generate(), AssigneeKind::Learner, 'ada', $at, $at, 1)->renewMonths);

        foreach ([
            ['ada@example.org', $at, 12, 'An assignee identifier'],
            ['ada', $at, 0, 'A course is renewed after 1 to 120 months.'],
            ['ada', $at, 121, 'A course is renewed after 1 to 120 months.'],
            ['ada', $at->modify('-1 second'), null, 'The due date of an assignment is not before the assignment.'],
        ] as [$assignee, $due, $months, $reason]) {
            try {
                new Assignment(AssignmentId::generate(), CourseId::generate(), AssigneeKind::Learner, $assignee, $due, $at, $months);
                self::fail('Accepted: '.$reason);
            } catch (InvalidCourse $invalidCourse) {
                self::assertStringStartsWith($reason, $invalidCourse->getMessage());
            }
        }
    }

    public function testAnObligationMovesThroughItsStatesAndReminders(): void
    {
        $due = new DateTimeImmutable('2026-12-01 05:00:00 UTC');
        $obligation = new Obligation(AssignmentId::generate(), CourseId::generate(), LearnerId::of('ada'), $due, new DateTimeImmutable('2026-11-01 00:00:00', new DateTimeZone('America/Toronto')));
        self::assertSame(['+00:00', 1, null, null, []], [$obligation->assignedAt->format('P'), $obligation->cycle, $obligation->completedAt, $obligation->renewsAt, $obligation->reminders]);

        foreach ([
            ['2026-11-24 04:59:59', ObligationState::Pending, null],
            ['2026-11-24 05:00:00', ObligationState::DueSoon, ReminderStage::BeforeDue],
            ['2026-11-30 04:59:59', ObligationState::DueSoon, ReminderStage::BeforeDue],
            ['2026-11-30 05:00:00', ObligationState::DueSoon, ReminderStage::AtDue],
            ['2026-12-01 04:59:59', ObligationState::DueSoon, ReminderStage::AtDue],
            ['2026-12-01 05:00:00', ObligationState::Overdue, ReminderStage::Overdue],
        ] as [$moment, $state, $stage]) {
            $now = new DateTimeImmutable($moment.' UTC');
            self::assertSame([$state, $stage], [$obligation->state($now, 7), $obligation->stage($now, 7)], $moment);
        }

        self::assertTrue($obligation->remind(ReminderStage::BeforeDue));
        self::assertFalse($obligation->remind(ReminderStage::BeforeDue));
        self::assertTrue($obligation->remind(ReminderStage::Overdue));
        self::assertSame([ReminderStage::BeforeDue, ReminderStage::Overdue], $obligation->reminders);
        self::assertFalse($obligation->renew(30));
        self::assertFalse($obligation->isRenewable(new DateTimeImmutable('2030-01-01')));

        $completedAt = new DateTimeImmutable('2026-11-20 12:00:00', new DateTimeZone('America/Toronto'));
        self::assertTrue($obligation->complete($completedAt, new DateTimeImmutable('2027-11-20 17:00:00', new DateTimeZone('America/Toronto'))));
        self::assertFalse($obligation->complete($completedAt->modify('+1 day'), null));
        self::assertSame(['2026-11-20 17:00', '2027-11-20 22:00'], [$obligation->completedAt?->format('Y-m-d H:i'), $obligation->renewsAt?->format('Y-m-d H:i')]);
        self::assertSame([ObligationState::Completed, null], [$obligation->state(new DateTimeImmutable('2027-01-01'), 7), $obligation->stage(new DateTimeImmutable('2027-01-01'), 7)]);
        self::assertFalse($obligation->isRenewable(new DateTimeImmutable('2027-11-20 21:59:59 UTC')));
        self::assertTrue($obligation->isRenewable(new DateTimeImmutable('2027-11-20 22:00:00 UTC')));

        self::assertTrue($obligation->renew(30));
        $renewed = clone $obligation;
        self::assertSame([2, '2027-12-20 22:00', null, null, []], [$renewed->cycle, $renewed->dueAt->format('Y-m-d H:i'), $renewed->completedAt, $renewed->renewsAt, $renewed->reminders]);
        self::assertSame(ObligationState::Pending, $obligation->state(new DateTimeImmutable('2027-12-13 21:59:59 UTC'), 7));
        self::assertSame(ObligationState::DueSoon, $obligation->state(new DateTimeImmutable('2027-12-13 22:00:00 UTC'), 7));
    }

    public function testANoticeFollowsTheStage(): void
    {
        self::assertSame([TrainingNotice::DueSoon, TrainingNotice::Due, TrainingNotice::Overdue], array_map(TrainingNotice::of(...), ReminderStage::cases()));
    }

    public function testACompletionIsRenewedMonthsLater(): void
    {
        self::assertSame('2027-11-20T17:00:00+00:00', Recertification::renewsAt(new DateTimeImmutable('2026-11-20 17:00:00 UTC'), 12)->format(\DATE_ATOM));
        self::assertSame('2027-02-20T12:00:00-05:00', Recertification::renewsAt(new DateTimeImmutable('2026-11-20 12:00:00', new DateTimeZone('America/Toronto')), 3)->format(\DATE_ATOM));
        self::assertSame('2027-03-31T00:00:00+00:00', Recertification::renewsAt(new DateTimeImmutable('2027-01-31 00:00:00 UTC'), 1)->format(\DATE_ATOM));

        $this->expectException(InvalidCourse::class);
        $this->expectExceptionMessageIsOrContains('A completion is renewed before the end of the year 9999.');
        Recertification::renewsAt(new DateTimeImmutable('9999-06-01 00:00:00 UTC'), 12);
    }

    public function testTheSettingsAreBounded(): void
    {
        $settings = new TrainingSettings();
        self::assertSame([7, 30, 10_000, 500], [$settings->reminderDays, $settings->graceDays, $settings->maxMembers, $settings->batch]);
        self::assertSame([1, 1, 1, 1], array_values((array) new TrainingSettings(1, 1, 1, 1)));
        self::assertSame([90, 365], [new TrainingSettings(90, 365)->reminderDays, new TrainingSettings(90, 365)->graceDays]);

        foreach ([[0, 30, 1, 1], [91, 30, 1, 1], [7, 0, 1, 1], [7, 366, 1, 1], [7, 30, 0, 1], [7, 30, 1, 0]] as $values) {
            try {
                new TrainingSettings(...$values);
                self::fail('Accepted: '.implode(', ', $values));
            } catch (InvalidCourse $invalidCourse) {
                self::assertStringStartsWith(0 === $values[0] || 91 === $values[0] || 0 === $values[1] || 366 === $values[1] ? 'A reminder comes 1 to 90 days' : 'The members of an assignment', $invalidCourse->getMessage());
            }
        }
    }

    public function testATranscriptAddsUpItsCreditsAndIsExported(): void
    {
        $learner = LearnerId::of('ada');
        $assignment = AssignmentId::generate();
        $first = CourseId::generate();
        $transcript = new Transcript($learner, [
            new TranscriptEntry(TranscriptEntryId::of('e2'), $learner, $first, '=HYPERLINK("x")', new DateTimeImmutable('2026-11-20 12:00:00', new DateTimeZone('America/Toronto')), Credits::of('1.5', CreditUnit::Hours), $assignment, 2),
            new TranscriptEntry(TranscriptEntryId::of('e1'), $learner, $first, 'Knots, "advanced"', new DateTimeImmutable('2026-11-01 00:00:00 UTC'), Credits::of('0.75', CreditUnit::Hours)),
            new TranscriptEntry(TranscriptEntryId::of('e0'), $learner, $first, "Line\nbreak", new DateTimeImmutable('2026-10-01 00:00:00 UTC'), Credits::of('2', CreditUnit::Ceu)),
            new TranscriptEntry(TranscriptEntryId::of('e3'), $learner, $first, '-1+1', new DateTimeImmutable('2026-09-01 00:00:00 UTC')),
        ]);

        self::assertSame([225, 200], [$transcript->total(CreditUnit::Hours), $transcript->total(CreditUnit::Ceu)]);
        self::assertSame(0, new Transcript($learner, [])->total(CreditUnit::Hours));
        $array = $transcript->toArray();
        self::assertSame(['ceu' => '2', 'hours' => '2.25'], $array['totals']);
        self::assertSame([], new Transcript($learner, [])->toArray()['totals']);
        self::assertSame(['course' => $first->value, 'title' => '=HYPERLINK("x")', 'completed_at' => '2026-11-20T17:00:00+00:00', 'credits' => '1.5', 'unit' => 'hours', 'assignment' => $assignment->value, 'cycle' => 2], $array['entries'][0]);
        self::assertSame([null, null, null, null], [$array['entries'][3]['credits'], $array['entries'][3]['unit'], $array['entries'][3]['assignment'], $array['entries'][3]['cycle']]);
        self::assertSame('ada', $array['learner']);

        self::assertSame(
            "course,title,completed_at,credits,unit,assignment,cycle\r\n"
            .$first->value.",\"'=HYPERLINK(\"\"x\"\")\",2026-11-20T17:00:00+00:00,1.5,hours,".$assignment->value.",2\r\n"
            .$first->value.",\"Knots, \"\"advanced\"\"\",2026-11-01T00:00:00+00:00,0.75,hours,,\r\n"
            .$first->value.",\"Line\nbreak\",2026-10-01T00:00:00+00:00,2,ceu,,\r\n"
            .$first->value.",'-1+1,2026-09-01T00:00:00+00:00,,,,\r\n",
            $transcript->csv(),
        );

        foreach (['+1' => "'+1", '@x' => "'@x", "\tx" => "'\tx", "\rx" => "\"'\rx\"", 'a=b' => 'a=b'] as $title => $cell) {
            $line = explode("\r\n", new Transcript($learner, [new TranscriptEntry(TranscriptEntryId::of('e9'), $learner, $first, $title, new DateTimeImmutable('2026-09-01 00:00:00 UTC'))])->csv(), 2)[1];
            self::assertSame($first->value.','.$cell.',2026-09-01T00:00:00+00:00,,,,', substr($line, 0, -2), $title);
        }
    }
}
