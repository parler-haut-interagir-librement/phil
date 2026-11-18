<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Cmi5;

use DateTimeImmutable;
use Override;
use Phil\CoursesBundle\Application\Cmi5\Cmi5Context;
use Phil\CoursesBundle\Application\Cmi5\Cmi5Satisfaction;
use Phil\CoursesBundle\Application\Cmi5\Cmi5Statements;
use Phil\CoursesBundle\Application\Cmi5\IssueWaivedStatement;
use Phil\CoursesBundle\Application\Cmi5\PlatformStatements;
use Phil\CoursesBundle\Application\Contract\LearningRecordStore;
use Phil\CoursesBundle\Domain\Cmi5\Cmi5Iri;
use Phil\CoursesBundle\Domain\Cmi5\Cmi5Verb;
use Phil\CoursesBundle\Domain\Course\CourseOrigin;
use Phil\CoursesBundle\Domain\Event\ActivitySatisfied;
use Phil\CoursesBundle\Domain\Event\BlockSatisfied;
use Phil\CoursesBundle\Domain\Exception\RecordStoreUnavailable;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Block;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\LaunchSession;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;
use Phil\CoursesBundle\Domain\ValueObject\BlockId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\LaunchSessionId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\ModuleId;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalCourseRepository;
use Phil\CoursesBundle\Tests\Unit\Fixture\CoursesDecorator;
use Phil\CoursesBundle\Tests\Unit\Fixture\EnrollmentsDecorator;
use Phil\CoursesBundle\Tests\Unit\Fixture\Json;
use Phil\CoursesBundle\Tests\Unit\Fixture\Statements;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The `satisfied` statements of the blocks and of the course: a nested block before the block that holds it, a unit
 * whose `moveOn` is `NotApplicable` left out, a waived unit counted satisfied, one statement for each block and
 * enrollment, a statement the store refuses issued again later, and the course named by its course structure.
 */
#[CoversClass(Cmi5Satisfaction::class)]
#[CoversClass(Cmi5Statements::class)]
#[CoversClass(PlatformStatements::class)]
#[CoversClass(IssueWaivedStatement::class)]
#[CoversClass(BlockSatisfied::class)]
final class SatisfactionTest extends TestCase
{
    private const string COURSE = 'https://example.org/courses/navigation';

    private Store $store;

    private Enrollment $enrollment;

    /** @var array<string, Block> */
    private array $blocks;

    /** @var array<string, Activity> */
    private array $units;

    #[Override]
    protected function setUp(): void
    {
        $this->store = new Store();
        $this->course(CourseOrigin::Cmi5, self::COURSE);
    }

    public function testANestedBlockIsSatisfiedBeforeTheBlockThatHoldsItThenTheCourse(): void
    {
        $this->complete('tides');
        self::assertSame([['satisfied', 'https://example.org/blocks/tides']], $this->satisfied());
        self::assertSame([$this->blocks['tides']->id->value], array_map(static fn (BlockSatisfied $event): string => $event->block, $this->store->events->of(BlockSatisfied::class)));

        $this->complete('symbols');
        self::assertSame([['satisfied', 'https://example.org/blocks/tides'], ['satisfied', 'https://example.org/blocks/charts']], $this->satisfied());

        $this->complete('stars');
        self::assertSame([
            ['satisfied', 'https://example.org/blocks/tides'],
            ['satisfied', 'https://example.org/blocks/charts'],
            ['satisfied', 'https://example.org/blocks/sky'],
            ['satisfied', self::COURSE],
        ], $this->satisfied());

        $statement = array_last($this->statements());
        self::assertSame([['objectType' => 'Activity', 'id' => self::COURSE]], Json::at($statement, 'context', 'contextActivities', 'grouping'));
        self::assertContains(['id' => Cmi5Iri::MOVE_ON_CATEGORY], (array) Json::at($statement, 'context', 'contextActivities', 'category'));
        $block = $this->statements()[\count($this->statements()) - 2];
        self::assertSame([['objectType' => 'Activity', 'id' => self::COURSE]], Json::at($block, 'context', 'contextActivities', 'grouping'));
        self::assertSame(['objectType' => 'Agent', 'account' => ['homePage' => Store::HOME.'/', 'name' => 'ada']], Json::at($block, 'actor'));
        self::assertSame($this->enrollment->registration, Json::at($block, 'context', 'registration'));

        $events = $this->store->events->of(BlockSatisfied::class);
        self::assertCount(3, $events);
        self::assertSame($this->enrollment->id->value, $events[2]->enrollment);
        self::assertSame(Json::at($block, 'id'), $events[2]->statement);
        self::assertSame(4, Store::count($this->store->connection, 'SELECT COUNT(*) FROM phil_course_satisfaction WHERE enrollment_id = ?', [$this->enrollment->id->value]));
        self::assertSame(Json::at($statement, 'id'), $this->store->connection->fetchOne('SELECT statement_id FROM phil_course_satisfaction WHERE subject_id = ?', [$this->enrollment->course->value]));
    }

    public function testABlockAndTheBlockThatHoldsItSatisfiedTogetherComeInnermostFirst(): void
    {
        $this->complete('symbols');
        self::assertSame([], $this->satisfied());

        $this->complete('tides');
        self::assertSame([['satisfied', 'https://example.org/blocks/tides'], ['satisfied', 'https://example.org/blocks/charts']], $this->satisfied());
        self::assertSame([$this->blocks['tides']->id->value, $this->blocks['charts']->id->value], array_map(static fn (BlockSatisfied $event): string => $event->block, $this->store->events->of(BlockSatisfied::class)));
    }

    public function testAStatementAnotherRequestIssuedMeanwhileIsNotIssuedTwice(): void
    {
        $stale = new class($this->store->enrollmentStore) extends EnrollmentsDecorator {
            #[Override]
            public function satisfactions(EnrollmentId $enrollment): array
            {
                return [];
            }
        };
        $statements = new Cmi5Statements($this->store->lrs, $this->store->sessionStore, $stale, $this->store->courseStore, $this->store->enrollments, $this->store->settings, $this->store->clock, $this->store->events);
        $this->store->enrollments->record($this->enrollment, $this->units['tides'], new Outcome(true));
        $statements->satisfy($this->context('tides'), $this->enrollment);
        $statements->satisfy($this->context('tides'), $this->enrollment);

        self::assertSame([['satisfied', 'https://example.org/blocks/tides']], $this->satisfied());
        self::assertCount(1, $this->store->events->of(BlockSatisfied::class));
    }

    public function testEachSatisfiedStatementIsIssuedOnceForAnEnrollment(): void
    {
        foreach (['tides', 'symbols', 'stars'] as $unit) {
            $this->complete($unit);
        }

        $context = $this->context('stars');
        $this->store->statements->satisfy($context, $this->enrollment);
        $this->store->statements->satisfy($context, $this->enrollment);

        self::assertCount(4, $this->satisfied());
        self::assertCount(3, $this->store->events->of(BlockSatisfied::class));

        $grace = $this->store->enrollments->enroll($this->enrollment->course, LearnerId::of('grace'));
        $this->store->statements->satisfy($context, $grace);
        self::assertCount(4, $this->satisfied());
    }

    public function testAnEnrollmentCompletedWithoutItsSatisfiedStatementsReceivesThemAtTheNextOutcome(): void
    {
        foreach (['symbols', 'tides', 'stars'] as $unit) {
            $this->store->enrollments->record($this->enrollment, $this->units[$unit], new Outcome(true));
        }

        self::assertTrue($this->store->enrollments->get($this->enrollment->id)->isCompleted());
        self::assertSame([], $this->satisfied());

        $this->complete('legend');

        self::assertSame([
            ['satisfied', 'https://example.org/blocks/tides'],
            ['satisfied', 'https://example.org/blocks/charts'],
            ['satisfied', 'https://example.org/blocks/sky'],
            ['satisfied', self::COURSE],
        ], $this->satisfied());
    }

    public function testAWaivedUnitCountsSatisfiedAndItsBlocksFollowItsWaivedStatement(): void
    {
        $listener = new IssueWaivedStatement($this->store->enrollmentStore, $this->store->courseStore, $this->store->statements, $this->store->settings, $this->store->clock);
        $this->store->events->listeners[] = static fn (object $event): mixed => $event instanceof ActivitySatisfied ? $listener($event) : null;

        $this->store->enrollments->waive($this->enrollment, $this->units['tides']);
        $this->store->enrollments->waive($this->enrollment, $this->units['symbols']);
        $this->complete('stars');

        self::assertSame([
            [Cmi5Verb::Waived->value, $this->iri('tides')],
            [Cmi5Verb::Satisfied->value, 'https://example.org/blocks/tides'],
            [Cmi5Verb::Waived->value, $this->iri('symbols')],
            [Cmi5Verb::Satisfied->value, 'https://example.org/blocks/charts'],
        ], \array_slice($this->verbs(), 0, 4));
        $statements = $this->statements();
        self::assertSame(Json::at($statements[0], 'context', 'extensions', Cmi5Iri::SESSION), Json::at($statements[1], 'context', 'extensions', Cmi5Iri::SESSION));
        self::assertSame([Cmi5Verb::Satisfied->value, self::COURSE], array_last($this->verbs()));
    }

    public function testAUnitThatIsNotRequiredLeavesItsBlockToTheOthers(): void
    {
        self::assertFalse($this->units['legend']->required);
        $this->complete('tides');

        self::assertContains(['satisfied', 'https://example.org/blocks/tides'], $this->satisfied());
        self::assertNull($this->store->enrollmentStore->progress($this->enrollment->id, $this->units['legend']->id));
    }

    public function testABlockWithoutAUnitIsNeverSatisfied(): void
    {
        $this->store->courses->addBlock($this->enrollment->course, 'Empty', 'https://example.org/blocks/empty', 2);

        foreach (['tides', 'symbols', 'stars'] as $unit) {
            $this->complete($unit);
        }

        self::assertNotContains(['satisfied', 'https://example.org/blocks/empty'], $this->satisfied());
        self::assertCount(4, $this->satisfied());
    }

    public function testAStatementTheStoreRefusesIsIssuedAgainLater(): void
    {
        $refusing = new class($this->store->lrs) implements LearningRecordStore {
            public bool $refuse = true;

            public function __construct(
                private readonly LearningRecordStore $inner,
            ) {
            }

            #[Override]
            public function store(string $statementId, string $statement, string $registration): void
            {
                if ($this->refuse && str_contains($statement, Cmi5Verb::Satisfied->value)) {
                    throw RecordStoreUnavailable::status(503);
                }

                $this->inner->store($statementId, $statement, $registration);
            }

            #[Override]
            public function state(string $activityId, string $registration, string $stateId): ?string
            {
                return null;
            }

            #[Override]
            public function saveState(string $activityId, string $registration, string $stateId, string $document): void
            {
            }

            #[Override]
            public function deleteState(string $activityId, string $registration, string $stateId): void
            {
            }
        };
        $statements = new Cmi5Statements($refusing, $this->store->sessionStore, $this->store->enrollmentStore, $this->store->courseStore, $this->store->enrollments, $this->store->settings, $this->store->clock, $this->store->events);
        $this->store->enrollments->record($this->enrollment, $this->units['tides'], new Outcome(true));

        try {
            $statements->satisfy($this->context('tides'), $this->enrollment);
            self::fail('The refusal of the store is hidden.');
        } catch (RecordStoreUnavailable $recordStoreUnavailable) {
            self::assertStringContainsString('503', $recordStoreUnavailable->getMessage());
        }

        self::assertSame([], $this->store->enrollmentStore->satisfactions($this->enrollment->id));
        self::assertSame([], $this->store->events->of(BlockSatisfied::class));

        $refusing->refuse = false;
        $statements->satisfy($this->context('tides'), $this->enrollment);
        self::assertSame([$this->blocks['tides']->id->value], $this->store->enrollmentStore->satisfactions($this->enrollment->id));
        self::assertSame([['satisfied', 'https://example.org/blocks/tides']], $this->satisfied());
    }

    /**
     * @return iterable<string, array{CourseOrigin, ?string}>
     */
    public static function coursesWithoutAStructure(): iterable
    {
        yield 'written here' => [CourseOrigin::Native, null];
        yield 'imported from SCORM' => [CourseOrigin::Scorm12, 'com.example.navigation'];
        yield 'cmi5 without its identifier' => [CourseOrigin::Cmi5, null];
    }

    #[DataProvider('coursesWithoutAStructure')]
    public function testACourseWithoutAStructureIsNamedByItsPlatformIri(CourseOrigin $origin, ?string $source): void
    {
        $this->store = new Store();
        $this->course($origin, $source);

        foreach (['tides', 'symbols', 'stars'] as $unit) {
            $this->complete($unit);
        }

        $course = Store::HOME.'/courses/'.$this->enrollment->course->value;
        self::assertSame([Cmi5Verb::Satisfied->value, $course], array_last($this->verbs()));
        self::assertSame([['objectType' => 'Activity', 'id' => $course]], Json::at($this->statements()[\count($this->statements()) - 2], 'context', 'contextActivities', 'grouping'));
    }

    public function testACourseWithoutActivitiesOrGoneIsLeftAlone(): void
    {
        $empty = $this->store->courses->create('Empty', '', false, CourseOrigin::Cmi5, 'https://example.org/courses/empty');
        $enrollment = $this->store->enrollments->enroll($empty->id, LearnerId::of('ada'));
        $context = $this->context('tides');
        $this->store->statements->satisfy($context, $enrollment);
        $this->store->courses->remove($this->enrollment->course);
        $this->store->statements->satisfy($context, $this->enrollment);

        self::assertSame([], $this->satisfied());
    }

    public function testABlockOfACycleAStoreGivesIsWalkedOnce(): void
    {
        $cyclic = $this->blocks['tides'];
        $courses = new class($this->store->courseStore, $cyclic) extends CoursesDecorator {
            public function __construct(
                DbalCourseRepository $inner,
                private readonly Block $cyclic,
            ) {
                parent::__construct($inner);
            }

            #[Override]
            public function blocks(CourseId $course): array
            {
                return [...parent::blocks($course), new Block($this->cyclic->parent ?? BlockId::generate(), $course, 'Loop', 0, 'https://example.org/blocks/loop', $this->cyclic->id)];
            }
        };
        $statements = new Cmi5Statements($this->store->lrs, $this->store->sessionStore, $this->store->enrollmentStore, $courses, $this->store->enrollments, $this->store->settings, $this->store->clock, $this->store->events);
        $this->store->enrollments->record($this->enrollment, $this->units['tides'], new Outcome(true));
        $statements->satisfy($this->context('tides'), $this->enrollment);

        // Charts and Tides hold each other: the walk ends, and Symbols, still to do, keeps both unsatisfied.
        self::assertSame([], $this->satisfied());
        $this->store->enrollments->record($this->enrollment, $this->units['symbols'], new Outcome(true));
        $statements->satisfy($this->context('symbols'), $this->enrollment);
        self::assertContains(['satisfied', 'https://example.org/blocks/tides'], $this->satisfied());
    }

    public function testASubjectAlreadyClaimedIsNotClaimedAgain(): void
    {
        $this->complete('tides');
        $counting = new class($this->store->enrollmentStore) extends EnrollmentsDecorator {
            public int $claims = 0;

            #[Override]
            public function claimSatisfaction(EnrollmentId $enrollment, string $subject, string $statement, DateTimeImmutable $at): bool
            {
                ++$this->claims;

                return parent::claimSatisfaction($enrollment, $subject, $statement, $at);
            }
        };
        $statements = new Cmi5Statements($this->store->lrs, $this->store->sessionStore, $counting, $this->store->courseStore, $this->store->enrollments, $this->store->settings, $this->store->clock, $this->store->events);
        $statements->satisfy($this->context('tides'), $this->enrollment);

        self::assertSame(0, $counting->claims);
    }

    public function testTheCourseWaitsForEveryRequiredUnitWhateverTheOrderOfTheOthers(): void
    {
        $this->complete('tides');
        $this->complete('stars');

        self::assertSame([['satisfied', 'https://example.org/blocks/tides'], ['satisfied', 'https://example.org/blocks/sky']], $this->satisfied());
    }

    public function testAUnitOutsideEveryBlockCountsForTheCourseOnly(): void
    {
        $loose = $this->store->courses->addActivity($this->store->courses->addModule($this->enrollment->course, 'Loose')->id, 'Loose', 'cmi5', ['au_id' => 'https://example.org/aus/loose', 'url' => 'https://content.example.net/loose/'], CompletionRequirement::Completed);
        $this->units['loose'] = $loose;

        foreach (['tides', 'symbols', 'stars'] as $unit) {
            $this->complete($unit);
        }

        self::assertCount(3, $this->satisfied());
        $this->complete('loose');
        self::assertSame([Cmi5Verb::Satisfied->value, self::COURSE], array_last($this->verbs()));
        self::assertCount(4, $this->satisfied());
    }

    public function testTheSatisfactionWorksWithoutADispatcher(): void
    {
        $statements = new Cmi5Statements($this->store->lrs, $this->store->sessionStore, $this->store->enrollmentStore, $this->store->courseStore, $this->store->enrollments, $this->store->settings, $this->store->clock);
        $this->store->enrollments->record($this->enrollment, $this->units['tides'], new Outcome(true));
        $statements->satisfy($this->context('tides'), $this->enrollment);

        self::assertSame([['satisfied', 'https://example.org/blocks/tides']], $this->satisfied());
    }

    public function testTheBlocksAreIssuedByTheirDepthWhateverTheOrderTheyAreListedIn(): void
    {
        $courseId = $this->enrollment->course;
        $gone = BlockId::of('gone');
        $first = $this->store->courses->addBlock($courseId, 'First', 'https://example.org/blocks/first', 0, $gone);
        $second = $this->store->courses->addBlock($courseId, 'Second', 'https://example.org/blocks/second', 0, $gone);
        $deep = $this->store->courses->addBlock($courseId, 'Deep', 'https://example.org/blocks/deep', 0, $this->blocks['tides']->id);
        $module = $this->store->courses->addModule($courseId, 'Extra')->id;
        $this->units['first'] = $this->unit($module, 'first', $first->id);
        $this->units['second'] = $this->unit($module, 'second', $second->id);
        $this->units['deep'] = $this->unit($module, 'deep', $deep->id);
        // The blocks of a store that lists a child of a block it does not give, in the order of this list.
        $listed = [$first, $this->blocks['charts'], $this->blocks['tides'], $second, $this->blocks['sky'], $deep];
        $courses = new class($this->store->courseStore, $listed) extends CoursesDecorator {
            /**
             * @param list<Block> $listed
             */
            public function __construct(
                DbalCourseRepository $inner,
                private readonly array $listed,
            ) {
                parent::__construct($inner);
            }

            #[Override]
            public function blocks(CourseId $course): array
            {
                return $this->listed;
            }
        };
        $statements = new Cmi5Statements($this->store->lrs, $this->store->sessionStore, $this->store->enrollmentStore, $courses, $this->store->enrollments, $this->store->settings, $this->store->clock, $this->store->events);

        foreach (['symbols', 'tides', 'stars', 'first', 'second', 'deep'] as $unit) {
            $this->store->enrollments->record($this->enrollment, $this->units[$unit], new Outcome(true));
        }

        $statements->satisfy($this->context('deep'), $this->enrollment);

        $block = static fn (string $name): array => ['satisfied', 'https://example.org/blocks/'.$name];
        self::assertSame([$block('deep'), $block('first'), $block('tides'), $block('second'), $block('charts'), $block('sky'), ['satisfied', self::COURSE]], $this->satisfied());
    }

    /**
     * A course of two blocks, the first holding a unit and a nested block of a unit and an optional unit:
     *
     *     charts: symbols, tides: (tides, legend)
     *     sky: stars
     */
    private function course(CourseOrigin $origin, ?string $source): void
    {
        $course = $this->store->courses->create('Navigation', '', false, $origin, $source);
        $charts = $this->store->courses->addBlock($course->id, 'Charts', 'https://example.org/blocks/charts', 0);
        $tides = $this->store->courses->addBlock($course->id, 'Tides', 'https://example.org/blocks/tides', 1, $charts->id);
        $sky = $this->store->courses->addBlock($course->id, 'Sky', 'https://example.org/blocks/sky', 1);
        $this->blocks = ['charts' => $charts, 'tides' => $tides, 'sky' => $sky];
        $first = $this->store->courses->addModule($course->id, 'Charts')->id;
        $second = $this->store->courses->addModule($course->id, 'Sky')->id;
        $this->units = [
            'symbols' => $this->unit($first, 'symbols', $charts->id),
            'tides' => $this->unit($first, 'tides', $tides->id),
            'legend' => $this->store->courses->addActivity($first, 'Legend', 'cmi5', ['au_id' => 'https://example.org/aus/legend', 'url' => 'https://content.example.net/legend/'], CompletionRequirement::NotApplicable, false, block: $tides->id),
            'stars' => $this->unit($second, 'stars', $sky->id),
        ];
        $this->enrollment = $this->store->enrollments->enroll($course->id, LearnerId::of('ada'));
    }

    private function unit(ModuleId $module, string $name, BlockId $block): Activity
    {
        return $this->store->courses->addActivity($module, ucfirst($name), 'cmi5', ['au_id' => 'https://example.org/aus/'.$name, 'url' => 'https://content.example.net/'.$name.'/'], CompletionRequirement::Completed, block: $block);
    }

    private function complete(string $unit): void
    {
        $this->store->learning->launch($this->enrollment, $this->units[$unit]->id);
        $session = $this->store->sessionStore->open($this->enrollment->id, $this->units[$unit]->id)[0];
        $context = $this->store->statements->context($session);
        $this->store->statements->receive($session, (string) json_encode(Statements::of($context, Cmi5Verb::Initialized)));
        $session = $this->store->sessionStore->find($session->id) ?? $session;
        $this->store->statements->receive($session, (string) json_encode(Statements::completed($context)));
    }

    private function context(string $unit): Cmi5Context
    {
        $now = $this->store->clock->now();

        return Cmi5Context::of(new LaunchSession(LaunchSessionId::generate(), $this->enrollment->id, $this->units[$unit]->id, $now, $now), $this->enrollment, $this->units[$unit], Store::HOME);
    }

    private function iri(string $unit): string
    {
        return Store::HOME.'/courses/activities/'.$this->units[$unit]->id->value;
    }

    /**
     * @return list<array{string, string}> the verb, as its last word, and the object of each `satisfied` statement
     */
    private function satisfied(): array
    {
        return array_values(array_map(
            static fn (array $verb): array => ['satisfied', $verb[1]],
            array_filter($this->verbs(), static fn (array $verb): bool => Cmi5Verb::Satisfied->value === $verb[0]),
        ));
    }

    /**
     * @return list<array{string, string}> the verb and the object of each statement, in order
     */
    private function verbs(): array
    {
        return array_map(static fn (array $statement): array => [Json::text($statement, 'verb', 'id'), Json::text($statement, 'object', 'id')], $this->statements());
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function statements(): array
    {
        return array_map(Json::decode(...), $this->store->connection->fetchFirstColumn('SELECT body FROM phil_course_statement ORDER BY stored_at, rowid'));
    }
}
