<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Application;

use Phil\SitesConsoleBundle\Application\Console\ConfigurationPushes;
use Phil\SitesConsoleBundle\Application\Console\Fleet;
use Phil\SitesConsoleBundle\Application\Console\Journal;
use Phil\SitesConsoleBundle\Application\Console\Puller;
use Phil\SitesConsoleBundle\Application\Console\SiteRegistry;
use Phil\SitesConsoleBundle\Application\Console\UpdatePlans;
use Phil\SitesConsoleBundle\Application\Event\SiteAlertRaised;
use Phil\SitesConsoleBundle\Application\Exception\AgentFailure;
use Phil\SitesConsoleBundle\Application\Exception\NotFound;
use Phil\SitesConsoleBundle\Application\View\SiteOverview;
use Phil\SitesConsoleBundle\Application\View\VersionMatrix;
use Phil\SitesConsoleBundle\Domain\Alert\AlertRules;
use Phil\SitesConsoleBundle\Domain\Exception\InvalidValue;
use Phil\SitesConsoleBundle\Domain\Model\ConfigurationPush;
use Phil\SitesConsoleBundle\Domain\Model\JournalEntry;
use Phil\SitesConsoleBundle\Domain\Model\Site;
use Phil\SitesConsoleBundle\Domain\Model\Snapshot;
use Phil\SitesConsoleBundle\Domain\Model\UpdatePlan;
use Phil\SitesConsoleBundle\Domain\Status\AlertKind;
use Phil\SitesConsoleBundle\Domain\Status\DeliveryOutcome;
use Phil\SitesConsoleBundle\Domain\Status\HealthState;
use Phil\SitesConsoleBundle\Domain\Status\PlanState;
use Phil\SitesConsoleBundle\Domain\Status\StepOutcome;
use Phil\SitesConsoleBundle\Domain\ValueObject\ConfigurationRequest;
use Phil\SitesConsoleBundle\Domain\ValueObject\Delivery;
use Phil\SitesConsoleBundle\Domain\ValueObject\Outcome;
use Phil\SitesConsoleBundle\Domain\ValueObject\StatusDocument;
use Phil\SitesConsoleBundle\Domain\ValueObject\UpdateRequest;
use Phil\SitesConsoleBundle\Domain\ValueObject\UpdateStep;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemoryConfigurationPushRepository;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemoryJournalRepository;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemorySiteRepository;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemorySnapshotRepository;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemoryUpdatePlanRepository;
use Phil\SitesConsoleBundle\Tests\Unit\Support\FakeAgentClient;
use Phil\SitesConsoleBundle\Tests\Unit\Support\Fixtures;
use Phil\SitesConsoleBundle\Tests\Unit\Support\Keys;
use Phil\SitesConsoleBundle\Tests\Unit\Support\RecordingDispatcher;
use Phil\SitesConsoleBundle\Tests\Unit\Support\RecordingLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;

#[CoversClass(SiteRegistry::class)]
#[CoversClass(Journal::class)]
#[CoversClass(Puller::class)]
#[CoversClass(Fleet::class)]
#[CoversClass(ConfigurationPushes::class)]
#[CoversClass(UpdatePlans::class)]
#[CoversClass(SiteOverview::class)]
#[CoversClass(VersionMatrix::class)]
#[CoversClass(SiteAlertRaised::class)]
#[CoversClass(AgentFailure::class)]
#[CoversClass(NotFound::class)]
final class ConsoleTest extends TestCase
{
    private MockClock $clock;

    private InMemorySnapshotRepository $snapshots;

    private Journal $journal;

    private SiteRegistry $registry;

    private FakeAgentClient $client;

    private RecordingDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->clock = new MockClock(Fixtures::NOW);
        $this->snapshots = new InMemorySnapshotRepository();
        $entries = new InMemoryJournalRepository();
        $this->journal = new Journal($entries, $this->clock);
        $this->registry = new SiteRegistry(new InMemorySiteRepository(), $this->snapshots, $this->journal, $this->clock);
        $this->client = new FakeAgentClient();
        $this->dispatcher = new RecordingDispatcher();
    }

    public function testSitesAreRegisteredChangedSelectedAndRemovedWithTheJournal(): void
    {
        $public = Keys::site()->verifyingKey()->toBase64();
        $shop = $this->registry->register('shop', 'Shop', 'https://shop.example.com/agent', $public, ['eu'], 'ada');
        $this->registry->register('blog', 'Blog', 'https://blog.example.com/agent', $public, [], 'ada');
        $changed = $this->registry->register('shop', 'Boutique', 'https://boutique.example.com/agent', $public, ['eu', 'web'], 'grace');

        self::assertSame($shop, $changed);
        self::assertSame('Boutique', $this->registry->get('shop')->name());
        self::assertSame(['blog', 'shop'], $this->ids($this->registry->all()));
        self::assertSame(['shop', 'blog'], $this->ids($this->registry->select(['shop', 'blog', 'shop'])));
        self::assertSame(['shop'], $this->ids($this->registry->select([], 'eu')));
        self::assertSame(['blog', 'shop'], $this->ids($this->registry->select()));
        self::assertSame([], $this->registry->select([], 'none'));

        $this->snapshots->save(Snapshot::unreachable('shop', 'x', Fixtures::now()));
        $this->registry->remove('shop', 'grace');
        self::assertSame(['blog'], $this->ids($this->registry->all()));
        self::assertNull($this->snapshots->latest('shop'));

        self::assertSame(
            ['site.removed shop grace Boutique', 'site.registered shop grace Boutique at https://boutique.example.com/agent', 'site.registered blog ada Blog at https://blog.example.com/agent', 'site.registered shop ada Shop at https://shop.example.com/agent'],
            array_map(static fn (JournalEntry $entry): string => \sprintf('%s %s %s %s', $entry->action()->value, $entry->siteId(), $entry->actor(), $entry->message()), $this->journal->recent(10)),
        );
        self::assertCount(1, $this->journal->recent(0));
        self::assertCount(1, $this->journal->recent(10, 'blog'));
    }

    public function testAnInsecureAddressOnlyWhenAllowed(): void
    {
        $registry = new SiteRegistry(new InMemorySiteRepository(), $this->snapshots, $this->journal, $this->clock, true);

        self::assertSame('http://localhost:8000', $registry->register('dev', 'Dev', 'http://localhost:8000', Keys::site()->verifyingKey()->toBase64(), [], null)->agentUrl()->value);

        $this->expectException(InvalidValue::class);
        $this->registry->register('dev', 'Dev', 'http://localhost:8000', Keys::site()->verifyingKey()->toBase64(), [], null);
    }

    public function testUnknownThings(): void
    {
        foreach ([
            'The console knows no site "x".' => fn (): Site => $this->registry->get('x'),
            'The console knows no site "y".' => fn () => $this->registry->remove('y', null),
            'The console knows no site "z".' => fn (): array => $this->registry->select(['z']),
            'The console knows no push "p".' => fn (): ConfigurationPush => $this->pushes()->get('p'),
            'The console knows no update plan "q".' => fn (): UpdatePlan => $this->plans()->get('q'),
        ] as $message => $call) {
            try {
                $call();
                self::fail($message);
            } catch (NotFound $notFound) {
                self::assertSame($message, $notFound->getMessage());
            }
        }
    }

    public function testAPullKeepsASnapshotAndRaisesNewAlertsOnce(): void
    {
        $this->sites('shop', 'blog');
        $this->client->statuses = [
            'shop' => static fn (): StatusDocument => Fixtures::status('shop', ['phil/url' => '0.1.4']),
            'blog' => static fn (): StatusDocument => Fixtures::status('blog', ['phil/url' => '0.1.6'], HealthState::Down),
        ];
        $puller = $this->puller();

        $puller->pull('blog');
        self::assertSame(['blog|health_down|'], $this->alertKeys());

        $this->dispatcher->events = [];
        $shop = $puller->pull('shop');
        self::assertTrue($shop->isReachable());
        self::assertSame($shop, $this->snapshots->latest('shop'));
        self::assertSame(['shop|version_behind|phil/url'], $this->alertKeys());

        $this->dispatcher->events = [];
        $puller->pull('shop');
        $puller->pull('blog');
        self::assertSame([], $this->dispatcher->events, 'An alert already raised is not raised again.');
    }

    public function testAnUnreachableSiteIsKeptAsSuchAndLogged(): void
    {
        $this->sites('shop');
        $logger = new RecordingLogger();

        $snapshot = $this->puller($logger)->pull('shop');

        self::assertFalse($snapshot->isReachable());
        self::assertSame('The agent could not be reached: no answer', $snapshot->error());
        self::assertSame(['warning: The site shop could not be read: The agent could not be reached: no answer'], $logger->lines);
        self::assertSame(['shop|unreachable|'], $this->alertKeys());
    }

    public function testAStatusOfAnotherSiteIsAFailure(): void
    {
        $this->sites('shop');
        $this->client->statuses = ['shop' => static fn (): StatusDocument => Fixtures::status(str_repeat('b', 70))];

        self::assertSame('The agent answered a malformed document: the status is that of the site "'.str_repeat('b', 64).'".', $this->puller()->pull('shop')->error());
    }

    public function testAPullWithoutADispatcher(): void
    {
        $this->sites('shop');
        $puller = new Puller($this->registry, $this->client, $this->snapshots, new AlertRules(), $this->clock);

        self::assertFalse($puller->pull('shop')->isReachable());
    }

    public function testTheFleetOverviewAndTheMatrix(): void
    {
        $this->sites('shop', 'blog', 'news');
        $this->client->statuses = [
            'shop' => static fn (): StatusDocument => Fixtures::status('shop', ['phil/url' => '0.1.4', 'other/lib' => '1.0']),
            'blog' => static fn (): StatusDocument => Fixtures::status('blog', ['phil/url' => '0.1.6', 'phil/enums' => '0.3.0']),
        ];
        $this->puller()->pull('shop');
        $this->puller()->pull('blog');
        $fleet = new Fleet($this->registry, $this->snapshots, new AlertRules(['phil/*']));

        $overview = $fleet->overview();
        self::assertSame(['blog', 'news', 'shop'], array_map(static fn (SiteOverview $one): string => $one->site->id(), $overview));
        self::assertSame(HealthState::Up, $overview[0]->health());
        self::assertSame(HealthState::Unknown, $overview[1]->health());
        self::assertNull($overview[1]->snapshot);
        self::assertSame([], $overview[1]->alerts);
        self::assertSame(AlertKind::VersionBehind, $overview[2]->alerts[0]->kind);

        $shop = $fleet->site('shop');
        self::assertSame('shop', $shop->site->id());
        self::assertSame('0.1.4', $shop->snapshot?->version('phil/url'));
        self::assertCount(1, $shop->alerts);

        $matrix = $fleet->matrix();
        self::assertSame(['phil/enums', 'phil/url'], $matrix->packages);
        self::assertSame(['blog', 'shop'], $matrix->sites);
        self::assertSame(['phil/enums' => '0.3.0', 'phil/url' => '0.1.6'], $matrix->newest);
        self::assertSame(['phil/url' => '0.1.4'], $matrix->versions['shop']);
        self::assertSame('0.1.4', $matrix->version('shop', 'phil/url'));
        self::assertNull($matrix->version('shop', 'phil/enums'));
        self::assertTrue($matrix->isBehind('shop', 'phil/url'));
        self::assertFalse($matrix->isBehind('blog', 'phil/url'));
        self::assertFalse($matrix->isBehind('shop', 'phil/enums'));
        self::assertFalse(new VersionMatrix([], [], ['a' => ['x/y' => 'dev-main']], ['x/y' => '1.0'])->isBehind('a', 'x/y'));
        self::assertFalse(new VersionMatrix([], [], ['a' => ['x/y' => '1.0']], [])->isBehind('a', 'x/y'));
    }

    public function testAPushIsKeptJournaledAndDeliveredToEachSite(): void
    {
        $this->sites('shop', 'blog', 'news');
        $this->client->configurations = [
            'shop' => static fn (ConfigurationRequest $request): Outcome => Outcome::succeeded('1 settings applied.'),
            'blog' => static fn (ConfigurationRequest $request): Outcome => Outcome::failed('Refused'),
        ];
        $pushes = $this->pushes();

        $push = $pushes->create(['mail.footer' => 'Hi'], [], 'ada');
        self::assertSame(['blog', 'news', 'shop'], $push->undeliveredSites());
        self::assertSame($push, $pushes->get($push->id()));
        self::assertSame([$push], $pushes->recent(0));

        $pushes->deliver($push->id(), 'ada');
        self::assertSame(['blog', 'news'], $push->undeliveredSites());
        self::assertSame(['configuration:blog:'.$push->id(), 'configuration:news:'.$push->id(), 'configuration:shop:'.$push->id()], $this->client->calls);
        self::assertSame([DeliveryOutcome::Failed, DeliveryOutcome::Failed, DeliveryOutcome::Applied], array_map(static fn (Delivery $delivery): DeliveryOutcome => $delivery->outcome, $push->deliveries()));
        self::assertSame('The agent could not be reached: no answer', $push->deliveries()[1]->message);

        $this->client->calls = [];
        $this->client->configurations['blog'] = static fn (): Outcome => Outcome::succeeded()->replay();
        $pushes->deliver($push->id(), null);
        self::assertSame(['configuration:blog:'.$push->id(), 'configuration:news:'.$push->id()], $this->client->calls, 'Only the sites not reached are tried again.');

        self::assertSame([
            'push.delivered news 0 failed: The agent could not be reached: no answer',
            'push.delivered blog 1 replayed',
            'push.delivered shop 1 applied: 1 settings applied.',
            'push.delivered news 0 failed: The agent could not be reached: no answer',
            'push.delivered blog 0 failed: Refused',
            'push.created  1 mail.footer to blog, news, shop',
        ], array_map(static fn (JournalEntry $entry): string => \sprintf('%s %s %d %s', $entry->action()->value, $entry->siteId(), (int) $entry->succeeded(), $entry->message()), \array_slice($this->journal->recent(20), 0, 6)));
        self::assertSame($push->id(), $this->journal->recent(1)[0]->reference());
    }

    public function testAPushToARemovedSiteFailsForThatSite(): void
    {
        $this->sites('shop');
        $push = $this->pushes()->create(['a' => 1], ['shop'], null);
        $this->registry->remove('shop', null);

        self::assertSame('The console knows no site "shop".', $this->pushes()->deliver($push->id(), null)->deliveries()[0]->message);
    }

    public function testAPlanRunsBatchByBatchAndStopsAtTheFirstFailure(): void
    {
        $this->sites('a', 'b', 'c', 'd', 'e');
        $succeeds = static fn (UpdateRequest $request): Outcome => Outcome::succeeded('ok '.$request->version);
        $this->client->updates = ['a' => $succeeds, 'b' => $succeeds, 'c' => $succeeds, 'd' => static fn (): Outcome => Outcome::failed('tests'), 'e' => $succeeds];
        $plans = $this->plans();

        $plan = $plans->plan('phil/url', '0.1.5', ['a', 'b', 'c', 'd', 'e'], 2, 'ada');
        self::assertSame(PlanState::Planned, $plan->state());
        self::assertSame($plan, $plans->get($plan->id()));
        self::assertSame([$plan], $plans->recent(0));
        self::assertSame('update.planned', $this->journal->recent(1)[0]->action()->value);
        self::assertSame('phil/url 0.1.5 on a, b, c, d, e, batches of 2', $this->journal->recent(1)[0]->message());

        $plans->run($plan->id(), 1, 'ada');
        self::assertSame(['update:a:'.$plan->id().':a', 'update:b:'.$plan->id().':b'], $this->client->calls);
        self::assertSame(PlanState::Running, $plan->state());

        $plans->run($plan->id(), null, 'ada');
        self::assertSame(PlanState::Stopped, $plan->state());
        self::assertSame([StepOutcome::Succeeded, StepOutcome::Succeeded, StepOutcome::Succeeded, StepOutcome::Failed, StepOutcome::Skipped], array_map(static fn (UpdateStep $step): StepOutcome => $step->outcome, $plan->steps()));
        self::assertSame(4, $this->calls());
        self::assertSame('update.step d 0 failed: tests', \sprintf('%s %s %d %s', $this->journal->recent(1)[0]->action()->value, $this->journal->recent(1)[0]->siteId(), (int) $this->journal->recent(1)[0]->succeeded(), $this->journal->recent(1)[0]->message()));
        self::assertSame('succeeded: ok 0.1.5', $this->journal->recent(2)[1]->message());

        $plans->run($plan->id(), null, 'ada');
        self::assertSame(4, $this->calls(), 'A stopped plan runs no more.');
    }

    public function testBatchesCountFromTheNextPendingSite(): void
    {
        $this->sites('a', 'b', 'c');
        $succeeds = static fn (): Outcome => Outcome::succeeded();
        $this->client->updates = ['a' => $succeeds, 'b' => $succeeds, 'c' => $succeeds];
        $plans = $this->plans();
        $plan = $plans->plan('phil/url', '1.0', ['a', 'b', 'c'], 1, null);

        $plans->run($plan->id(), 0, null);
        self::assertSame(1, $this->calls(), 'At least one batch runs.');
        $plans->run($plan->id(), 2, null);
        self::assertSame(PlanState::Completed, $plan->state());
        self::assertSame('update.step c 1 succeeded', \sprintf('%s %s %d %s', $this->journal->recent(1)[0]->action()->value, $this->journal->recent(1)[0]->siteId(), (int) $this->journal->recent(1)[0]->succeeded(), $this->journal->recent(1)[0]->message()));
    }

    public function testAPlanForARemovedSiteStops(): void
    {
        $this->sites('a');
        $plan = $this->plans()->plan('phil/url', '1.0', ['a'], 1, null);
        $this->registry->remove('a', null);

        self::assertSame('The console knows no site "a".', $this->plans()->run($plan->id(), null, null)->steps()[0]->message);
    }

    public function testAgentFailures(): void
    {
        self::assertSame('The agent answered 401 without a valid signature.', AgentFailure::unsigned(401)->getMessage());
        self::assertSame('The agent answered 501.', AgentFailure::status(501, '')->getMessage());
        self::assertSame('The agent answered 422: no', AgentFailure::status(422, 'no')->getMessage());
        $previous = new RuntimeException('x');
        self::assertSame($previous, AgentFailure::unreachable('x', $previous)->getPrevious());
        self::assertSame($previous, AgentFailure::malformed('x', $previous)->getPrevious());
    }

    private function puller(?RecordingLogger $logger = null): Puller
    {
        return new Puller($this->registry, $this->client, $this->snapshots, new AlertRules(['phil/*']), $this->clock, $this->dispatcher, $logger);
    }

    private function calls(): int
    {
        return \count($this->client->calls);
    }

    private function pushes(): ConfigurationPushes
    {
        return new ConfigurationPushes($this->registry, $this->pushRepository(), $this->client, $this->journal, $this->clock);
    }

    private ?InMemoryConfigurationPushRepository $pushRepository = null;

    private ?InMemoryUpdatePlanRepository $planRepository = null;

    private function pushRepository(): InMemoryConfigurationPushRepository
    {
        return $this->pushRepository ??= new InMemoryConfigurationPushRepository();
    }

    private function plans(): UpdatePlans
    {
        return new UpdatePlans($this->registry, $this->planRepository ??= new InMemoryUpdatePlanRepository(), $this->client, $this->journal, $this->clock);
    }

    private function sites(string ...$ids): void
    {
        foreach ($ids as $id) {
            $this->registry->register($id, ucfirst($id), 'https://'.$id.'.example.com/agent', Keys::site()->verifyingKey()->toBase64(), [], null);
        }
    }

    /**
     * @param list<Site> $sites
     *
     * @return list<string>
     */
    private function ids(array $sites): array
    {
        return array_map(static fn (Site $site): string => $site->id(), $sites);
    }

    /**
     * @return list<string>
     */
    private function alertKeys(): array
    {
        return array_map(static fn (object $event): string => $event instanceof SiteAlertRaised ? $event->alert->key() : '', $this->dispatcher->events);
    }
}
