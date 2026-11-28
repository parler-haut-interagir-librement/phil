<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Application;

use Override;
use Phil\SitesConsoleBundle\Application\Console\ConfigurationPushes;
use Phil\SitesConsoleBundle\Application\Console\Journal;
use Phil\SitesConsoleBundle\Application\Console\Puller;
use Phil\SitesConsoleBundle\Application\Console\SiteRegistry;
use Phil\SitesConsoleBundle\Application\Console\UpdatePlans;
use Phil\SitesConsoleBundle\Application\Exception\AgentFailure;
use Phil\SitesConsoleBundle\Application\View\VersionMatrix;
use Phil\SitesConsoleBundle\Domain\Alert\AlertRules;
use Phil\SitesConsoleBundle\Domain\Contract\ConfigurationPushRepository;
use Phil\SitesConsoleBundle\Domain\Contract\UpdatePlanRepository;
use Phil\SitesConsoleBundle\Domain\Model\ConfigurationPush;
use Phil\SitesConsoleBundle\Domain\Model\JournalEntry;
use Phil\SitesConsoleBundle\Domain\Model\Snapshot;
use Phil\SitesConsoleBundle\Domain\Model\UpdatePlan;
use Phil\SitesConsoleBundle\Domain\Status\PlanState;
use Phil\SitesConsoleBundle\Domain\Status\StepOutcome;
use Phil\SitesConsoleBundle\Domain\ValueObject\Outcome;
use Phil\SitesConsoleBundle\Domain\ValueObject\StatusDocument;
use Phil\SitesConsoleBundle\Domain\ValueObject\UpdateStep;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemoryConfigurationPushRepository;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemoryJournalRepository;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemorySiteRepository;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemorySnapshotRepository;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemoryUpdatePlanRepository;
use Phil\SitesConsoleBundle\Tests\Unit\Support\FakeAgentClient;
use Phil\SitesConsoleBundle\Tests\Unit\Support\Fixtures;
use Phil\SitesConsoleBundle\Tests\Unit\Support\Keys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * What the console services keep, journal and count at their edges.
 */
#[CoversClass(SiteRegistry::class)]
#[CoversClass(Journal::class)]
#[CoversClass(Puller::class)]
#[CoversClass(ConfigurationPushes::class)]
#[CoversClass(UpdatePlans::class)]
#[CoversClass(VersionMatrix::class)]
#[CoversClass(AgentFailure::class)]
#[CoversClass(InMemorySnapshotRepository::class)]
final class ConsoleEdgesTest extends TestCase
{
    private MockClock $clock;

    private InMemorySnapshotRepository $snapshots;

    private Journal $journal;

    private SiteRegistry $registry;

    private FakeAgentClient $client;

    protected function setUp(): void
    {
        $this->clock = new MockClock(Fixtures::NOW);
        $this->snapshots = new InMemorySnapshotRepository();
        $entries = new InMemoryJournalRepository();
        $this->journal = new Journal($entries, $this->clock);
        $this->registry = new SiteRegistry(new InMemorySiteRepository(), $this->snapshots, $this->journal, $this->clock);
        $this->client = new FakeAgentClient();

        foreach (['shop', 'blog'] as $id) {
            $this->registry->register($id, ucfirst($id), 'https://'.$id.'.example.com/agent', Keys::site()->verifyingKey()->toBase64(), [], null);
        }
    }

    public function testTheRegistryJournalsSuccesses(): void
    {
        $this->registry->remove('blog', 'ada');

        self::assertSame([true, true, true], array_map(static fn (JournalEntry $entry): bool => $entry->succeeded(), $this->journal->recent(10)));
    }

    public function testASelectionIsAListWithoutRepeats(): void
    {
        self::assertSame([0, 1], array_keys($this->registry->select(['shop', 'shop', 'blog'])));
    }

    public function testAPushIsKeptWhenItIsCreated(): void
    {
        $repository = new InMemoryConfigurationPushRepository();
        $pushes = new ConfigurationPushes($this->registry, $repository, $this->client, $this->journal, $this->clock);

        $push = $pushes->create(['a' => 1], ['shop'], 'ada');
        $second = $pushes->create(['b' => 1], ['shop'], 'ada');

        self::assertSame($push, $repository->get($push->id()));
        self::assertTrue($this->journal->recent(1)[0]->succeeded());
        self::assertCount(1, $pushes->recent(1));
        self::assertSame($second, $pushes->recent(1)[0]);
        self::assertCount(2, $pushes->recent(5));
        self::assertCount(1, $pushes->recent(0));
    }

    public function testAPlanIsKeptWhenItIsMade(): void
    {
        $repository = new InMemoryUpdatePlanRepository();
        $plans = new UpdatePlans($this->registry, $repository, $this->client, $this->journal, $this->clock);

        $plan = $plans->plan('phil/url', '1.0', ['shop'], 1, 'ada');
        $second = $plans->plan('phil/url', '1.1', ['shop'], 1, 'ada');

        self::assertSame($plan, $repository->get($plan->id()));
        self::assertTrue($this->journal->recent(1)[0]->succeeded());
        self::assertSame([$second], $plans->recent(1));
        self::assertCount(2, $plans->recent(5));
        self::assertCount(1, $plans->recent(0));
    }

    public function testEachDeliveryOfAPushIsSaved(): void
    {
        $repository = new class implements ConfigurationPushRepository {
            public int $saves = 0;

            private readonly InMemoryConfigurationPushRepository $inner;

            public function __construct()
            {
                $this->inner = new InMemoryConfigurationPushRepository();
            }

            #[Override]
            public function get(string $id): ?ConfigurationPush
            {
                return $this->inner->get($id);
            }

            #[Override]
            public function save(ConfigurationPush $push): void
            {
                ++$this->saves;
                $this->inner->save($push);
            }

            #[Override]
            public function recent(int $limit): array
            {
                return $this->inner->recent($limit);
            }
        };
        $this->client->configurations = ['shop' => static fn (): Outcome => Outcome::succeeded(), 'blog' => static fn (): Outcome => Outcome::failed('no')];
        $pushes = new ConfigurationPushes($this->registry, $repository, $this->client, $this->journal, $this->clock);
        $push = $pushes->create(['a' => 1], ['shop', 'blog'], null);
        self::assertSame(1, $repository->saves);

        $pushes->deliver($push->id(), null);

        self::assertSame(3, $repository->saves);
    }

    public function testEachStepOfAPlanIsSaved(): void
    {
        $repository = new class implements UpdatePlanRepository {
            public int $saves = 0;

            private readonly InMemoryUpdatePlanRepository $inner;

            public function __construct()
            {
                $this->inner = new InMemoryUpdatePlanRepository();
            }

            #[Override]
            public function get(string $id): ?UpdatePlan
            {
                return $this->inner->get($id);
            }

            #[Override]
            public function save(UpdatePlan $plan): void
            {
                ++$this->saves;
                $this->inner->save($plan);
            }

            #[Override]
            public function recent(int $limit): array
            {
                return $this->inner->recent($limit);
            }
        };
        $this->client->updates = ['shop' => static fn (): Outcome => Outcome::succeeded(), 'blog' => static fn (): Outcome => Outcome::succeeded()];
        $plans = new UpdatePlans($this->registry, $repository, $this->client, $this->journal, $this->clock);
        $plan = $plans->plan('phil/url', '1.0', ['shop', 'blog'], 1, null);

        $plans->run($plan->id(), null, null);

        self::assertSame(3, $repository->saves);
    }

    public function testARunKeepsEachStepAndSurvivesAnUnreachableAgent(): void
    {
        $repository = new InMemoryUpdatePlanRepository();
        $plans = new UpdatePlans($this->registry, $repository, $this->client, $this->journal, $this->clock);
        $plan = $plans->plan('phil/url', '1.0', ['shop', 'blog'], 1, 'ada');

        $run = $plans->run($plan->id(), 1, 'ada');

        self::assertSame(PlanState::Stopped, $run->state());
        self::assertSame([StepOutcome::Failed, StepOutcome::Skipped], array_map(static fn (UpdateStep $step): StepOutcome => $step->outcome, $run->steps()));
        self::assertSame('The agent could not be reached: no answer', $run->steps()[0]->message);
        self::assertSame($run, $repository->get($plan->id()));
        self::assertSame(['update:shop:'.$plan->id().':shop'], $this->client->calls);
    }

    public function testAFinishedPlanRunsNothingEvenWhenBatchesAreAsked(): void
    {
        $this->client->updates = ['shop' => static fn (): Outcome => Outcome::succeeded('ok')];
        $plans = new UpdatePlans($this->registry, new InMemoryUpdatePlanRepository(), $this->client, $this->journal, $this->clock);
        $plan = $plans->plan('phil/url', '1.0', ['shop'], 1, null);
        $plans->run($plan->id(), null, null);
        $calls = $this->client->calls;

        $again = $plans->run($plan->id(), 1, null);

        self::assertSame(PlanState::Completed, $again->state());
        self::assertSame($calls, $this->client->calls);
    }

    public function testTheSiteOfAWrongStatusIsNamedByItsFirstSixtyFourCharacters(): void
    {
        $this->client->statuses = ['shop' => static fn (): StatusDocument => Fixtures::status('b'.str_repeat('é', 100))];
        $puller = new Puller($this->registry, $this->client, $this->snapshots, new AlertRules(), $this->clock);

        self::assertSame('The agent answered a malformed document: the status is that of the site "b'.str_repeat('é', 63).'".', $puller->pull('shop')->error());
    }

    public function testTheFailuresOfAnAgentHaveNoCode(): void
    {
        self::assertSame(0, AgentFailure::unreachable('x')->getCode());
        self::assertSame(0, AgentFailure::malformed('x')->getCode());
    }

    public function testTheMatrixFlagsOnlyAnOlderVersionWithAnOrder(): void
    {
        $matrix = new VersionMatrix([], [], ['a' => ['x/y' => '2.0', 'z/z' => 'dev-main', 'u/v' => '1.0', 'w/w' => '3.0'], 'b' => ['x/y' => '1.0']], ['x/y' => '2.0', 'z/z' => '1.0', 'u/v' => '1.0', 'w/w' => '2.0']);

        self::assertFalse($matrix->isBehind('a', 'x/y'));
        self::assertFalse($matrix->isBehind('a', 'z/z'));
        self::assertFalse($matrix->isBehind('a', 'u/v'));
        self::assertFalse($matrix->isBehind('a', 'w/w'));
        self::assertTrue($matrix->isBehind('b', 'x/y'));
    }

    public function testASnapshotTakenAtTheMomentIsKeptByThePurge(): void
    {
        $at = Fixtures::now();
        $this->snapshots->save(Snapshot::unreachable('shop', 'old', $at->modify('-1 day')));
        $this->snapshots->save(Snapshot::unreachable('shop', 'at', $at));
        $this->snapshots->save(Snapshot::unreachable('shop', 'new', $at->modify('+1 day')));

        self::assertSame(1, $this->snapshots->purgeBefore($at));
        self::assertSame(['new', 'at'], array_map(static fn (Snapshot $snapshot): ?string => $snapshot->error(), $this->snapshots->history('shop', 10)));
    }
}
