<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CoursesBundle\Domain\Cmi5\SessionState;
use Phil\CoursesBundle\Domain\Course\CourseOrigin;
use Phil\CoursesBundle\Domain\Model\Block;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\LaunchSession;
use Phil\CoursesBundle\Domain\Model\Module;
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
use Phil\CoursesBundle\Infrastructure\Doctrine\CourseTables;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalCourseRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalEnrollmentRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalLaunchSessionRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalLearningRecordStore;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalRetryRequestRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\Rows;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DbalCourseRepository::class)]
#[CoversClass(DbalEnrollmentRepository::class)]
#[CoversClass(DbalRetryRequestRepository::class)]
#[CoversClass(DbalLaunchSessionRepository::class)]
#[CoversClass(DbalLearningRecordStore::class)]
#[CoversClass(Rows::class)]
final class StoresTest extends TestCase
{
    public function testEveryValueIsKeptAndReadBack(): void
    {
        $store = new Store();
        $course = $store->courses->create('Navigation', 'Charts', true);
        $module = $store->courses->addModule($course->id, 'Week 1', Release::on(new DateTimeImmutable('2026-12-01 09:00', new DateTimeZone('America/Toronto'))));
        $activity = $store->courses->addActivity($module->id, 'Symbols', 'scorm', ['path' => 'a.html', 'mastery_score' => 0.8, 'flag' => true, 'count' => 2, 'none' => null], CompletionRequirement::CompletedOrPassed, false, 3, Release::daysAfterEnrollment(2), 'item');

        $readModule = $store->courseStore->module($module->id);
        self::assertSame('2026-12-01T14:00:00+00:00', $readModule?->release->on?->format(\DATE_ATOM));
        self::assertSame('Week 1', $readModule->title);
        self::assertSame(0, $readModule->position);
        self::assertTrue($course->id->equals($readModule->course));

        $read = $store->courseStore->activity($activity->id);
        self::assertSame(['path' => 'a.html', 'mastery_score' => 0.8, 'flag' => true, 'count' => 2, 'none' => null], $read?->settings);
        self::assertSame(CompletionRequirement::CompletedOrPassed, $read->requirement);
        self::assertFalse($read->required);
        self::assertSame(3, $read->maxAttempts);
        self::assertSame(2, $read->release->days);
        self::assertSame('item', $read->source);
        self::assertSame('scorm', $read->type);
        self::assertTrue($module->id->equals($read->module));
        self::assertNull($store->courseStore->module(ModuleId::of('none')));
        self::assertNull($store->courseStore->activity(ActivityId::of('none')));
        self::assertNull($store->courseStore->find(CourseId::of('none')));

        $readCourse = $store->courseStore->find($course->id);
        self::assertSame(['Navigation', 'Charts', true, '2026-11-13T10:00:00+00:00'], [$readCourse?->title, $readCourse?->description, $readCourse?->ordered, $readCourse?->createdAt->format(\DATE_ATOM)]);

        $course->rename('Renamed', '');
        $store->courseStore->save($course);
        $module->schedule(Release::immediately());
        $store->courseStore->saveModule($module);
        self::assertSame('Renamed', $store->courseStore->find($course->id)?->title);
        self::assertTrue($store->courseStore->module($module->id)?->release->isImmediate());
        self::assertSame(1, Store::count($store->connection, 'SELECT COUNT(*) FROM '.CourseTables::COURSE));

        $store->connection->update(CourseTables::ACTIVITY, ['settings' => '{broken', 'requirement' => 'Never'], ['id' => $activity->id->value]);
        $broken = $store->courseStore->activity($activity->id);
        self::assertSame([], $broken?->settings);
        self::assertSame(CompletionRequirement::Completed, $broken->requirement);
        $store->connection->update(CourseTables::ACTIVITY, ['settings' => '"text"'], ['id' => $activity->id->value]);
        self::assertSame([], $store->courseStore->activity($activity->id)?->settings);
        $store->connection->update(CourseTables::COURSE, ['origin' => 'elsewhere'], ['id' => $course->id->value]);
        $elsewhere = $store->courseStore->find($course->id);
        self::assertNotNull($elsewhere);
        self::assertSame('native', $elsewhere->origin->value);
    }

    public function testTheEnrollmentsAndProgressAreKeptAndReadBack(): void
    {
        $store = new Store();
        $course = $store->courses->create('C');
        $activity = $store->courses->addActivity($store->courses->addModule($course->id, 'M')->id, 'A', 'page', [], CompletionRequirement::CompletedAndPassed);
        $other = $store->courses->create('Other');
        $ada = LearnerId::of('ada');
        $first = $store->enrollments->enroll($course->id, $ada);
        $store->clock->modify('+1 minute');
        $second = $store->enrollments->enroll($other->id, $ada);
        $store->enrollments->record($first, $activity, Outcome::failed(Score::scaled(-0.5)));
        $store->enrollments->record($first, $activity, Outcome::passed(Score::scaled(0.25)));

        self::assertSame([$second->id->value, $first->id->value], array_map(static fn (Enrollment $enrollment): string => $enrollment->id->value, $store->enrollmentStore->ofLearner($ada)));
        self::assertSame([], $store->enrollmentStore->ofLearner(LearnerId::of('bob')));
        self::assertNull($store->enrollmentStore->find(EnrollmentId::of('none')));
        self::assertNull($store->enrollmentStore->findFor($course->id, LearnerId::of('bob')));
        $found = $store->enrollmentStore->find($first->id);
        self::assertNotNull($found);
        self::assertSame($first->registration, $found->registration);
        self::assertSame('2026-11-13T10:00:00+00:00', $found->enrolledAt->format(\DATE_ATOM));
        self::assertNull($found->completedAt);

        $progress = $store->enrollmentStore->progress($first->id, $activity->id);
        self::assertNotNull($progress);
        self::assertSame([false, true, false, 0.25, 1, 0], [$progress->completed, $progress->success, $progress->waived, $progress->score?->scaled, $progress->failures, $progress->extraTries]);
        self::assertSame('2026-11-13T10:01:00+00:00', $progress->startedAt?->format(\DATE_ATOM));
        self::assertNull($progress->satisfiedAt);
        self::assertNull($store->enrollmentStore->progress($second->id, $activity->id));

        $store->enrollments->grantTry($first, $activity);
        $store->enrollments->waive($first, $activity);

        $waived = $store->enrollmentStore->progress($first->id, $activity->id);
        self::assertSame([true, 1], [$waived?->waived, $waived?->extraTries]);
        self::assertSame('2026-11-13T10:01:00+00:00', $waived?->satisfiedAt?->format(\DATE_ATOM));
        $completed = $store->enrollmentStore->find($first->id);
        self::assertNotNull($completed);
        self::assertSame('2026-11-13T10:01:00+00:00', $completed->completedAt?->format(\DATE_ATOM));
        self::assertSame([$activity->id->value], array_keys($store->enrollmentStore->progresses($first->id)));

        $store->connection->update(CourseTables::PROGRESS, ['score' => 7, 'success' => null], ['enrollment_id' => $first->id->value]);
        $high = $store->enrollmentStore->progress($first->id, $activity->id);
        self::assertSame(1.0, $high?->score?->scaled);
        self::assertNull($high->success);
        $store->connection->update(CourseTables::PROGRESS, ['score' => -7], ['enrollment_id' => $first->id->value]);
        $low = $store->enrollmentStore->progress($first->id, $activity->id);
        self::assertSame(-1.0, $low?->score?->scaled);
        $store->connection->update(CourseTables::PROGRESS, ['score' => null], ['enrollment_id' => $first->id->value]);
        $none = $store->enrollmentStore->progress($first->id, $activity->id);
        self::assertNull($none?->score);
    }

    public function testTheRequestsAndSessionsAreKeptAndReadBack(): void
    {
        $store = new Store();
        $now = $store->clock->now();
        $request = new RetryRequest(RetryRequestId::of('r1'), EnrollmentId::of('e'), ActivityId::of('a'), 'Ill', $now);
        $store->retryStore->save($request);
        self::assertSame('r1', $store->retryStore->pending(EnrollmentId::of('e'), ActivityId::of('a'))?->id->value);
        self::assertNull($store->retryStore->pending(EnrollmentId::of('e'), ActivityId::of('b')));
        self::assertSame('', $store->retryStore->find($request->id)?->note);
        self::assertNull($store->connection->fetchOne('SELECT note FROM phil_course_retry WHERE id = ?', ['r1']));
        $request->decide(RetryDecision::Granted, 'teacher-1', $now->modify('+1 hour'), 'Once more.');
        $store->retryStore->save($request);

        $read = $store->retryStore->find($request->id);
        self::assertSame(['Ill', RetryDecision::Granted, 'teacher-1', '2026-11-13T11:00:00+00:00', '2026-11-13T10:00:00+00:00', 'Once more.'], [$read?->reason, $read?->decision, $read?->decidedBy, $read?->decidedAt?->format(\DATE_ATOM), $read?->requestedAt->format(\DATE_ATOM), $read?->note]);
        self::assertNull($store->retryStore->pending(EnrollmentId::of('e'), ActivityId::of('a')));
        self::assertNull($store->retryStore->find(RetryRequestId::of('none')));
        self::assertSame([], $store->retryStore->waiting());

        $session = new LaunchSession(LaunchSessionId::of('s1'), EnrollmentId::of('e'), ActivityId::of('a'), $now, $now->modify('+1 hour'));
        $ended = new LaunchSession(LaunchSessionId::of('s2'), EnrollmentId::of('e'), ActivityId::of('a'), $now, $now->modify('+1 hour'));
        $ended->end(SessionState::Terminated, $now);

        $store->sessionStore->save($session);
        $store->sessionStore->save($ended);

        $session->fetch();
        $session->initialize();

        $store->sessionStore->save($session);

        $readSession = $store->sessionStore->find($session->id);
        self::assertSame([true, SessionState::Initialized, '2026-11-13T11:00:00+00:00', null], [$readSession?->fetched, $readSession?->state, $readSession?->expiresAt->format(\DATE_ATOM), $readSession?->endedAt]);
        self::assertSame(['s1'], array_map(static fn (LaunchSession $open): string => $open->id->value, $store->sessionStore->open(EnrollmentId::of('e'), ActivityId::of('a'))));
        self::assertSame('2026-11-13T10:00:00+00:00', $store->sessionStore->find($ended->id)?->endedAt?->format(\DATE_ATOM));
        self::assertNull($store->sessionStore->find(LaunchSessionId::of('none')));

        $store->connection->update(CourseTables::SESSION, ['state' => 'odd'], ['id' => 's1']);
        self::assertSame(SessionState::Abandoned, $store->sessionStore->find($session->id)?->state);
        $store->connection->update(CourseTables::RETRY, ['decision' => 'odd'], ['id' => 'r1']);
        self::assertSame(RetryDecision::Pending, $store->retryStore->find($request->id)?->decision);
    }

    public function testTheStoreKeepsAStatementOnceAndTheStateDocuments(): void
    {
        $store = new Store();
        $store->lrs->store('a7d1c6b8-0e0f-4c3a-9a37-2b8f1d4e5c6a', '{"first":true}', 'reg-1');
        $store->lrs->store('a7d1c6b8-0e0f-4c3a-9a37-2b8f1d4e5c6a', '{"second":true}', 'reg-1');

        self::assertSame(['{"first":true}'], $store->connection->fetchFirstColumn('SELECT body FROM '.CourseTables::STATEMENT));
        self::assertSame(['reg-1'], $store->connection->fetchFirstColumn('SELECT registration FROM '.CourseTables::STATEMENT));

        self::assertNull($store->lrs->state('https://a', 'reg-1', 'suspend'));
        $store->lrs->saveState('https://a', 'reg-1', 'suspend', '{"page":1}');
        $store->lrs->saveState('https://a', 'reg-1', 'suspend', '{"page":2}');
        $store->lrs->saveState('https://b', 'reg-1', 'suspend', '{"page":9}');
        self::assertSame('{"page":2}', $store->lrs->state('https://a', 'reg-1', 'suspend'));
        self::assertNull($store->lrs->state('https://a', 'reg-2', 'suspend'));
        $store->lrs->deleteState('https://a', 'reg-1', 'suspend');
        self::assertNull($store->lrs->state('https://a', 'reg-1', 'suspend'));
        self::assertSame('{"page":9}', $store->lrs->state('https://b', 'reg-1', 'suspend'));
        self::assertSame(hash('sha256', 'https://b'), $store->connection->fetchOne('SELECT activity_hash FROM '.CourseTables::STATE));
    }

    public function testTheValuesOfARowAreReadTheSameOnEveryDriver(): void
    {
        $rows = new Rows(Store::sqlite());

        self::assertTrue(Rows::bool(true));
        self::assertTrue(Rows::bool(1));
        self::assertTrue(Rows::bool('1'));
        self::assertTrue(Rows::bool('t'));
        self::assertTrue(Rows::bool('true'));
        self::assertFalse(Rows::bool('0'));
        self::assertFalse(Rows::bool('f'));
        self::assertFalse(Rows::bool(null));
        self::assertNull(Rows::nullableBool(null));
        self::assertFalse(Rows::nullableBool(0));
        self::assertSame('3', Rows::text(3));
        self::assertSame('', Rows::text(['a']));
        self::assertNull(Rows::nullableText(null));
        self::assertSame('a', Rows::nullableText('a'));
        self::assertSame(3, Rows::int('3'));
        self::assertSame(0, Rows::int('x'));
        self::assertNull(Rows::nullableInt(null));
        self::assertSame(0, Rows::nullableInt(0));
        self::assertSame(0.5, Rows::nullableFloat('0.5'));
        self::assertNull(Rows::nullableFloat('x'));
        self::assertNull($rows->moment(null));
        self::assertSame('2026-11-13T10:00:00+00:00', $rows->moment('2026-11-13 10:00:00')?->format(\DATE_ATOM));
        self::assertSame('1970-01-01T00:00:00+00:00', $rows->requiredMoment(null)->format(\DATE_ATOM));
    }

    public function testCoursesAreRemovedOnlyWithTheirOwnRows(): void
    {
        $store = new Store();
        $course = $store->courses->create('C');
        $store->courseStore->saveModule(new Module(ModuleId::of('m'), $course->id, 'M', 0));
        $block = $store->courses->addBlock($course->id, 'B', 'https://example.org/b', 0);
        $enrollment = $store->enrollments->enroll($course->id, LearnerId::of('ada'));
        $store->enrollmentStore->claimSatisfaction($enrollment->id, $block->id->value, 's', $store->clock->now());
        $store->courses->remove(CourseId::of('other'));
        self::assertNotNull($store->courseStore->find($course->id));
        self::assertNotNull($store->courseStore->module(ModuleId::of('m')));
        self::assertCount(1, $store->courseStore->blocks($course->id));
        self::assertCount(1, $store->enrollmentStore->satisfactions($enrollment->id));

        $store->courses->remove($course->id);
        self::assertSame([], $store->courseStore->blocks($course->id));
        self::assertSame([], $store->enrollmentStore->satisfactions($enrollment->id));
    }

    public function testTheBlocksAreReadEachParentBeforeTheBlocksItHolds(): void
    {
        $store = new Store();
        $course = $store->courses->create('C', '', false, CourseOrigin::Cmi5, 'https://example.org/c');
        $other = $store->courses->create('D');
        $second = $store->courses->addBlock($course->id, 'Second', 'https://example.org/second', 2);
        $first = $store->courses->addBlock($course->id, 'First', 'https://example.org/first', 0);
        $inner = $store->courses->addBlock($course->id, 'Inner', 'https://example.org/inner', 1, $first->id);
        $innermost = $store->courses->addBlock($course->id, 'Innermost', 'https://example.org/innermost', 0, $inner->id);
        $store->courses->addBlock($course->id, 'Orphan', 'https://example.org/orphan', 0, BlockId::of('gone'));
        $store->courses->addBlock($other->id, 'Elsewhere', 'https://example.org/elsewhere', 0);

        self::assertSame(['First', 'Inner', 'Innermost', 'Second'], array_map(static fn (Block $block): string => $block->title, $store->courseStore->blocks($course->id)));
        $read = $store->courseStore->blocks($course->id)[2];
        self::assertSame([$innermost->id->value, $course->id->value, 'Innermost', 0, 'https://example.org/innermost', $inner->id->value], [$read->id->value, $read->course->value, $read->title, $read->position, $read->source, $read->parent?->value]);
        self::assertNull($store->courseStore->blocks($course->id)[3]->parent);
        self::assertSame($second->id->value, $store->courses->blocks($course->id)[3]->id->value);

        $unit = $store->courses->addActivity($store->courses->addModule($course->id, 'M')->id, 'U', 'page', block: $inner->id);
        self::assertSame($inner->id->value, $store->courseStore->activity($unit->id)?->block?->value);
    }

    public function testAPositionBelowZeroIsReadAsZero(): void
    {
        $store = new Store();
        $course = $store->courses->create('C');
        $block = $store->courses->addBlock($course->id, 'B', 'https://example.org/b', 1);
        $store->connection->executeStatement('UPDATE '.CourseTables::BLOCK.' SET position = -3 WHERE id = ?', [$block->id->value]);

        self::assertSame(0, $store->courseStore->blocks($course->id)[0]->position);
    }

    public function testASatisfactionIsClaimedOnceAndReleased(): void
    {
        $store = new Store();
        $enrollment = EnrollmentId::of('e');
        self::assertTrue($store->enrollmentStore->claimSatisfaction($enrollment, 'b', 's1', $store->clock->now()));
        self::assertFalse($store->enrollmentStore->claimSatisfaction($enrollment, 'b', 's2', $store->clock->now()));
        self::assertTrue($store->enrollmentStore->claimSatisfaction($enrollment, 'a', 's3', $store->clock->now()->modify('+1 minute')));
        self::assertTrue($store->enrollmentStore->claimSatisfaction(EnrollmentId::of('f'), 'b', 's4', $store->clock->now()));
        self::assertSame(['b', 'a'], $store->enrollmentStore->satisfactions($enrollment));
        self::assertSame('s1', $store->connection->fetchOne('SELECT statement_id FROM phil_course_satisfaction WHERE enrollment_id = ? AND subject_id = ?', ['e', 'b']));
        self::assertSame('2026-11-13 10:00:00', $store->connection->fetchOne('SELECT satisfied_at FROM phil_course_satisfaction WHERE subject_id = ?', ['b']));

        $store->enrollmentStore->releaseSatisfaction($enrollment, 'b');
        self::assertSame(['a'], $store->enrollmentStore->satisfactions($enrollment));
        self::assertSame(['b'], $store->enrollmentStore->satisfactions(EnrollmentId::of('f')));
    }
}
