<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\SitesConsoleBundle\Domain\Contract\ConfigurationPushRepository;
use Phil\SitesConsoleBundle\Domain\Contract\JournalRepository;
use Phil\SitesConsoleBundle\Domain\Contract\KeyRotationRepository;
use Phil\SitesConsoleBundle\Domain\Contract\SiteRepository;
use Phil\SitesConsoleBundle\Domain\Contract\SnapshotRepository;
use Phil\SitesConsoleBundle\Domain\Contract\UpdatePlanRepository;
use Phil\SitesConsoleBundle\Domain\Model\ConfigurationPush;
use Phil\SitesConsoleBundle\Domain\Model\JournalEntry;
use Phil\SitesConsoleBundle\Domain\Model\KeyRotation;
use Phil\SitesConsoleBundle\Domain\Model\Site;
use Phil\SitesConsoleBundle\Domain\Model\Snapshot;
use Phil\SitesConsoleBundle\Domain\Model\UpdatePlan;
use Phil\SitesConsoleBundle\Domain\Status\DeliveryOutcome;
use Phil\SitesConsoleBundle\Domain\Status\HealthState;
use Phil\SitesConsoleBundle\Domain\Status\JournalAction;
use Phil\SitesConsoleBundle\Domain\Status\KeyOperation;
use Phil\SitesConsoleBundle\Domain\Status\PlanState;
use Phil\SitesConsoleBundle\Domain\Status\RotationPhase;
use Phil\SitesConsoleBundle\Domain\Status\RotationStep;
use Phil\SitesConsoleBundle\Domain\Status\StepOutcome;
use Phil\SitesConsoleBundle\Domain\ValueObject\Delivery;
use Phil\SitesConsoleBundle\Domain\ValueObject\Outcome;
use Phil\SitesConsoleBundle\Domain\ValueObject\Settings;
use Phil\SitesConsoleBundle\Domain\ValueObject\UpdateStep;
use Phil\SitesConsoleBundle\Tests\Unit\Support\Fixtures;
use Phil\SitesConsoleBundle\Tests\Unit\Support\Keys;
use PHPUnit\Framework\TestCase;

/**
 * What every store keeps and gives back, whatever keeps it.
 */
abstract class RepositoryTestCase extends TestCase
{
    abstract protected function sites(): SiteRepository;

    abstract protected function snapshots(): SnapshotRepository;

    abstract protected function pushes(): ConfigurationPushRepository;

    abstract protected function plans(): UpdatePlanRepository;

    abstract protected function journal(): JournalRepository;

    abstract protected function rotations(): KeyRotationRepository;

    /**
     * Forgets the objects the store holds in memory, so that the next read comes from storage.
     */
    abstract protected function clear(): void;

    public function testKeyRotations(): void
    {
        $console = Keys::console()->verifyingKey();
        $next = Keys::next()->verifyingKey();
        $first = KeyRotation::start($console, $next, ['shop', 'blog'], 'ada', new DateTimeImmutable('2026-11-20 10:00:00'));
        $this->rotations()->save($first);
        $first->record(KeyOperation::Stage, 'shop', Outcome::succeeded('staged'), new DateTimeImmutable('2026-11-20 10:01:00'));
        $first->skip('blog', new DateTimeImmutable('2026-11-20 10:02:00'));
        $first->switchSigning(new DateTimeImmutable('2026-11-20 10:03:00'));
        $this->rotations()->save($first);
        $this->clear();

        $read = $this->rotations()->get($first->id());
        self::assertNotNull($read);
        self::assertSame(RotationPhase::Retiring, $read->phase());
        self::assertTrue($read->previousKey()->equals($console));
        self::assertTrue($read->nextKey()->equals($next));
        self::assertSame('ada', $read->startedBy());
        self::assertEquals(new DateTimeImmutable('2026-11-20 10:00:00'), $read->startedAt());
        self::assertEquals(new DateTimeImmutable('2026-11-20 10:03:00'), $read->updatedAt());
        self::assertSame([
            ['site' => 'shop', 'stage' => RotationStep::Done, 'retire' => RotationStep::Pending, 'message' => 'staged'],
            ['site' => 'blog', 'stage' => RotationStep::Skipped, 'retire' => RotationStep::Skipped, 'message' => 'The site left the console.'],
        ], $read->progress());
        self::assertSame($first->id(), $this->rotations()->unfinished()?->id());
        self::assertSame($first->id(), $this->rotations()->latestTo($next->toBase64())?->id());
        self::assertNull($this->rotations()->latestTo($console->toBase64()));
        self::assertNull($this->rotations()->get('01JZZZZZZZZZZZZZZZZZZZZZZZ'));

        $read->record(KeyOperation::Retire, 'shop', Outcome::succeeded(), new DateTimeImmutable('2026-11-20 10:04:00'));
        $read->finish(new DateTimeImmutable('2026-11-20 10:04:00'));
        $this->rotations()->save($read);
        $back = KeyRotation::start($next, $console, [], null, new DateTimeImmutable('2026-11-21 10:00:00'));
        $this->rotations()->save($back);
        $this->clear();

        self::assertSame($back->id(), $this->rotations()->unfinished()?->id());
        self::assertSame(RotationPhase::Finished, $this->rotations()->latestTo($next->toBase64())?->phase());
        self::assertSame($back->id(), $this->rotations()->latestTo($console->toBase64())?->id());

        $back = $this->rotations()->get($back->id());
        self::assertNotNull($back);
        $back->switchSigning(new DateTimeImmutable('2026-11-21 10:00:00'));
        $back->finish(new DateTimeImmutable('2026-11-21 10:00:00'));
        $this->rotations()->save($back);
        $this->clear();
        self::assertNull($this->rotations()->unfinished());

        $again = KeyRotation::start($console, $next, [], null, new DateTimeImmutable('2026-11-22 10:00:00'));
        $this->rotations()->save($again);
        $this->clear();
        $newest = $this->rotations()->latestTo($next->toBase64());
        self::assertNotNull($newest);
        self::assertSame($again->id(), $newest->id(), 'The newest rotation to a key wins.');
        self::assertSame(RotationPhase::Staging, $newest->phase());
    }

    public function testTheNewestUnfinishedRotationIsTheOneUnderWay(): void
    {
        $older = KeyRotation::start(Keys::console()->verifyingKey(), Keys::next()->verifyingKey(), [], null, new DateTimeImmutable('2026-11-20 10:00:00'));
        $newer = KeyRotation::start(Keys::next()->verifyingKey(), Keys::console()->verifyingKey(), [], null, new DateTimeImmutable('2026-11-20 10:00:00'));
        $this->rotations()->save($older);
        $this->rotations()->save($newer);
        $this->clear();

        self::assertGreaterThan($older->id(), $newer->id());
        self::assertSame($newer->id(), $this->rotations()->unfinished()?->id());
    }

    public function testSites(): void
    {
        $shop = Fixtures::site('shop', ['eu']);
        $this->sites()->save($shop);
        $this->sites()->save(Fixtures::site('blog'));
        $this->clear();

        $read = $this->sites()->get('shop');
        self::assertNotNull($read);
        self::assertSame(['eu'], $read->groups());
        self::assertSame('https://shop.example.com/_sites-console', $read->agentUrl()->value);
        self::assertSame(['blog', 'shop'], array_map(static fn (Site $site): string => $site->id(), $this->sites()->all()));
        self::assertNull($this->sites()->get('none'));

        $this->sites()->remove($read);
        $this->clear();
        self::assertNull($this->sites()->get('shop'));
    }

    public function testSnapshots(): void
    {
        $first = Snapshot::reached('shop', Fixtures::status('shop', ['phil/url' => '0.1.4'], HealthState::Degraded), new DateTimeImmutable('2026-08-01 10:00:00'));
        $second = Snapshot::unreachable('shop', 'timeout', new DateTimeImmutable('2026-08-02 10:00:00'));
        $third = Snapshot::reached('shop', Fixtures::status(), new DateTimeImmutable('2026-11-20 10:00:00'));
        $old = Snapshot::reached('blog', Fixtures::status('blog'), new DateTimeImmutable('2026-01-01 10:00:00'));

        foreach ([$first, $second, $third, $old] as $snapshot) {
            $this->snapshots()->save($snapshot);
        }

        $this->clear();

        self::assertSame($third->id(), $this->snapshots()->latest('shop')?->id());
        self::assertSame([$third->id(), $second->id()], array_map(static fn (Snapshot $snapshot): string => $snapshot->id(), $this->snapshots()->history('shop', 2)));
        self::assertCount(1, $this->snapshots()->history('shop', 0));
        self::assertNull($this->snapshots()->latest('none'));

        $read = $this->snapshots()->history('shop', 3)[2];
        self::assertSame(HealthState::Degraded, $read->health()->state);
        self::assertSame(['phil/url' => '0.1.4'], $read->packages());
        self::assertSame('2026-08-01 10:00:00', $read->takenAt()->format('Y-m-d H:i:s'));
        self::assertSame('timeout', $this->snapshots()->history('shop', 3)[1]->error());

        self::assertSame(2, $this->snapshots()->purgeBefore(new DateTimeImmutable('2026-09-01 00:00:00')));
        $this->clear();
        self::assertSame([$third->id()], array_map(static fn (Snapshot $snapshot): string => $snapshot->id(), $this->snapshots()->history('shop', 10)));
        self::assertSame($old->id(), $this->snapshots()->latest('blog')?->id(), 'The latest snapshot of a site stays.');

        $this->snapshots()->removeSite('shop');
        $this->clear();
        self::assertNull($this->snapshots()->latest('shop'));
        self::assertNotNull($this->snapshots()->latest('blog'));
    }

    public function testThePurgeCountsTheSnapshotsOfEverySite(): void
    {
        foreach (['shop', 'blog'] as $site) {
            $this->snapshots()->save(Snapshot::unreachable($site, 'old', new DateTimeImmutable('2026-01-01 10:00:00')));
            $this->snapshots()->save(Snapshot::unreachable($site, 'older', new DateTimeImmutable('2026-01-02 10:00:00')));
            $this->snapshots()->save(Snapshot::unreachable($site, 'latest', new DateTimeImmutable('2026-01-03 10:00:00')));
        }

        self::assertSame(4, $this->snapshots()->purgeBefore(new DateTimeImmutable('2026-06-01 00:00:00')));
        self::assertSame(['latest'], array_map(static fn (Snapshot $snapshot): ?string => $snapshot->error(), $this->snapshots()->history('shop', 10)));
        self::assertSame(['latest'], array_map(static fn (Snapshot $snapshot): ?string => $snapshot->error(), $this->snapshots()->history('blog', 10)));
    }

    public function testPushes(): void
    {
        $first = ConfigurationPush::create(Settings::of(['mail.footer' => 'Hi', 'n' => 2]), ['shop', 'blog'], 'ada', Fixtures::now());
        $this->pushes()->save($first);
        $first->record('shop', Outcome::succeeded('ok'), Fixtures::now());
        $this->pushes()->save($first);
        $second = ConfigurationPush::create(Settings::of(['a' => true]), ['shop'], null, Fixtures::now());
        $this->pushes()->save($second);
        $this->clear();

        $read = $this->pushes()->get($first->id());
        self::assertNotNull($read);
        self::assertSame(['mail.footer' => 'Hi', 'n' => 2], $read->settings()->values);
        self::assertSame([DeliveryOutcome::Applied, DeliveryOutcome::Pending], array_map(static fn (Delivery $delivery): DeliveryOutcome => $delivery->outcome, $read->deliveries()));
        self::assertSame('ada', $read->createdBy());
        self::assertSame([$second->id(), $first->id()], array_map(static fn (ConfigurationPush $push): string => $push->id(), $this->pushes()->recent(5)));
        self::assertCount(1, $this->pushes()->recent(0));
        self::assertNull($this->pushes()->get('none'));
    }

    public function testPlans(): void
    {
        $first = UpdatePlan::plan('phil/url', '0.1.5', ['shop', 'blog'], 1, 'ada', Fixtures::now());
        $this->plans()->save($first);
        $first->record(0, Outcome::failed('broken'), Fixtures::now());
        $this->plans()->save($first);
        $second = UpdatePlan::plan('phil/url', '0.1.6', ['shop'], 1, null, Fixtures::now());
        $this->plans()->save($second);
        $this->clear();

        $read = $this->plans()->get($first->id());
        self::assertNotNull($read);
        self::assertSame(PlanState::Stopped, $read->state());
        self::assertSame([StepOutcome::Failed, StepOutcome::Skipped], array_map(static fn (UpdateStep $step): StepOutcome => $step->outcome, $read->steps()));
        self::assertSame('broken', $read->steps()[0]->message);
        self::assertSame([$second->id(), $first->id()], array_map(static fn (UpdatePlan $plan): string => $plan->id(), $this->plans()->recent(5)));
        self::assertCount(1, $this->plans()->recent(0));
        self::assertNull($this->plans()->get('none'));
    }

    public function testTheJournal(): void
    {
        $lines = [
            JournalEntry::record(JournalAction::SiteRegistered, 'ada', 'shop', null, true, 'one', Fixtures::now()),
            JournalEntry::record(JournalAction::PushCreated, 'ada', null, 'PUSH', true, 'two', Fixtures::now()),
            JournalEntry::record(JournalAction::PushDelivered, null, 'shop', 'PUSH', false, 'three', Fixtures::now()),
        ];

        foreach ($lines as $line) {
            $this->journal()->append($line);
        }

        $this->clear();

        self::assertSame(['three', 'two', 'one'], array_map(static fn (JournalEntry $entry): string => $entry->message(), $this->journal()->recent(10)));
        self::assertSame(['three', 'one'], array_map(static fn (JournalEntry $entry): string => $entry->message(), $this->journal()->recent(10, 'shop')));
        self::assertSame(['three'], array_map(static fn (JournalEntry $entry): string => $entry->message(), $this->journal()->recent(0)));
        $read = $this->journal()->recent(1)[0];
        self::assertSame(JournalAction::PushDelivered, $read->action());
        self::assertFalse($read->succeeded());
        self::assertSame('PUSH', $read->reference());
        self::assertNull($read->actor());
    }
}
