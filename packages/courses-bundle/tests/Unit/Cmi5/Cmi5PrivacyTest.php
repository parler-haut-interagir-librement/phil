<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Cmi5;

use Override;
use Phil\CoursesBundle\Application\Contract\LearningRecordStore;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Infrastructure\Doctrine\CourseTables;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalLaunchSessionRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalLearningRecordStore;
use Phil\CoursesBundle\Infrastructure\Privacy\Cmi5Privacy;
use Phil\CoursesBundle\Infrastructure\Xapi\HttpLearningRecordStore;
use Phil\CoursesBundle\Tests\Unit\Fixture\Json;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\SafeHttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * The cmi5 records of a learner in the access and erasure requests of phil/privacy-bundle: the launch sessions, the
 * statements and state documents of the store inside the bundle and the notes of the `satisfied` statements, exported
 * and erased; with a store outside the bundle, what it keeps said in the export and reported as surviving.
 */
#[CoversClass(Cmi5Privacy::class)]
#[CoversClass(DbalLearningRecordStore::class)]
#[CoversClass(DbalLaunchSessionRepository::class)]
#[CoversClass(HttpLearningRecordStore::class)]
final class Cmi5PrivacyTest extends TestCase
{
    private const string STATEMENT = 'a7d1c6b8-0e0f-4c3a-9a37-2b8f1d4e5c6a';

    private Store $store;

    private Activity $unit;

    private Enrollment $ada;

    private Enrollment $eve;

    #[Override]
    protected function setUp(): void
    {
        $this->store = new Store();
        $course = $this->store->courses->create('Navigation');
        $this->unit = $this->store->courses->addActivity($this->store->courses->addModule($course->id, 'M')->id, 'Symbols', 'cmi5', ['au_id' => 'https://example.org/aus/symbols', 'url' => 'https://content.example.net/symbols/'], CompletionRequirement::Completed);
        $this->ada = $this->store->enrollments->enroll($course->id, LearnerId::of('ada'));
        $this->eve = $this->store->enrollments->enroll($course->id, LearnerId::of('eve'));

        foreach ([$this->ada, $this->eve] as $enrollment) {
            $this->store->learning->launch($enrollment, $this->unit->id);
            $this->store->lrs->saveState('https://example.org/aus/symbols', $enrollment->registration, 'bookmark', '{"page":3}');
            $this->store->enrollmentStore->claimSatisfaction($enrollment->id, $course->id->value, 's-'.$enrollment->learner->value, $this->store->clock->now());
        }

        $this->store->clock->modify('+1 minute');
        $this->store->lrs->store(self::STATEMENT, 'not json', $this->ada->registration);
    }

    public function testTheRecordsOfTheStoreInsideTheBundleAreExportedAndErased(): void
    {
        $privacy = new Cmi5Privacy($this->store->enrollmentStore, $this->store->sessionStore, $this->store->lrs, $this->store->lrs);
        self::assertSame(Cmi5Privacy::MODULE, $privacy->module()->value);

        $export = $privacy->export(DataSubject::fromString('ada'));
        self::assertSame(['registrations', 'launch_sessions', 'statements', 'state_documents', 'satisfactions'], array_keys($export));
        self::assertSame([['enrollment' => $this->ada->id->value, 'course' => $this->ada->course->value, 'registration' => $this->ada->registration]], $export['registrations']);
        $session = $this->store->sessionStore->ofEnrollments($this->ada->id)[0];
        self::assertSame([['id' => $session->id->value, 'enrollment' => $this->ada->id->value, 'activity' => $this->unit->id->value, 'state' => 'launched', 'launched_at' => '2026-11-13T10:00:00+00:00', 'ended_at' => null]], $export['launch_sessions']);

        /** @var list<array{id: string, registration: string, stored_at: string, statement: mixed}> $statements */
        $statements = $export['statements'];
        self::assertCount(2, $statements);
        self::assertSame([$this->ada->registration, '2026-11-13T10:00:00+00:00', 'http://adlnet.gov/expapi/verbs/launched'], [$statements[0]['registration'], $statements[0]['stored_at'], \is_array($statements[0]['statement']) && \is_array($statements[0]['statement']['verb'] ?? null) ? $statements[0]['statement']['verb']['id'] ?? null : null]);
        self::assertSame(['id' => self::STATEMENT, 'registration' => $this->ada->registration, 'stored_at' => '2026-11-13T10:01:00+00:00', 'statement' => 'not json'], $statements[1]);
        self::assertSame([['registration' => $this->ada->registration, 'activity_sha256' => hash('sha256', 'https://example.org/aus/symbols'), 'state_id' => 'bookmark', 'updated_at' => '2026-11-13T10:00:00+00:00', 'document' => ['page' => 3]]], $export['state_documents']);
        self::assertSame([['enrollment' => $this->ada->id->value, 'subject' => $this->ada->course->value]], $export['satisfactions']);

        foreach ([1, 2] as $run) {
            $report = $privacy->erase(DataSubject::fromString('ada'));
            self::assertSame([
                'phil_course_cmi5_session' => ErasureEffect::Deleted,
                'phil_course_statement' => ErasureEffect::Deleted,
                'phil_course_state' => ErasureEffect::Deleted,
                'phil_course_satisfaction' => ErasureEffect::Deleted,
            ], $report->effects, (string) $run);
        }

        self::assertSame([], $privacy->export(DataSubject::fromString('ada')));
        self::assertSame([[], [], [], []], [$this->store->sessionStore->ofEnrollments($this->ada->id), $this->store->lrs->statementsOf([$this->ada->registration]), $this->store->lrs->statesOf([$this->ada->registration]), $this->store->enrollmentStore->satisfactions($this->ada->id)]);
        self::assertSame(['registrations', 'launch_sessions', 'statements', 'state_documents', 'satisfactions'], array_keys($privacy->export(DataSubject::fromString('eve'))));
        self::assertCount(1, $this->store->sessionStore->ofEnrollments($this->eve->id));
        self::assertSame(1, Store::count($this->store->connection, 'SELECT COUNT(*) FROM '.CourseTables::STATEMENT));
    }

    public function testTheRecordsOfAnXapiActivityAreExportedAndErasedWithThoseOfCmi5(): void
    {
        $knots = $this->store->courses->addActivity($this->store->courses->outline($this->ada->course)->modules[0]->module->id, 'Knots', 'xapi', ['activity_id' => 'https://example.org/xapi/knots', 'url' => 'https://units.example.net/knots/']);
        $this->store->learning->launch($this->ada, $knots->id);
        $session = $this->store->sessionStore->open($this->ada->id, $knots->id)[0];
        $this->store->xapi->receive($session, (string) json_encode(['actor' => ['account' => ['homePage' => 'https://learn.example.org/', 'name' => 'ada']], 'verb' => ['id' => 'http://adlnet.gov/expapi/verbs/completed'], 'object' => ['id' => 'https://example.org/xapi/knots']]));
        $privacy = new Cmi5Privacy($this->store->enrollmentStore, $this->store->sessionStore, $this->store->lrs, $this->store->lrs);

        $export = $privacy->export(DataSubject::fromString('ada'));
        $sessions = Json::at($export, 'launch_sessions');
        $statements = Json::at($export, 'statements');
        self::assertIsArray($sessions);
        self::assertIsArray($statements);
        self::assertContains($session->id->value, array_map(static fn (mixed $kept): mixed => Json::at($kept, 'id'), $sessions));
        self::assertContains('https://example.org/xapi/knots', array_map(static fn (mixed $kept): mixed => \is_array(Json::at($kept, 'statement')) ? Json::at($kept, 'statement', 'object', 'id') : null, $statements));

        $privacy->erase(DataSubject::fromString('ada'));
        self::assertSame([], $this->store->lrs->statementsOf([$this->ada->registration]));
        self::assertNull($this->store->sessionStore->find($session->id));
    }

    public function testARemoteStoreKeepsTheStatementsAndSaysWhere(): void
    {
        $remote = new HttpLearningRecordStore(new SafeHttpClient(new MockHttpClient(), new Limits(maxRedirects: 0)), 'https://lrs.example.com/xapi', $this->store->lrs, 'key', 'secret');
        self::assertSame('https://lrs.example.com/xapi/', $remote->endpoint);
        $privacy = new Cmi5Privacy($this->store->enrollmentStore, $this->store->sessionStore, $this->store->lrs, $remote);

        $export = $privacy->export(DataSubject::fromString('ada'));
        self::assertSame([
            'records' => ['statements'],
            'store' => 'https://lrs.example.com/xapi/',
            'registrations' => [$this->ada->registration],
            'note' => 'The statements of these registrations were sent to this remote learning record store, which keeps them: the platform can neither read nor erase them there. Ask its operator, with the registrations.',
        ], $export['kept_elsewhere']);
        self::assertStringNotContainsString('secret', (string) json_encode($export));

        self::assertSame([
            'phil_course_cmi5_session' => ErasureEffect::Deleted,
            'phil_course_statement' => ErasureEffect::Deleted,
            'phil_course_state' => ErasureEffect::Deleted,
            'phil_course_satisfaction' => ErasureEffect::Survives,
            Cmi5Privacy::ELSEWHERE => ErasureEffect::Survives,
        ], $privacy->erase(DataSubject::fromString('ada'))->effects);
        self::assertSame([[], []], [$this->store->lrs->statementsOf([$this->ada->registration]), $this->store->lrs->statesOf([$this->ada->registration])]);
        self::assertSame([$this->ada->course->value], $this->store->enrollmentStore->satisfactions($this->ada->id));
        self::assertSame(['registrations', 'satisfactions', 'kept_elsewhere'], array_keys($privacy->export(DataSubject::fromString('ada'))));
    }

    public function testAStoreOfTheApplicationKeepsTheStatementsAndTheDocuments(): void
    {
        $own = new class implements LearningRecordStore {
            public function store(string $statementId, string $statement, string $registration): void
            {
            }

            public function state(string $activityId, string $registration, string $stateId): ?string
            {
                return null;
            }

            public function saveState(string $activityId, string $registration, string $stateId, string $document): void
            {
            }

            public function deleteState(string $activityId, string $registration, string $stateId): void
            {
            }
        };
        $privacy = new Cmi5Privacy($this->store->enrollmentStore, $this->store->sessionStore, $this->store->lrs, $own);
        $elsewhere = $privacy->export(DataSubject::fromString('eve'))['kept_elsewhere'] ?? null;

        self::assertIsArray($elsewhere);
        self::assertSame([['statements', 'state_documents'], $own::class, [$this->eve->registration]], [$elsewhere['records'], $elsewhere['store'], $elsewhere['registrations']]);
        self::assertSame('The statements and state documents of these registrations are kept by this learning record store of the application, which the courses neither read nor erase.', $elsewhere['note']);
    }

    public function testAPersonWithoutRecordsHasNothingToExport(): void
    {
        $privacy = new Cmi5Privacy($this->store->enrollmentStore, $this->store->sessionStore, $this->store->lrs, $this->store->lrs);
        $course = $this->store->courses->create('Empty');
        $this->store->enrollments->enroll($course->id, LearnerId::of('bob'));

        foreach (['nobody', 'bob', str_repeat('x', LearnerId::MAX_LENGTH + 1)] as $person) {
            self::assertSame([], $privacy->export(DataSubject::fromString($person)), $person);
            self::assertCount(4, $privacy->erase(DataSubject::fromString($person))->effects);
        }

        self::assertSame([], $this->store->sessionStore->ofEnrollments());
        $this->store->sessionStore->removeOfEnrollments();
        $this->store->lrs->erase([]);
        self::assertSame([[], []], [$this->store->lrs->statementsOf([]), $this->store->lrs->statesOf([])]);
        self::assertSame(2, Store::count($this->store->connection, 'SELECT COUNT(*) FROM '.CourseTables::SESSION));
    }

    /**
     * @param list<string> $kept the kinds of record the person keeps
     */
    #[DataProvider('kinds')]
    public function testEachKindOfRecordAloneIsExported(array $kept, string $section, int $count): void
    {
        $privacy = new Cmi5Privacy($this->store->enrollmentStore, $this->store->sessionStore, $this->store->lrs, $this->store->lrs);
        $course = $this->store->courses->create('Alone');
        $unit = $this->store->courses->addActivity($this->store->courses->addModule($course->id, 'M')->id, 'Symbols', 'cmi5', ['au_id' => 'https://example.org/aus/alone', 'url' => 'https://content.example.net/alone/'], CompletionRequirement::Completed);
        $bob = $this->store->enrollments->enroll($course->id, LearnerId::of('bob'));
        $this->store->learning->launch($bob, $unit->id);
        $this->store->lrs->saveState('https://example.org/aus/alone', $bob->registration, 'bookmark', '{}');
        $this->store->enrollmentStore->claimSatisfaction($bob->id, 'first', 's-1', $this->store->clock->now());
        $this->store->enrollmentStore->claimSatisfaction($bob->id, 'second', 's-2', $this->store->clock->now());

        $connection = $this->store->connection;

        foreach (['sessions' => [CourseTables::SESSION, 'enrollment_id', $bob->id->value], 'statements' => [CourseTables::STATEMENT, 'registration', $bob->registration], 'states' => [CourseTables::STATE, 'registration', $bob->registration], 'satisfactions' => [CourseTables::SATISFACTION, 'enrollment_id', $bob->id->value]] as $kind => [$table, $column, $value]) {
            if (!\in_array($kind, $kept, true)) {
                $connection->executeStatement('DELETE FROM '.$table.' WHERE '.$column.' = ?', [$value]);
            }
        }

        $export = $privacy->export(DataSubject::fromString('bob'));
        self::assertSame(['registrations', $section], array_keys($export));
        self::assertIsArray($export[$section]);
        self::assertCount($count, $export[$section]);
    }

    /**
     * @return iterable<string, array{list<string>, string, int}>
     */
    public static function kinds(): iterable
    {
        yield 'launch sessions' => [['sessions'], 'launch_sessions', 1];
        yield 'statements' => [['statements'], 'statements', 1];
        yield 'state documents' => [['states'], 'state_documents', 1];
        yield 'satisfactions' => [['satisfactions'], 'satisfactions', 2];
    }

    public function testTheStatementsAndTheDocumentsAreReadDownToTheDepthOfSixtyFour(): void
    {
        $privacy = new Cmi5Privacy($this->store->enrollmentStore, $this->store->sessionStore, $this->store->lrs, $this->store->lrs);
        $deep = str_repeat('[', 63).str_repeat(']', 63);
        $deeper = str_repeat('[', 64).str_repeat(']', 64);
        $this->store->lrs->store('b8e2d7c9-1f10-4d4b-8b48-3c9a2e5f6d7b', $deep, $this->eve->registration);
        $this->store->lrs->store('c9f3e8da-2a21-4e5c-9c59-4dab3f6a7e8c', $deeper, $this->eve->registration);

        $statements = $privacy->export(DataSubject::fromString('eve'))['statements'];
        self::assertIsArray($statements);
        $bodies = array_column($statements, 'statement', 'id');
        self::assertIsArray($bodies['b8e2d7c9-1f10-4d4b-8b48-3c9a2e5f6d7b']);
        self::assertSame($deep, json_encode($bodies['b8e2d7c9-1f10-4d4b-8b48-3c9a2e5f6d7b'], \JSON_THROW_ON_ERROR));
        self::assertSame($deeper, $bodies['c9f3e8da-2a21-4e5c-9c59-4dab3f6a7e8c']);
    }
}
