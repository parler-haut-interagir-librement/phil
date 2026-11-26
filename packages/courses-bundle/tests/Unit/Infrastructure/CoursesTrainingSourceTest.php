<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\CoursesBundle\Application\Contract\TrainingCosts;
use Phil\CoursesBundle\Application\Contract\TrainingUnits;
use Phil\CoursesBundle\Application\Training\TrainingCost;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Model\TranscriptEntry;
use Phil\CoursesBundle\Domain\Training\CreditUnit;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\Credits;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\TranscriptEntryId;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalTranscriptRepository;
use Phil\CoursesBundle\Infrastructure\Organizations\OrganizationsTrainingUnits;
use Phil\CoursesBundle\Infrastructure\Reporting\CoursesTrainingSource;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use Phil\OrganizationsBundle\Application\OrganizationAccess;
use Phil\OrganizationsBundle\Application\Organizations;
use Phil\OrganizationsBundle\Application\RoleCatalog;
use Phil\OrganizationsBundle\Application\UnitAccess;
use Phil\OrganizationsBundle\Application\UnitOfWork;
use Phil\OrganizationsBundle\Application\Units;
use Phil\OrganizationsBundle\Application\UnitTree;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Infrastructure\Memory\InMemoryOrganizationStore;
use Phil\OrganizationsBundle\Infrastructure\Reporting\ReportingUnitDirectory;
use Phil\ReportingBundle\Application\Report\ReportRequest;
use Phil\ReportingBundle\Application\Training\QuebecTrainingReport;
use Phil\ReportingBundle\Application\Training\TrainingHoursReport;
use Phil\ReportingBundle\Application\Training\TrainingLedger;
use Phil\ReportingBundle\Domain\Training\TrainingActivity;
use Phil\ReportingBundle\Domain\ValueObject\Period;
use Phil\Slug\TransliteratingSlugger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The completions of the transcripts as the training source of phil/reporting-bundle: the completions of a period read
 * from the store, their hours from their credits, their costs and units from the application's ports, and the unit of
 * a member from the tree of units of phil/organizations-bundle.
 */
#[CoversClass(CoursesTrainingSource::class)]
#[CoversClass(TrainingCost::class)]
#[CoversClass(OrganizationsTrainingUnits::class)]
#[CoversClass(DbalTranscriptRepository::class)]
final class CoursesTrainingSourceTest extends TestCase
{
    public function testTheCompletionsOfThePeriodAreReadOldestFirstItsEndLeftOut(): void
    {
        $store = new Store();
        $course = CourseId::generate();
        $montreal = new DateTimeZone('America/Toronto');

        foreach (['2026-01-01 00:00:00', '2026-03-10 09:00:00', '2025-12-31 23:59:59', '2026-02-01 12:00:00', '2027-01-01 00:00:00'] as $i => $moment) {
            $store->transcriptStore->save(new TranscriptEntry(TranscriptEntryId::generate(), LearnerId::of('learner-'.$i), $course, 'Safety', new DateTimeImmutable($moment, $montreal)));
        }

        $read = $store->transcriptStore->completedBetween(new DateTimeImmutable('2026-01-01', $montreal), new DateTimeImmutable('2027-01-01', $montreal));

        self::assertSame(['learner-0', 'learner-3', 'learner-1'], array_map(static fn (TranscriptEntry $entry): string => $entry->learner->value, [...$read]));
        self::assertSame([], [...$store->transcriptStore->completedBetween(new DateTimeImmutable('2024-01-01'), new DateTimeImmutable('2025-01-01'))]);
    }

    public function testEachCompletionIsAnActivityWithTheHoursOfItsCredits(): void
    {
        $store = new Store();
        $course = CourseId::generate();
        $credits = [Credits::of('1.5', CreditUnit::Hours), Credits::of('0.25', CreditUnit::Ceu), null, Credits::of('9999.99', CreditUnit::Hours), Credits::of('1000.01', CreditUnit::Ceu), Credits::of('100', CreditUnit::Ceu)];

        foreach ($credits as $i => $credit) {
            $store->transcriptStore->save(new TranscriptEntry(TranscriptEntryId::generate(), LearnerId::of('ann'), $course, 'Safety', new DateTimeImmutable('2026-05-0'.($i + 1).' 10:00:00'), $credit));
        }

        $source = new CoursesTrainingSource($store->transcriptStore);
        $activities = [...$source->activities(new Period(new DateTimeImmutable('2026-05-01'), new DateTimeImmutable('2026-06-01')))];

        self::assertSame('CAD', $source->currency());
        self::assertSame([150, 250, 0, 999_999, TrainingActivity::MAX_HUNDREDTHS, 100_000], array_map(static fn (TrainingActivity $activity): int => $activity->hundredthsOfHours, $activities));
        self::assertSame([0, 0, 0, 0, 0, 0], array_map(static fn (TrainingActivity $activity): int => $activity->cost + $activity->eligibleCost, $activities));
        self::assertSame([null], array_values(array_unique(array_map(static fn (TrainingActivity $activity): ?string => $activity->unit, $activities))));
        self::assertEquals(new DateTimeImmutable('2026-05-01 10:00:00'), $activities[0]->on);

        $hours = new CoursesTrainingSource($store->transcriptStore, 'EUR', 15);
        self::assertSame('EUR', $hours->currency());
        self::assertSame([150, 375, 0, 999_999, TrainingActivity::MAX_HUNDREDTHS, 150_000], array_map(static fn (TrainingActivity $activity): int => $activity->hundredthsOfHours, [...$hours->activities(new Period(new DateTimeImmutable('2026-05-01'), new DateTimeImmutable('2026-06-01')))]));
    }

    public function testTheCostsAndTheUnitsComeFromThePortsEachLearnerAskedOnce(): void
    {
        $store = new Store();
        $course = CourseId::generate();

        foreach (['ann', 'bob', 'ann', 'eve', 'bob'] as $i => $learner) {
            $store->transcriptStore->save(new TranscriptEntry(TranscriptEntryId::generate(), LearnerId::of($learner), $course, 'Safety', new DateTimeImmutable('2026-05-0'.($i + 1)), Credits::of('2', CreditUnit::Hours)));
        }

        $costs = new class implements TrainingCosts {
            #[Override]
            public function cost(TranscriptEntry $entry): TrainingCost
            {
                return 'eve' === $entry->learner->value ? TrainingCost::none() : new TrainingCost(12_500, 10_000);
            }
        };
        $units = new class implements TrainingUnits {
            /** @var list<string> */
            public array $asked = [];

            #[Override]
            public function unitOf(LearnerId $learner): ?string
            {
                $this->asked[] = $learner->value;

                return 'eve' === $learner->value ? null : 'unit-'.$learner->value;
            }
        };

        $source = new CoursesTrainingSource($store->transcriptStore, 'CAD', 10, $costs, $units);
        $period = new Period(new DateTimeImmutable('2026-05-01'), new DateTimeImmutable('2026-06-01'));
        $activities = [...$source->activities($period)];

        self::assertSame([[12_500, 10_000, 'unit-ann'], [12_500, 10_000, 'unit-bob'], [12_500, 10_000, 'unit-ann'], [0, 0, null], [12_500, 10_000, 'unit-bob']], array_map(static fn (TrainingActivity $activity): array => [$activity->cost, $activity->eligibleCost, $activity->unit], $activities));
        self::assertSame(['ann', 'bob', 'eve'], $units->asked);

        [...$source->activities($period)];
        self::assertSame(['ann', 'bob', 'eve', 'ann', 'bob', 'eve'], $units->asked, 'Nothing is kept between two reports.');
    }

    public function testTheUnitsOfAReportAreForgottenPastTheirBound(): void
    {
        $store = new Store();
        $course = CourseId::generate();
        $learners = CoursesTrainingSource::MAX_LEARNERS + 1;
        $store->connection->beginTransaction();

        for ($i = 0; $i < $learners; ++$i) {
            $store->transcriptStore->save(new TranscriptEntry(TranscriptEntryId::generate(), LearnerId::of('l'.$i), $course, 'Safety', new DateTimeImmutable('2026-05-01 00:00:00')->modify('+'.$i.' seconds')));
        }

        $store->transcriptStore->save(new TranscriptEntry(TranscriptEntryId::generate(), LearnerId::of('l0'), $course, 'Safety', new DateTimeImmutable('2026-05-02')));
        $store->transcriptStore->save(new TranscriptEntry(TranscriptEntryId::generate(), LearnerId::of('l'.($learners - 1)), $course, 'Safety', new DateTimeImmutable('2026-05-03')));
        $store->connection->commit();

        $units = new class implements TrainingUnits {
            /** @var array<string, int> */
            public array $asked = [];

            #[Override]
            public function unitOf(LearnerId $learner): string
            {
                $this->asked[$learner->value] = ($this->asked[$learner->value] ?? 0) + 1;

                return 'u'.$learner->value;
            }
        };

        $activities = [...new CoursesTrainingSource($store->transcriptStore, units: $units)->activities(new Period(new DateTimeImmutable('2026-05-01'), new DateTimeImmutable('2026-06-01')))];

        self::assertCount($learners + 2, $activities);
        self::assertSame(2, $units->asked['l0'], 'The first learner is forgotten once the bound is reached.');
        self::assertSame(1, $units->asked['l'.($learners - 1)], 'The last learner, asked after the units were forgotten, is held again.');
        self::assertSame(1, $units->asked['l1']);
        self::assertSame('ul0', $activities[$learners]->unit);
    }

    public function testTheReportsOfReportingTotalTheCompletionsByUnitAndThePayroll(): void
    {
        $organizationStore = new InMemoryOrganizationStore();
        $store = new Store();
        $work = new UnitOfWork($organizationStore);
        $access = new OrganizationAccess($organizationStore, RoleCatalog::organization());
        $organizations = new Organizations($organizationStore, $work, $access, new TransliteratingSlugger(), $store->clock);
        $units = new Units($organizationStore, $organizationStore, $work, $access, new UnitAccess($organizationStore, $organizationStore, $access, RoleCatalog::unit()), $store->clock);
        $owner = MemberId::fromString('ann');
        $acme = $organizations->create('Acme Guild', $owner);
        $sales = $units->create($acme->id, $owner, 'Sales');
        $east = $units->create($acme->id, $owner, 'East', $sales->id);
        $units->addMember($east->id, $owner, $owner, ['member']);
        $tree = new UnitTree($organizationStore);

        $course = CourseId::generate();
        $store->transcriptStore->save(new TranscriptEntry(TranscriptEntryId::generate(), LearnerId::of('ann'), $course, 'Safety', new DateTimeImmutable('2026-03-02'), Credits::of('1.5', CreditUnit::Hours)));
        $store->transcriptStore->save(new TranscriptEntry(TranscriptEntryId::generate(), LearnerId::of('zed'), $course, 'Safety', new DateTimeImmutable('2026-08-02'), Credits::of('0.2', CreditUnit::Ceu)));

        $costs = new class implements TrainingCosts {
            #[Override]
            public function cost(TranscriptEntry $entry): TrainingCost
            {
                return new TrainingCost(25_000, 20_000);
            }
        };
        $ledger = new TrainingLedger(new CoursesTrainingSource($store->transcriptStore, 'CAD', 10, $costs, new OrganizationsTrainingUnits($organizations, $tree)), new ReportingUnitDirectory($tree));
        $year = new Period(new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2027-01-01'));

        self::assertSame([
            ['2026-01-01', '2026-12-31', $sales->id->value, 'Sales', 1, 1, 1.5, 250.0, 200.0, 'CAD'],
            ['2026-01-01', '2026-12-31', $east->id->value, 'East', 2, 1, 1.5, 250.0, 200.0, 'CAD'],
            ['2026-01-01', '2026-12-31', '', 'No unit', 0, 1, 2.0, 250.0, 200.0, 'CAD'],
        ], iterator_to_array(new TrainingHoursReport($ledger)->rows(new ReportRequest($year)), false));

        $quebec = iterator_to_array(new QuebecTrainingReport($ledger)->rows(new ReportRequest($year, ['payroll' => 3_000_000])), false);
        self::assertSame(['2026-01-01', '2026-12-31', 2, 3.5, 500.0, 400.0, 30000.0, 300.0], \array_slice($quebec[0], 0, 8));
    }

    public function testACostIsBounded(): void
    {
        $cost = new TrainingCost(TrainingCost::MAX_AMOUNT, TrainingCost::MAX_AMOUNT);
        self::assertSame([TrainingCost::MAX_AMOUNT, TrainingCost::MAX_AMOUNT], [$cost->cost, $cost->eligibleCost]);
        self::assertSame([0, 0], [TrainingCost::none()->cost, TrainingCost::none()->eligibleCost]);
        self::assertSame([0, 0], [new TrainingCost(0)->cost, new TrainingCost(0)->eligibleCost]);

        foreach ([[-1, 0], [TrainingCost::MAX_AMOUNT + 1, 0], [100, -1], [100, 101]] as [$amount, $eligible]) {
            try {
                new TrainingCost($amount, $eligible);
                self::fail($amount.' / '.$eligible);
            } catch (InvalidCourse $refused) {
                self::assertStringContainsString('cost of a completion is from 0 to', $refused->getMessage());
            }
        }
    }

    public function testTheTrainingOfAMemberCountsInTheirFirstUnit(): void
    {
        $organizationStore = new InMemoryOrganizationStore();
        $clock = new Store()->clock;
        $work = new UnitOfWork($organizationStore);
        $access = new OrganizationAccess($organizationStore, RoleCatalog::organization());
        $organizations = new Organizations($organizationStore, $work, $access, new TransliteratingSlugger(), $clock);
        $units = new Units($organizationStore, $organizationStore, $work, $access, new UnitAccess($organizationStore, $organizationStore, $access, RoleCatalog::unit()), $clock);
        $owner = MemberId::fromString('ann');
        $acme = $organizations->create('Acme Guild', $owner);
        $empty = $organizations->create('Empty Guild', $owner);
        $sales = $units->create($acme->id, $owner, 'Sales');
        $east = $units->create($acme->id, $owner, 'East', $sales->id);
        $west = $units->create($acme->id, $owner, 'West', $sales->id);
        $units->addMember($east->id, $owner, $owner, ['member']);
        $units->addMember($west->id, $owner, $owner, ['member']);

        $organizations->create('Bob Guild', MemberId::fromString('bob'));
        $units->create($empty->id, $owner, 'Nobody');

        $trainingUnits = new OrganizationsTrainingUnits($organizations, new UnitTree($organizationStore));
        $first = [$east->id->value, $west->id->value];
        sort($first);

        self::assertSame($first[0], $trainingUnits->unitOf(LearnerId::of('ann')));
        self::assertNull($trainingUnits->unitOf(LearnerId::of('bob')), 'A member of no unit counts in none.');
        self::assertNull($trainingUnits->unitOf(LearnerId::of('eve')));
        self::assertNull($trainingUnits->unitOf(LearnerId::of('a:b')), 'A learner that is no member identifier counts in none.');
    }
}
