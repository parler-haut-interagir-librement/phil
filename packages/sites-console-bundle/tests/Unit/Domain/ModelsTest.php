<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Domain;

use Phil\SitesConsoleBundle\Domain\Exception\InvalidValue;
use Phil\SitesConsoleBundle\Domain\Model\ConfigurationPush;
use Phil\SitesConsoleBundle\Domain\Model\JournalEntry;
use Phil\SitesConsoleBundle\Domain\Model\Site;
use Phil\SitesConsoleBundle\Domain\Model\Snapshot;
use Phil\SitesConsoleBundle\Domain\Model\UpdatePlan;
use Phil\SitesConsoleBundle\Domain\Status\DeliveryOutcome;
use Phil\SitesConsoleBundle\Domain\Status\HealthState;
use Phil\SitesConsoleBundle\Domain\Status\JournalAction;
use Phil\SitesConsoleBundle\Domain\Status\PlanState;
use Phil\SitesConsoleBundle\Domain\Status\StepOutcome;
use Phil\SitesConsoleBundle\Domain\ValueObject\AgentUrl;
use Phil\SitesConsoleBundle\Domain\ValueObject\Delivery;
use Phil\SitesConsoleBundle\Domain\ValueObject\Outcome;
use Phil\SitesConsoleBundle\Domain\ValueObject\Settings;
use Phil\SitesConsoleBundle\Domain\ValueObject\UpdateStep;
use Phil\SitesConsoleBundle\Tests\Unit\Support\Fixtures;
use Phil\SitesConsoleBundle\Tests\Unit\Support\Keys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Site::class)]
#[CoversClass(Snapshot::class)]
#[CoversClass(ConfigurationPush::class)]
#[CoversClass(UpdatePlan::class)]
#[CoversClass(JournalEntry::class)]
#[CoversClass(Delivery::class)]
#[CoversClass(UpdateStep::class)]
final class ModelsTest extends TestCase
{
    public function testASiteKeepsWhatVerifiesItAndItsGroupsSorted(): void
    {
        $site = Site::register('shop-2', '  Shop  ', AgentUrl::fromString('https://shop.example.com/agent/'), Keys::site()->verifyingKey(), ['web', 'eu', 'web'], Fixtures::now());

        self::assertSame('shop-2', $site->id());
        self::assertSame('Shop', $site->name());
        self::assertSame('https://shop.example.com/agent', $site->agentUrl()->value);
        self::assertSame(Keys::site()->verifyingKey()->toBase64(), $site->verifyingKey()->toBase64());
        self::assertSame(['eu', 'web'], $site->groups());
        self::assertTrue($site->inGroup('eu'));
        self::assertFalse($site->inGroup('us'));
        self::assertSame(Fixtures::NOW, $site->createdAt()->format('Y-m-d H:i:s'));

        $site->change('Boutique', AgentUrl::fromString('http://localhost/agent', true), Keys::console()->verifyingKey(), []);
        self::assertSame('Boutique', $site->name());
        self::assertSame('http://localhost/agent', $site->agentUrl()->value);
        self::assertSame(Keys::console()->verifyingKey()->toBase64(), $site->verifyingKey()->toBase64());
        self::assertSame([], $site->groups());
    }

    public function testTheLimitsOfASite(): void
    {
        self::assertSame('a', Site::register('a', str_repeat('é', 100), AgentUrl::fromString('https://a.example.com'), Keys::site()->verifyingKey(), array_map(static fn (int $i): string => 'g'.$i, range(1, 20)), Fixtures::now())->id());
        self::assertSame(str_repeat('a', 64), Site::register(str_repeat('a', 64), 'A', AgentUrl::fromString('https://a.example.com'), Keys::site()->verifyingKey(), [], Fixtures::now())->id());
    }

    /**
     * @param list<string> $groups
     */
    #[DataProvider('badSites')]
    public function testABadSiteIsRefused(string $id, string $name, array $groups, string $reason): void
    {
        $this->expectExceptionObject(InvalidValue::because($reason));
        Site::register($id, $name, AgentUrl::fromString('https://a.example.com'), Keys::site()->verifyingKey(), $groups, Fixtures::now());
    }

    /**
     * @return iterable<string, array{string, string, list<string>, string}>
     */
    public static function badSites(): iterable
    {
        $id = 'The identifier of a site is 1 to 64 lowercase letters, digits and inner dashes, starting with a letter.';
        $name = 'The name of a site is 1 to 100 characters without control characters.';
        $group = 'A group is 1 to 32 lowercase letters, digits, dashes and underscores.';

        yield 'digit first' => ['1shop', 'Shop', [], $id];
        yield 'uppercase' => ['Shop', 'Shop', [], $id];
        yield 'trailing dash' => ['shop-', 'Shop', [], $id];
        yield 'too long' => [str_repeat('a', 65), 'Shop', [], $id];
        yield 'newline' => ["shop\n", 'Shop', [], $id];
        yield 'blank name' => ['shop', '   ', [], $name];
        yield 'long name' => ['shop', str_repeat('a', 101), [], $name];
        yield 'control in name' => ['shop', "a\tb", [], $name];
        yield 'bad group' => ['shop', 'Shop', ['EU'], $group];
        yield 'long group' => ['shop', 'Shop', [str_repeat('a', 33)], $group];
        yield 'too many groups' => ['shop', 'Shop', array_map(static fn (int $i): string => 'g'.$i, range(1, 21)), 'A site belongs to at most 20 groups.'];
    }

    public function testASnapshotOfASiteReachedOrNot(): void
    {
        $reached = Snapshot::reached('shop', Fixtures::status('shop', ['phil/url' => '0.1.4'], HealthState::Degraded), Fixtures::now());

        self::assertSame(26, \strlen($reached->id()));
        self::assertSame('shop', $reached->siteId());
        self::assertTrue($reached->isReachable());
        self::assertSame(HealthState::Degraded, $reached->health()->state);
        self::assertSame('database', $reached->health()->checks[0]->name);
        self::assertSame(['phil/url' => '0.1.4'], $reached->packages());
        self::assertSame('0.1.4', $reached->version('phil/url'));
        self::assertNull($reached->version('phil/enums'));
        self::assertSame('8.5.1', $reached->php());
        self::assertSame('8.2.0', $reached->symfony());
        self::assertNull($reached->error());
        self::assertSame(Fixtures::NOW, $reached->takenAt()->format('Y-m-d H:i:s'));

        $unreachable = Snapshot::unreachable('shop', str_repeat('é', 501), Fixtures::now());
        self::assertFalse($unreachable->isReachable());
        self::assertSame(HealthState::Unknown, $unreachable->health()->state);
        self::assertSame([], $unreachable->packages());
        self::assertNull($unreachable->php());
        self::assertNull($unreachable->symfony());
        self::assertSame(500, mb_strlen((string) $unreachable->error()));
        self::assertGreaterThan($reached->id(), $unreachable->id());
    }

    public function testAPushRecordsEachDelivery(): void
    {
        $push = ConfigurationPush::create(Settings::of(['mail.footer' => 'Hi']), ['shop', 'blog', 'shop'], 'ada', Fixtures::now());

        self::assertSame(26, \strlen($push->id()));
        self::assertSame(['mail.footer' => 'Hi'], $push->settings()->values);
        self::assertSame('ada', $push->createdBy());
        self::assertSame(Fixtures::NOW, $push->createdAt()->format('Y-m-d H:i:s'));
        self::assertSame(['shop', 'blog'], $push->undeliveredSites());
        self::assertEquals([new Delivery('shop', DeliveryOutcome::Pending), new Delivery('blog', DeliveryOutcome::Pending)], $push->deliveries());

        self::assertSame(DeliveryOutcome::Applied, $push->record('shop', Outcome::succeeded('ok'), Fixtures::now()));
        self::assertSame(DeliveryOutcome::Failed, $push->record('blog', Outcome::failed('down'), Fixtures::now()));
        self::assertSame(['blog'], $push->undeliveredSites());
        self::assertSame(DeliveryOutcome::Replayed, $push->record('blog', Outcome::succeeded()->replay(), Fixtures::now()));
        self::assertSame([], $push->undeliveredSites());
        self::assertSame('ok', $push->deliveries()[0]->message);
        self::assertSame('2026-11-20T10:00:00+00:00', $push->deliveries()[1]->at?->format(\DATE_ATOM));
        self::assertNull(ConfigurationPush::create(Settings::of(['a' => 1]), ['shop'], null, Fixtures::now())->createdBy());
    }

    public function testAPushGoesToSomeSitesAndOnlyRecordsThem(): void
    {
        self::assertCount(500, ConfigurationPush::create(Settings::of(['a' => 1]), array_map(static fn (int $i): string => 's'.$i, range(1, 500)), null, Fixtures::now())->deliveries());

        try {
            ConfigurationPush::create(Settings::of(['a' => 1]), [], null, Fixtures::now());
            self::fail('A push goes somewhere.');
        } catch (InvalidValue $invalidValue) {
            self::assertSame('A push goes to 1 to 500 sites.', $invalidValue->getMessage());
        }

        try {
            ConfigurationPush::create(Settings::of(['a' => 1]), array_map(static fn (int $i): string => 's'.$i, range(1, 501)), null, Fixtures::now());
            self::fail('A push goes to 500 sites at most.');
        } catch (InvalidValue $invalidValue) {
            self::assertSame('A push goes to 1 to 500 sites.', $invalidValue->getMessage());
        }

        $push = ConfigurationPush::create(Settings::of(['a' => 1]), ['shop'], null, Fixtures::now());
        $this->expectExceptionObject(InvalidValue::because(\sprintf('The push %s does not go to the site "blog".', $push->id())));
        $push->record('blog', Outcome::succeeded(), Fixtures::now());
    }

    public function testAPlanRunsInBatchesAndCompletes(): void
    {
        $plan = UpdatePlan::plan('phil/url', '0.1.5', ['a', 'b', 'c', 'a'], 2, 'ada', Fixtures::now());

        self::assertSame(26, \strlen($plan->id()));
        self::assertSame('phil/url', $plan->package());
        self::assertSame('0.1.5', $plan->version());
        self::assertSame(2, $plan->batchSize());
        self::assertSame('ada', $plan->createdBy());
        self::assertSame(Fixtures::NOW, $plan->createdAt()->format('Y-m-d H:i:s'));
        self::assertSame(PlanState::Planned, $plan->state());
        self::assertSame([1, 1, 2], array_map(static fn (UpdateStep $step): int => $step->batch, $plan->steps()));
        self::assertSame(0, $plan->next());
        self::assertSame('a', $plan->siteAt(0));
        self::assertSame(2, $plan->batchAt(2));
        self::assertSame($plan->id().':b', $plan->stepKey(1));

        self::assertSame(StepOutcome::Succeeded, $plan->record(0, Outcome::succeeded('done'), Fixtures::now()));
        self::assertSame(PlanState::Running, $plan->state());
        self::assertSame(1, $plan->next());
        $plan->record(1, Outcome::succeeded(), Fixtures::now());
        $plan->record(2, Outcome::succeeded(), Fixtures::now());
        self::assertSame(PlanState::Completed, $plan->state());
        self::assertNull($plan->next());
        self::assertSame('done', $plan->steps()[0]->message);
        self::assertSame('2026-11-20T10:00:00+00:00', $plan->steps()[0]->finishedAt?->format(\DATE_ATOM));
        self::assertSame([StepOutcome::Succeeded, StepOutcome::Succeeded, StepOutcome::Succeeded], array_map(static fn (UpdateStep $step): StepOutcome => $step->outcome, $plan->steps()));
    }

    public function testAPlanStopsAtTheFirstFailureAndSkipsTheRest(): void
    {
        $plan = UpdatePlan::plan('phil/url', '0.1.5', ['a', 'b', 'c'], 1, null, Fixtures::now());
        $plan->record(0, Outcome::succeeded(), Fixtures::now());

        self::assertSame(StepOutcome::Failed, $plan->record(1, Outcome::failed('broken'), Fixtures::now()));
        self::assertSame(PlanState::Stopped, $plan->state());
        self::assertNull($plan->next());
        self::assertSame([StepOutcome::Succeeded, StepOutcome::Failed, StepOutcome::Skipped], array_map(static fn (UpdateStep $step): StepOutcome => $step->outcome, $plan->steps()));
        self::assertNull($plan->steps()[2]->finishedAt);
        self::assertSame([1, 2, 3], array_map(static fn (UpdateStep $step): int => $step->batch, $plan->steps()));
        self::assertNull($plan->createdBy());

        $this->expectExceptionObject(InvalidValue::because(\sprintf('The step 2 of the plan %s is not pending.', $plan->id())));
        $plan->record(2, Outcome::succeeded(), Fixtures::now());
    }

    public function testAStepIsRecordedOnce(): void
    {
        $plan = UpdatePlan::plan('phil/url', '0.1.5', ['a', 'b'], 1, null, Fixtures::now());
        $plan->record(0, Outcome::succeeded(), Fixtures::now());

        $this->expectExceptionObject(InvalidValue::because(\sprintf('The step 0 of the plan %s is not pending.', $plan->id())));
        $plan->record(0, Outcome::succeeded(), Fixtures::now());
    }

    public function testAnUnknownStep(): void
    {
        $plan = UpdatePlan::plan('phil/url', '0.1.5', ['a'], 1, null, Fixtures::now());

        $this->expectExceptionObject(InvalidValue::because(\sprintf('The plan %s has no step 1.', $plan->id())));
        $plan->siteAt(1);
    }

    /**
     * @param list<string> $sites
     */
    #[DataProvider('badPlans')]
    public function testABadPlanIsRefused(string $package, string $version, array $sites, int $batchSize, string $reason): void
    {
        $this->expectExceptionObject(InvalidValue::because($reason));
        UpdatePlan::plan($package, $version, $sites, $batchSize, null, Fixtures::now());
    }

    /**
     * @return iterable<string, array{string, string, list<string>, int, string}>
     */
    public static function badPlans(): iterable
    {
        yield 'package' => ['Phil', '1.0', ['a'], 1, 'An update names a package and a version.'];
        yield 'version' => ['phil/url', '1 0', ['a'], 1, 'An update names a package and a version.'];
        yield 'no site' => ['phil/url', '1.0', [], 1, 'An update plan covers 1 to 500 sites.'];
        yield 'too many sites' => ['phil/url', '1.0', array_map(static fn (int $i): string => 's'.$i, range(1, 501)), 1, 'An update plan covers 1 to 500 sites.'];
        yield 'empty batch' => ['phil/url', '1.0', ['a'], 0, 'A batch holds at least one site.'];
    }

    public function testAPlanOfFiveHundredSites(): void
    {
        self::assertCount(500, UpdatePlan::plan('phil/url', '1.0', array_map(static fn (int $i): string => 's'.$i, range(1, 500)), 500, null, Fixtures::now())->steps());
    }

    public function testAJournalLine(): void
    {
        $entry = JournalEntry::record(JournalAction::PushDelivered, str_repeat('a', 181), 'shop', 'PUSH', false, str_repeat('é', 501), Fixtures::now());

        self::assertSame(26, \strlen($entry->id()));
        self::assertSame(180, mb_strlen((string) $entry->actor()));
        self::assertSame(JournalAction::PushDelivered, $entry->action());
        self::assertSame('shop', $entry->siteId());
        self::assertSame('PUSH', $entry->reference());
        self::assertFalse($entry->succeeded());
        self::assertSame(500, mb_strlen($entry->message()));
        self::assertSame(Fixtures::NOW, $entry->occurredAt()->format('Y-m-d H:i:s'));
        self::assertNull(JournalEntry::record(JournalAction::SiteRemoved, null, null, null, true, '', Fixtures::now())->actor());
    }
}
