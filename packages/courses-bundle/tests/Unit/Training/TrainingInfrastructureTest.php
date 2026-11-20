<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Training;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use Phil\CoursesBundle\Application\Handler\RunTrainingHandler;
use Phil\CoursesBundle\Application\Message\RunTraining;
use Phil\CoursesBundle\Application\Training\TrainingReport;
use Phil\CoursesBundle\Domain\Event\CourseCompleted;
use Phil\CoursesBundle\Domain\Model\Obligation;
use Phil\CoursesBundle\Domain\Training\AssigneeKind;
use Phil\CoursesBundle\Domain\Training\CreditUnit;
use Phil\CoursesBundle\Domain\Training\TrainingNotice;
use Phil\CoursesBundle\Domain\ValueObject\AssignmentId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\Credits;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;
use Phil\CoursesBundle\Infrastructure\Notifications\NotificationsTrainingNotifier;
use Phil\CoursesBundle\Infrastructure\Privacy\TrainingPrivacy;
use Phil\CoursesBundle\Infrastructure\Scheduler\TrainingSchedule;
use Phil\CoursesBundle\Tests\Unit\Fixture\Json;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use Phil\NotificationsBundle\Application\Handler\SendNotificationHandler;
use Phil\NotificationsBundle\Application\Message\SendNotification;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\NotificationTypes;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;

/**
 * The adapters of the mandatory training: its notifications through phil/notifications-bundle, identifiers only, its
 * schedule and handler, and its privacy module.
 */
#[CoversClass(NotificationsTrainingNotifier::class)]
#[CoversClass(TrainingSchedule::class)]
#[CoversClass(RunTrainingHandler::class)]
#[CoversClass(RunTraining::class)]
#[CoversClass(TrainingPrivacy::class)]
final class TrainingInfrastructureTest extends TestCase
{
    public function testANoticeIsANotificationOfIdentifiersOnly(): void
    {
        $bus = new class implements MessageBusInterface {
            /** @var list<object> */
            public array $messages = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->messages[] = $message;

                return new Envelope($message);
            }
        };
        $types = array_map(static fn (string $key): NotificationType => new NotificationType($key), array_keys(NotificationsTrainingNotifier::types()));
        $handler = new ReflectionClass(SendNotificationHandler::class)->newInstanceWithoutConstructor();
        $notifier = new NotificationsTrainingNotifier(new Notifications(new NotificationTypes(...$types), $handler, $bus));
        $obligation = new Obligation(AssignmentId::of('a1'), CourseId::of('c1'), LearnerId::of('ada'), new DateTimeImmutable('2026-12-01 04:59:59 UTC'), new DateTimeImmutable('2026-11-01 00:00:00 UTC'), 3);

        $notifier->notify(TrainingNotice::OverdueManager, 'grace', $obligation);

        self::assertCount(1, $bus->messages);
        $message = $bus->messages[0];
        self::assertInstanceOf(SendNotification::class, $message);
        self::assertSame(['courses.training.overdue_manager', ['grace'], 'course', 'c1', ['assignment' => 'a1', 'cycle' => '3', 'due' => '2026-12-01', 'learner' => 'ada']], [$message->type, $message->recipients, $message->subjectType, $message->subjectId, $message->data]);
    }

    public function testTheTypesAreDeclaredOnTheSiteAndByEmail(): void
    {
        $types = NotificationsTrainingNotifier::types();

        self::assertSame(['courses.training.assigned', 'courses.training.due_soon', 'courses.training.due', 'courses.training.overdue', 'courses.training.overdue_manager', 'courses.training.renewed'], array_keys($types));
        self::assertSame(['channels' => ['site', 'email'], 'importance' => 'medium'], $types['courses.training.assigned']);
        self::assertSame(['high', 'high', 'medium'], [$types['courses.training.overdue']['importance'], $types['courses.training.overdue_manager']['importance'], $types['courses.training.renewed']['importance']]);
    }

    public function testTheScheduleRunsEveryDayAtItsTime(): void
    {
        $schedule = new TrainingSchedule('06:30', 'America/Toronto');
        self::assertSame($schedule->getSchedule(), $schedule->getSchedule());
        $messages = $schedule->getSchedule()->getRecurringMessages();
        self::assertCount(1, $messages);
        $trigger = $messages[0]->getTrigger();
        self::assertInstanceOf(PeriodicalTrigger::class, $trigger);
        self::assertSame('2030-01-02T06:30:00-05:00', $trigger->getNextRunDate(new DateTimeImmutable('2030-01-01 12:00:00', new DateTimeZone('America/Toronto')))?->format(\DATE_ATOM));
        $provided = iterator_to_array($messages[0]->getMessages(new MessageContext('phil_courses_training', 'id', $trigger, new DateTimeImmutable())));
        self::assertEquals([new RunTraining()], $provided);
        self::assertSame('06:00', new ReflectionProperty(TrainingSchedule::class, 'at')->getValue(new TrainingSchedule()));
        self::assertSame('2030-01-02T06:00:00+00:00', new TrainingSchedule()->getSchedule()->getRecurringMessages()[0]->getTrigger()->getNextRunDate(new DateTimeImmutable('2030-01-01 12:00:00 UTC'))?->format(\DATE_ATOM));
    }

    public function testTheHandlerRunsTheTraining(): void
    {
        $store = new Store();
        $course = $store->courses->create('Fire safety');
        $store->assignments->assign($course->id, AssigneeKind::Learner, 'ada', $store->clock->now()->modify('+1 day'));

        self::assertEquals(new TrainingReport(0, 1, 0, 0), new RunTrainingHandler($store->run)());
    }

    public function testTheTrainingAndTranscriptOfALearnerAreExportedAndErased(): void
    {
        $store = new Store();
        $course = $store->courses->create('Fire safety');
        $page = $store->courses->addActivity($store->courses->addModule($course->id, 'Basics')->id, 'Exits', 'page');
        $store->transcripts->credit($course->id, Credits::of('2', CreditUnit::Hours));
        $assignment = $store->assignments->assign($course->id, AssigneeKind::Learner, 'ada', $store->clock->now()->modify('+1 day'), 12, LearnerId::of('grace'));
        $store->assignments->assign($course->id, AssigneeKind::Learner, 'grace', $store->clock->now()->modify('+1 day'), null, LearnerId::of('ada'));
        $enrollment = $store->enrollments->of($course->id, LearnerId::of('ada')) ?? throw new LogicException('Not enrolled.');
        $store->enrollments->record($enrollment, $page, Outcome::completed());
        $store->completions->__invoke($store->events->of(CourseCompleted::class)[0]);

        $privacy = new TrainingPrivacy($store->assignmentStore, $store->transcriptStore);
        self::assertSame(TrainingPrivacy::MODULE, $privacy->module()->value);
        $export = $privacy->export(DataSubject::fromString('ada'));
        self::assertSame(['obligations', 'transcript', 'credit_totals'], array_keys($export));
        self::assertSame([[
            'assignment' => $assignment->id->value,
            'course' => $course->id->value,
            'cycle' => 1,
            'assigned_at' => '2026-11-13T10:00:00+00:00',
            'due_at' => '2026-11-14T10:00:00+00:00',
            'completed_at' => '2026-11-13T10:00:00+00:00',
            'renews_at' => '2027-11-13T10:00:00+00:00',
            'reminders' => [],
        ]], $export['obligations']);
        self::assertSame(['hours' => '2'], $export['credit_totals']);
        self::assertSame('Fire safety', Json::at($export, 'transcript', 0, 'title'));
        self::assertSame([], $privacy->export(DataSubject::fromString('bob')));
        self::assertSame([], $privacy->export(DataSubject::fromString('ada@example.org')));
        self::assertSame(['obligations'], array_keys($privacy->export(DataSubject::fromString('grace'))));

        $kept = new TrainingPrivacy($store->assignmentStore, $store->transcriptStore, true);
        self::assertSame(['phil_course_obligation' => ErasureEffect::Deleted, 'phil_course_assignment' => ErasureEffect::Redacted, 'phil_course_transcript' => ErasureEffect::Survives], $kept->erase(DataSubject::fromString('ada'))->effects);
        self::assertSame(['transcript', 'credit_totals'], array_keys($privacy->export(DataSubject::fromString('ada'))));
        self::assertNull($store->assignmentStore->to(AssigneeKind::Learner, 'grace')[0]->assignedBy);
        self::assertSame('grace', $store->assignmentStore->to(AssigneeKind::Learner, 'ada')[0]->assignedBy?->value);

        self::assertSame(['phil_course_obligation' => ErasureEffect::Deleted, 'phil_course_assignment' => ErasureEffect::Redacted, 'phil_course_transcript' => ErasureEffect::Deleted], $privacy->erase(DataSubject::fromString('ada'))->effects);
        self::assertSame([], $privacy->export(DataSubject::fromString('ada')));
        self::assertSame(ErasureEffect::Deleted, $privacy->erase(DataSubject::fromString('ada@example.org'))->effects['phil_course_obligation']);
        self::assertSame(['obligations'], array_keys($privacy->export(DataSubject::fromString('grace'))));
    }
}
