<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Run;

use DateTimeImmutable;
use Phil\Automations\Contract\RunStore;
use Phil\Automations\Definition\AutomationDefinition;
use Phil\Automations\Definition\AutomationId;
use Phil\Automations\Definition\Step;
use Phil\Automations\Definition\StepPath;
use Phil\Automations\Definition\TriggerReference;
use Phil\Automations\Engine\Payload;
use Phil\Automations\Engine\TriggerMatch;
use Phil\Automations\Run\AutomationRun;
use Phil\Automations\Run\CompletedStep;
use Phil\Automations\Run\RunId;
use Phil\Automations\Run\RunState;
use PHPUnit\Framework\TestCase;

/**
 * Every promise of a run store.
 */
abstract class RunStoreTestCase extends TestCase
{
    abstract protected function store(): RunStore;

    public function testARunIsCreatedOnceAndReadBackWhole(): void
    {
        $store = $this->store();
        $run = self::newRun('order:1');

        self::assertTrue($store->create($run));
        self::assertFalse($store->create($run));
        self::assertEquals($run, $store->get($run->id));
        self::assertNull($store->get(self::newRun('order:2')->id));

        $at = self::at(100);
        $changed = $run->stepDone(new CompletedStep('one', 'test.act', Payload::of(['n' => 1, 'ok' => true, 'none' => null, 'id' => 'é'])), StepPath::fromString('1/then/0'), $at)->claimed($at, self::at(400));
        self::assertTrue($store->save($changed));
        self::assertEquals($changed->saved(), $store->get($run->id));
    }

    public function testARunIsSavedOnlyOverTheRevisionItWasReadAt(): void
    {
        $store = $this->store();
        $run = self::newRun('order:1');
        $store->create($run);

        self::assertTrue($store->save($run->movedTo(null, self::at(1))));
        self::assertFalse($store->save($run->movedTo(null, self::at(2))));
        self::assertSame(1, $store->get($run->id)?->revision);
        self::assertTrue($store->save($run->saved()->finished(RunState::Failed, 'Reason', self::at(3))));
        $stored = $store->get($run->id);
        self::assertNotNull($stored);
        self::assertSame(2, $stored->revision);
        self::assertSame('Reason', $stored->failure);
        self::assertNull($stored->cursor);
        self::assertNull($stored->resumeAt);
        self::assertFalse($store->save(self::newRun('order:9')));
    }

    public function testItListsTheRunsWhoseTimeHasComeOldestFirst(): void
    {
        $store = $this->store();
        $late = self::newRun('a')->waitingUntil(self::at(50), null, 0, self::at(0));
        $early = self::newRun('b')->waitingUntil(self::at(10), null, 0, self::at(0));
        $future = self::newRun('c')->waitingUntil(self::at(500), null, 0, self::at(0));
        $running = self::newRun('d')->claimed(self::at(0), self::at(20));
        $done = self::newRun('e')->finished(RunState::Completed, null, self::at(0));

        foreach ([$late, $early, $future, $running, $done] as $run) {
            $store->create($run);
        }

        self::assertEquals([$early->id, $running->id, $late->id], $store->due(self::at(50), 10));
        self::assertEquals([$early->id], $store->due(self::at(50), 1));
        self::assertEquals([$early->id], $store->due(self::at(10), 0));
        self::assertSame([], $store->due(self::at(9), 10));
        self::assertContainsOnlyInstancesOf(RunId::class, $store->due(self::at(50), 10));
    }

    public function testItForgetsTheFinishedRunsChangedBeforeAnInstant(): void
    {
        $store = $this->store();
        $old = self::newRun('a')->finished(RunState::Completed, null, self::at(10));
        $recent = self::newRun('b')->finished(RunState::Failed, 'x', self::at(100));
        $waiting = self::newRun('c')->waitingUntil(self::at(5), null, 0, self::at(5));

        foreach ([$old, $recent, $waiting] as $run) {
            $store->create($run);
        }

        self::assertSame(1, $store->purge(self::at(100)));
        self::assertNull($store->get($old->id));
        self::assertNotNull($store->get($recent->id));
        self::assertNotNull($store->get($waiting->id));
        self::assertSame(0, $store->purge(self::at(100)));
    }

    protected static function newRun(string $eventKey): AutomationRun
    {
        $definition = new AutomationDefinition(AutomationId::fromString('welcome'), 3, 'Welcome', TriggerReference::of('shop.order_paid'), [Step::delay('wait', 5)]);

        return AutomationRun::start($definition, new TriggerMatch($eventKey, 'customer-7', Payload::of(['order' => '1', 'count' => 2])), self::at(0));
    }

    protected static function at(int $seconds): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.(1_800_000_000 + $seconds));
    }
}
