<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Integration;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\CoursesBundle\Application\ActivityStatus;
use Phil\CoursesBundle\Domain\Cmi5\Cmi5Verb;
use Phil\CoursesBundle\Domain\Cmi5\SessionState;
use Phil\CoursesBundle\Domain\Course\CourseOrigin;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\PathEnrollment;
use Phil\CoursesBundle\Domain\Progress\AvailabilityStatus;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;
use Phil\CoursesBundle\Domain\Scorm\AttemptState;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;
use Phil\CoursesBundle\Domain\ValueObject\Release;
use Phil\CoursesBundle\Domain\ValueObject\RetryRequestId;
use Phil\CoursesBundle\Domain\ValueObject\Score;
use Phil\CoursesBundle\Domain\ValueObject\ScormAttemptId;
use Phil\CoursesBundle\Infrastructure\Doctrine\CourseTables;
use Phil\CoursesBundle\Infrastructure\Privacy\Cmi5Privacy;
use Phil\CoursesBundle\Tests\Unit\Fixture\Packages;
use Phil\CoursesBundle\Tests\Unit\Fixture\Statements;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use PHPUnit\Framework\TestCase;

/**
 * The bundle's tables and queries on a real server, read from the environment: the tables created from
 * `CourseTables`, a course imported and taken, the moments in UTC, the booleans, the scores and the order of the
 * outline, the unique enrollment, a cmi5 session and its statements, the state documents, the cmi5 records of a learner
 * exported and erased, a SCORM attempt with its time and its review, a course removed, and the tables of an earlier
 * schema brought up to date, a satisfied block noted once; a learning path and a group of equivalent courses, and
 * their tables added by the upgrade.
 */
abstract class ServerTestCase extends TestCase
{
    private Connection $connection;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    #[Override]
    protected function setUp(): void
    {
        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        $this->connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
        Store::drop($this->connection);
        Store::create($this->connection);
    }

    #[Override]
    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            Store::drop($this->connection);
            $this->connection->close();
        }
    }

    public function testACourseIsImportedTakenAndRemoved(): void
    {
        $store = new Store($this->connection);
        $report = $store->importer->import(Packages::zip(Packages::scorm12Files()));
        $course = $store->courses->get($report->course);
        $course->imposeOrder(true);

        $store->courseStore->save($course);
        $outline = $store->courses->outline($course->id);
        self::assertSame(['Welcome', 'Further reading', 'Saws', 'Chisels / Care'], array_map(static fn (Activity $activity): string => $activity->title, $outline->activities()));
        self::assertSame(0.8, $outline->activities()[2]->setting('mastery_score'));

        $later = $store->courses->addModule($course->id, 'Later', Release::daysAfterEnrollment(3));
        $store->courses->addActivity($later->id, 'Exam', 'page', ['body' => 'é ✓'], CompletionRequirement::Passed, true, 1, Release::on(new DateTimeImmutable('2026-12-01 05:00 UTC')));

        $enrollment = $store->enrollments->enroll($course->id, LearnerId::of('ada'));
        $store->learning->complete($enrollment, $outline->activities()[0]->id);
        $store->enrollments->record($enrollment, $outline->activities()[2], Outcome::failed(Score::scaled(-0.25)));

        $statuses = $store->availabilities->outline($enrollment);
        self::assertSame([AvailabilityStatus::Satisfied, AvailabilityStatus::Open, AvailabilityStatus::Locked, AvailabilityStatus::Locked, AvailabilityStatus::Scheduled], array_map(static fn (ActivityStatus $status): AvailabilityStatus => $status->availability->status, $statuses));
        self::assertSame('2026-12-01T05:00:00+00:00', $statuses[4]->availability->opensAt?->format(\DATE_ATOM));
        self::assertSame(-0.25, $statuses[2]->progress?->score?->scaled);
        self::assertFalse($statuses[2]->progress->success);
        self::assertSame('é ✓', $statuses[4]->activity->setting('body'));
        $satisfied = $this->connection->fetchOne('SELECT satisfied_at FROM '.CourseTables::PROGRESS.' WHERE satisfied_at IS NOT NULL');
        self::assertIsString($satisfied);
        self::assertSame('2026-11-13 10:00:00', substr($satisfied, 0, 19));

        $store->courses->remove($course->id);
        self::assertSame(0, Store::count($this->connection, 'SELECT COUNT(*) FROM '.CourseTables::ACTIVITY));
        self::assertSame(0, Store::count($this->connection, 'SELECT COUNT(*) FROM '.CourseTables::PROGRESS));
    }

    public function testALearnerIsEnrolledOnce(): void
    {
        $store = new Store($this->connection);
        $course = $store->courses->create('C');
        $store->enrollments->enroll($course->id, LearnerId::of('ada'));

        $this->expectException(UniqueConstraintViolationException::class);

        $this->connection->insert(CourseTables::ENROLLMENT, ['id' => 'e2', 'course_id' => $course->id->value, 'learner' => 'ada', 'registration' => 'r2', 'enrolled_at' => '2026-11-13 10:00:00']);
    }

    public function testACmi5SessionKeepsItsStatementsAndStates(): void
    {
        $store = new Store($this->connection);
        $course = $store->courses->create('C');
        $unit = $store->courses->addActivity($store->courses->addModule($course->id, 'M')->id, 'Unit', 'cmi5', ['au_id' => 'https://example.org/au', 'url' => 'https://example.net/au/'], CompletionRequirement::Completed);
        $enrollment = $store->enrollments->enroll($course->id, LearnerId::of('ada'));
        $store->learning->launch($enrollment, $unit->id);
        $session = $store->sessionStore->open($enrollment->id, $unit->id)[0];
        self::assertArrayHasKey('auth-token', $store->tokens->fetch($session->id->value));
        $session = $store->sessionStore->find($session->id) ?? $session;
        self::assertTrue($session->fetched);

        $context = $store->statements->context($session);
        $store->statements->receive($session, (string) json_encode(Statements::of($context, Cmi5Verb::Initialized)));
        $session = $store->sessionStore->find($session->id) ?? $session;
        $store->statements->receive($session, (string) json_encode([Statements::completed($context), Statements::of($context, Cmi5Verb::Terminated, ['duration' => 'PT1M'])]));

        self::assertSame(SessionState::Terminated, $store->sessionStore->find($session->id)?->state);
        self::assertSame([], $store->sessionStore->open($enrollment->id, $unit->id));
        self::assertTrue($store->enrollments->get($enrollment->id)->isCompleted());
        self::assertSame(5, Store::count($this->connection, 'SELECT COUNT(*) FROM '.CourseTables::STATEMENT.' WHERE registration = ?', [$enrollment->registration]));

        $store->lrs->store('a7d1c6b8-0e0f-4c3a-9a37-2b8f1d4e5c6a', '{"a":1}', $enrollment->registration);
        $store->lrs->store('a7d1c6b8-0e0f-4c3a-9a37-2b8f1d4e5c6a', '{"a":2}', $enrollment->registration);
        self::assertSame('{"a":1}', $this->connection->fetchOne('SELECT body FROM '.CourseTables::STATEMENT.' WHERE id = ?', ['a7d1c6b8-0e0f-4c3a-9a37-2b8f1d4e5c6a']));

        $store->lrs->saveState($context->activityIri, $enrollment->registration, 'suspend', '{"page":1}');
        $store->lrs->saveState($context->activityIri, $enrollment->registration, 'suspend', '{"page":2}');
        self::assertSame('{"page":2}', $store->lrs->state($context->activityIri, $enrollment->registration, 'suspend'));
        $store->lrs->deleteState($context->activityIri, $enrollment->registration, 'suspend');
        self::assertNull($store->lrs->state($context->activityIri, $enrollment->registration, 'suspend'));
    }

    public function testAScormAttemptKeepsItsTrackingData(): void
    {
        $store = new Store($this->connection);
        $course = $store->courses->create('C');
        $activity = $store->courses->addActivity($store->courses->addModule($course->id, 'M')->id, 'SCO', 'scorm', ['path' => 'a.html', 'scorm_version' => 'scorm2004']);
        $enrollment = $store->enrollments->enroll($course->id, LearnerId::of('ada'));
        $launch = $store->scorm->launch($activity, $enrollment);
        $suspend = str_repeat('é', 64_000);
        $answer = $store->scorm->commit($launch->token, ['cmi.suspend_data' => $suspend, 'cmi.location' => 'Étape 2', 'cmi.score.scaled' => '0.25'], false);
        $store->scorm->commit((string) $answer->token, ['cmi.session_time' => 'PT1H', 'cmi.exit' => 'suspend'], true);

        $attempt = $store->attemptStore->latest($enrollment->id, $activity->id);
        self::assertNotNull($attempt);
        self::assertSame([$suspend, 'Étape 2', 'PT1H0M0S'], [$attempt->data['cmi.suspend_data'], $attempt->data['cmi.location'], $attempt->data['cmi.total_time']]);
        self::assertSame(['suspended', 0.25, 360_000], [$attempt->state->value, $attempt->status->score, $attempt->totalTime->centiseconds]);
        self::assertCount(1, $store->attemptStore->ofEnrollments($enrollment->id));
        self::assertNull($store->attemptStore->find(ScormAttemptId::of(strtoupper($launch->attempt->value))));

        $store->attemptStore->removeOfEnrollments($enrollment->id);
        self::assertSame([], $store->attemptStore->ofEnrollments($enrollment->id));
    }

    public function testTheCmi5RecordsOfALearnerAreExportedAndErased(): void
    {
        $store = new Store($this->connection);
        $course = $store->courses->create('C');
        $unit = $store->courses->addActivity($store->courses->addModule($course->id, 'M')->id, 'Unit', 'cmi5', ['au_id' => 'https://example.org/au', 'url' => 'https://example.net/au/'], CompletionRequirement::Completed);
        $ada = $store->enrollments->enroll($course->id, LearnerId::of('ada'));
        $eve = $store->enrollments->enroll($course->id, LearnerId::of('eve'));

        foreach ([$ada, $eve] as $enrollment) {
            $store->learning->launch($enrollment, $unit->id);
            $store->lrs->saveState('https://example.org/au', $enrollment->registration, 'bookmark', '{"page":"é"}');
            $store->enrollmentStore->claimSatisfaction($enrollment->id, $course->id->value, 's-'.$enrollment->learner->value, $store->clock->now());
        }

        $privacy = new Cmi5Privacy($store->enrollmentStore, $store->sessionStore, $store->lrs, $store->lrs);
        $export = $privacy->export(DataSubject::fromString('ada'));
        self::assertCount(1, \is_array($export['launch_sessions'] ?? null) ? $export['launch_sessions'] : []);
        self::assertSame([['registration' => $ada->registration, 'activity_sha256' => hash('sha256', 'https://example.org/au'), 'state_id' => 'bookmark', 'updated_at' => '2026-11-13T10:00:00+00:00', 'document' => ['page' => 'é']]], $export['state_documents'] ?? null);
        self::assertSame('2026-11-13T10:00:00+00:00', $store->lrs->statementsOf([$ada->registration])[0]['stored_at']->format(\DATE_ATOM));

        $privacy->erase(DataSubject::fromString('ada'));
        self::assertSame([], $privacy->export(DataSubject::fromString('ada')));
        self::assertSame([1, 1, 1], [
            Store::count($this->connection, 'SELECT COUNT(*) FROM '.CourseTables::SESSION),
            Store::count($this->connection, 'SELECT COUNT(*) FROM '.CourseTables::STATE),
            Store::count($this->connection, 'SELECT COUNT(*) FROM '.CourseTables::SATISFACTION),
        ]);
        self::assertSame(0, Store::count($this->connection, 'SELECT COUNT(*) FROM '.CourseTables::STATEMENT.' WHERE registration = ?', [$ada->registration]));
        self::assertCount(1, $store->sessionStore->ofEnrollments($eve->id, $ada->id));
    }

    public function testAScormAttemptKeepsItsTimeAndOpensForReview(): void
    {
        $store = new Store($this->connection);
        $course = $store->courses->create('C');
        $activity = $store->courses->addActivity($store->courses->addModule($course->id, 'M')->id, 'SCO', 'scorm', ['path' => 'a.html', 'scorm_version' => 'scorm12', 'time_limit' => 600]);
        $enrollment = $store->enrollments->enroll($course->id, LearnerId::of('ada'));
        $launch = $store->scorm->launch($activity, $enrollment);
        $store->clock->modify('+250 seconds');
        $store->scorm->commit($launch->token, ['cmi.core.lesson_status' => 'incomplete', 'cmi.core.exit' => 'suspend'], true);

        $suspended = $store->attemptStore->latest($enrollment->id, $activity->id);
        self::assertSame([250, '2026-11-13 10:00:00'], [$suspended?->elapsed, $suspended?->sessionOpenedAt?->format('Y-m-d H:i:s')]);

        $resumed = $store->scorm->launch($activity, $enrollment);
        self::assertSame(350, $resumed->remaining);
        $store->clock->modify('+350 seconds');
        $up = $store->scorm->commit($resumed->token, ['cmi.core.lesson_location' => 'é'], false);
        self::assertTrue($up->timeUp);

        $review = $store->scorm->review($activity, $enrollment);
        self::assertSame(['review', 'é', 'time-out'], [$review->values['cmi.core.lesson_mode'], $review->values['cmi.core.lesson_location'], $store->attemptStore->latest($enrollment->id, $activity->id)?->data['cmi.core.exit']]);
        self::assertSame([$activity->id->value], $store->scorm->reviewable($enrollment));
    }

    public function testTheTablesOfAnEarlierSchemaAreBroughtUpToDate(): void
    {
        Store::drop($this->connection);
        Store::createEarlier($this->connection);
        $this->connection->insert(CourseTables::COURSE, ['id' => 'c1', 'title' => 'Knots', 'description' => '', 'origin' => 'native', 'ordered' => false, 'created_at' => '2026-11-13 10:00:00'], ['ordered' => Types::BOOLEAN]);
        $this->connection->insert(CourseTables::RETRY, ['id' => 'r1', 'enrollment_id' => 'e1', 'activity_id' => 'a1', 'reason' => 'ill', 'decision' => 'pending', 'requested_at' => '2026-11-13 10:00:00']);

        foreach (CourseTables::upgrade($this->connection) as $sql) {
            $this->connection->executeStatement($sql);
        }

        self::assertSame([], CourseTables::upgrade($this->connection));
        $store = new Store($this->connection);
        self::assertSame('', $store->retryStore->find(RetryRequestId::of('r1'))?->note);
        $course = $store->courses->create('C', '', false, CourseOrigin::Cmi5, 'https://example.org/c');
        $block = $store->courses->addBlock($course->id, 'B', 'https://example.org/b', 0);
        $store->courses->addActivity($store->courses->addModule($course->id, 'M')->id, 'U', 'page', block: $block->id);
        self::assertSame($block->id->value, $store->courses->outline($course->id)->activities()[0]->block?->value);

        $enrollment = $store->enrollments->enroll($course->id, LearnerId::of('ada'));
        self::assertTrue($store->enrollmentStore->claimSatisfaction($enrollment->id, $block->id->value, 's1', $store->clock->now()));
        self::assertFalse($store->enrollmentStore->claimSatisfaction($enrollment->id, $block->id->value, 's2', $store->clock->now()));
        self::assertSame([$block->id->value], $store->enrollmentStore->satisfactions($enrollment->id));
        self::assertTrue($this->connection->createSchemaManager()->tablesExist([CourseTables::SCORM_ATTEMPT, CourseTables::SCORM_DATA]));
    }

    public function testTheTablesOfThePathsAndTheEquivalencesAreAddedByTheUpgrade(): void
    {
        $tables = [CourseTables::EQUIVALENT, CourseTables::EQUIVALENCE, CourseTables::PATH_ENROLLMENT, CourseTables::PATH_STEP, CourseTables::PATH];

        foreach ($tables as $table) {
            $this->connection->executeStatement('DROP TABLE '.$table);
        }

        $upgrade = CourseTables::upgrade($this->connection);
        self::assertCount(5, array_filter($upgrade, static fn (string $sql): bool => str_starts_with($sql, 'CREATE TABLE')));

        foreach ($upgrade as $sql) {
            $this->connection->executeStatement($sql);
        }

        self::assertSame([], CourseTables::upgrade($this->connection));
        self::assertTrue($this->connection->createSchemaManager()->tablesExist($tables));
    }

    public function testAPathAndAGroupOfEquivalentCoursesAreKept(): void
    {
        $store = new Store($this->connection);
        $english = $store->courses->create('Fire safety');
        $englishPage = $store->courses->addActivity($store->courses->addModule($english->id, 'M')->id, 'Exits', 'page');
        $french = $store->courses->create('Sécurité incendie');
        $frenchPage = $store->courses->addActivity($store->courses->addModule($french->id, 'M')->id, 'Sorties', 'page');
        $group = $store->equivalences->create('Sécurité');
        $store->equivalences->add($group->id, $english->id, 'en');
        $store->equivalences->add($group->id, $french->id, 'fr-CA');
        self::assertEquals($french->id, $store->equivalences->courseFor($english->id, 'fr'));

        $path = $store->paths->create('Onboarding', 'é ✓');
        $store->paths->addStep($path->id, $english->id);
        $last = $store->courses->create('Last');
        $lastPage = $store->courses->addActivity($store->courses->addModule($last->id, 'M')->id, 'End', 'page');
        $store->paths->addStep($path->id, $last->id, true, [$english->id]);
        self::assertEquals([$english->id], $store->paths->steps($path->id)[1]->prerequisites);
        self::assertSame('é ✓', $store->paths->get($path->id)->description);

        $ada = LearnerId::of('ada');
        $enrollment = $store->paths->enroll($path->id, $ada);
        $store->enrollments->record($store->enrollments->enroll($french->id, $ada), $frenchPage, Outcome::completed());
        self::assertSame([$enrollment->id->value], array_map(static fn (PathEnrollment $open): string => $open->id->value, $store->pathStore->openEnrollmentsWith($ada, [$french->id, $english->id])));
        $store->paths->advance($enrollment);
        $store->enrollments->record($store->enrollments->enroll($last->id, $ada), $lastPage, Outcome::completed());
        self::assertTrue($store->paths->advance($enrollment));
        self::assertSame('2026-11-13 10:00', $store->paths->enrollment($enrollment->id)->completedAt?->format('Y-m-d H:i'));
        self::assertSame([], $store->pathStore->openEnrollmentsWith($ada, [$english->id]));
        self::assertNull($store->enrollments->of($english->id, $ada)?->completedAt);
        self::assertSame($englishPage->course->value, $english->id->value);
    }

    public function testTheTimeOfTheScormAttemptsIsAddedToTheirTable(): void
    {
        Store::drop($this->connection);
        Store::createWithoutTime($this->connection);
        $this->connection->insert(CourseTables::SCORM_ATTEMPT, ['id' => '01890000-0000-7000-8000-000000000001', 'enrollment_id' => 'e1', 'activity_id' => 'a1', 'number' => 1, 'version' => '1.2', 'state' => 'suspended', 'session' => 1, 'completion' => 'incomplete', 'success' => 'unknown', 'total_time' => 0, 'started_at' => '2026-11-13 10:00:00', 'updated_at' => '2026-11-13 10:00:00']);

        $upgrade = CourseTables::upgrade($this->connection);
        self::assertNotSame([], $upgrade);

        foreach ($upgrade as $sql) {
            $this->connection->executeStatement($sql);
        }

        self::assertSame([], CourseTables::upgrade($this->connection));
        $attempt = new Store($this->connection)->attemptStore->find(ScormAttemptId::of('01890000-0000-7000-8000-000000000001'));
        self::assertSame([0, null, AttemptState::Suspended], [$attempt?->elapsed, $attempt?->sessionOpenedAt, $attempt?->state]);
    }
}
