<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Engine;

use Phil\Automations\Catalogue\Catalogue;
use Phil\Automations\Definition\AutomationDefinition;
use Phil\Automations\Definition\AutomationId;
use Phil\Automations\Definition\InMemoryDefinitions;
use Phil\Automations\Definition\Options;
use Phil\Automations\Definition\Step;
use Phil\Automations\Definition\TriggerReference;
use Phil\Automations\Engine\ActionContext;
use Phil\Automations\Engine\AutomationEngine;
use Phil\Automations\Engine\Payload;
use Phil\Automations\Exception\ActionFailed;
use Phil\Automations\Expression\ExpressionConditions;
use Phil\Automations\Messenger\AdvanceRun;
use Phil\Automations\Run\RunId;
use Phil\Automations\Run\RunState;
use Phil\Automations\Tests\Unit\Fixture\OrderPaid;
use Phil\Automations\Tests\Unit\Fixture\OrderPaidTrigger;
use Phil\Automations\Tests\Unit\Fixture\ProbeAction;
use Phil\Automations\Tests\Unit\Fixture\RecordingBus;
use Phil\Automations\Tests\Unit\Fixture\RecordingLogger;
use Phil\Automations\Tests\Unit\Fixture\ScriptedAction;
use Phil\Automations\Tests\Unit\Fixture\WatchedRunStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[CoversClass(AutomationEngine::class)]
final class AutomationEngineEdgesTest extends TestCase
{
    private MockClock $clock;

    private RecordingBus $bus;

    private WatchedRunStore $runs;

    private InMemoryDefinitions $definitions;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-13 10:00:00 UTC');
        $this->bus = new RecordingBus();
        $this->runs = new WatchedRunStore();
        $this->definitions = new InMemoryDefinitions();
    }

    public function testADefinitionWhoseOptionsDoNotMatchLeavesTheNextOnesToTheEvent(): void
    {
        $this->definitions->publish($this->definition('a', [Step::action('one', 'test.act')], 'pro'));
        $this->definitions->publish($this->definition('b', [Step::action('one', 'test.act')]));
        $this->definitions->publish($this->definition('c', [Step::action('one', 'test.act')]));

        $started = $this->engine([new ScriptedAction()])->handle(new OrderPaid('42', 'customer-7'));

        self::assertEquals([RunId::for(AutomationId::fromString('b'), 'order:42'), RunId::for(AutomationId::fromString('c'), 'order:42')], $started);
        self::assertCount(2, $this->bus->envelopes);
    }

    public function testAFailedRunIsLoggedWithItsRunItsAutomationAndItsState(): void
    {
        $logger = new RecordingLogger();
        $this->definitions->publish($this->definition('a', [Step::action('one', 'test.act')]));
        $engine = $this->engine([new ScriptedAction('test.act', [ActionFailed::permanent('No.')])], $logger);
        [$id] = $engine->handle(new OrderPaid('42', 'customer-7'));

        $engine->advance($id);

        self::assertCount(1, $logger->records);
        self::assertSame('warning', $logger->records[0]['level']);
        self::assertSame('The automation run {run} of {automation} failed.', $logger->records[0]['message']);
        self::assertSame(['run' => $id->value, 'automation' => 'a', 'state' => 'failed'], $logger->records[0]['context']);
    }

    public function testARunWhoseVersionIsGoneIsSavedOnce(): void
    {
        $this->definitions->publish($this->definition('a', [Step::action('one', 'test.act')]));
        $engine = $this->engine([new ScriptedAction()]);
        [$id] = $engine->handle(new OrderPaid('42', 'customer-7'));

        $engine = new AutomationEngine(new Catalogue([new OrderPaidTrigger()], [new ScriptedAction()]), new InMemoryDefinitions(), $this->runs, $this->bus, $this->clock);
        $engine->advance($id);

        self::assertSame(1, $this->runs->saves);
        self::assertSame(RunState::Failed, $this->runs->get($id)?->state);
    }

    public function testTheSweepReadsTheLimitItIsGivenAtLeastOne(): void
    {
        $engine = $this->engine([new ScriptedAction()]);

        $engine->resumeDue();
        $engine->resumeDue(0);
        $engine->resumeDue(-5);
        $engine->resumeDue(1);
        $engine->resumeDue(7);

        self::assertSame([500, 1, 1, 1, 7], $this->runs->limits);
    }

    public function testTheSweepSendsOneMessageForEachRunDue(): void
    {
        $this->definitions->publish($this->definition('a', [Step::action('one', 'test.act')]));
        $this->definitions->publish($this->definition('b', [Step::action('one', 'test.act')]));

        $engine = $this->engine([new ScriptedAction()]);
        $started = $engine->handle(new OrderPaid('42', 'customer-7'));
        $before = \count($this->bus->envelopes);

        self::assertSame(2, $engine->resumeDue());

        self::assertCount($before + 2, $this->bus->envelopes);
        $sent = array_map(static function (Envelope $envelope): string {
            $message = $envelope->getMessage();
            self::assertInstanceOf(AdvanceRun::class, $message);

            return $message->runId;
        }, \array_slice($this->bus->envelopes, $before));
        sort($sent);
        $expected = array_map(static fn (RunId $id): string => $id->value, $started);
        sort($expected);
        self::assertSame($expected, $sent);
    }

    public function testAStepIsTriedOnceMoreThanItsRetriesThenFails(): void
    {
        $action = new ScriptedAction('test.act', [new RuntimeException('Down.')]);
        $this->definitions->publish($this->definition('a', [Step::action('one', 'test.act', null, 2)]));
        $engine = $this->engine([$action]);
        [$id] = $engine->handle(new OrderPaid('42', 'customer-7'));

        for ($i = 0; $i < 6; ++$i) {
            $engine->advance($id);
            $this->clock->modify('+1 hour');
        }

        self::assertCount(3, $action->calls);
        self::assertSame([0, 1, 2], array_map(static fn (ActionContext $context): int => $context->attempt, $action->calls));
        self::assertSame(RunState::Failed, $this->runs->get($id)?->state);
        $delayed = array_filter($this->bus->envelopes, static fn (Envelope $envelope): bool => $envelope->last(DelayStamp::class) instanceof DelayStamp);
        self::assertCount(2, $delayed);
    }

    public function testThePermanentFailureOfAStepWithRetriesNeverWaits(): void
    {
        $action = new ScriptedAction('test.act', [ActionFailed::permanent('No.')]);
        $this->definitions->publish($this->definition('a', [Step::action('one', 'test.act', null, 5)]));
        $engine = $this->engine([$action]);
        [$id] = $engine->handle(new OrderPaid('42', 'customer-7'));
        $saves = $this->runs->saves;

        $engine->advance($id);

        self::assertSame(RunState::Failed, $this->runs->get($id)?->state);
        self::assertSame($saves + 2, $this->runs->saves);
        self::assertCount(1, $this->bus->envelopes);
    }

    public function testTheBackoffDoublesFromThirtySecondsUpToAnHour(): void
    {
        $action = new ScriptedAction('test.act', [new RuntimeException('Down.')]);
        $this->definitions->publish($this->definition('a', [Step::action('one', 'test.act', null, 10)]));
        $engine = $this->engine([$action]);
        [$id] = $engine->handle(new OrderPaid('42', 'customer-7'));
        $waits = [];

        for ($i = 0; $i < 10; ++$i) {
            $engine->advance($id);
            $resumeAt = $this->runs->get($id)?->resumeAt;
            self::assertNotNull($resumeAt);
            $waits[] = $resumeAt->getTimestamp() - $this->clock->now()->getTimestamp();
            $this->clock->modify('+'.end($waits).' seconds');
        }

        self::assertSame([30, 60, 120, 240, 480, 960, 1920, 3600, 3600, 3600], $waits);
    }

    public function testADelayAfterARetriedActionStartsAtAttemptZero(): void
    {
        $action = new ScriptedAction('test.act', [new RuntimeException('Down.'), Payload::of(['done' => 'one'])]);
        $this->definitions->publish($this->definition('a', [Step::action('one', 'test.act', null, 1), Step::delay('wait', 60), Step::action('two', 'test.act')]));
        $engine = $this->engine([$action]);
        [$id] = $engine->handle(new OrderPaid('42', 'customer-7'));

        $engine->advance($id);
        self::assertSame(1, $this->runs->get($id)?->attempt);

        $this->clock->modify('+30 seconds');
        $engine->advance($id);

        $run = $this->runs->get($id);
        self::assertSame(RunState::Waiting, $run?->state);
        self::assertSame(0, $run->attempt);
        self::assertSame('2', $run->cursor?->toString());
    }

    #[DataProvider('leases')]
    public function testTheLeaseOfARunIsAtLeastOneSecond(?int $leaseSeconds, int $expected): void
    {
        $probe = new ProbeAction($this->runs);
        $this->definitions->publish($this->definition('a', [Step::action('one', 'test.probe'), Step::action('two', 'test.probe')]));
        $engine = new AutomationEngine(new Catalogue([new OrderPaidTrigger()], [$probe], [new ExpressionConditions()]), $this->definitions, $this->runs, $this->bus, $this->clock, null, $leaseSeconds ?? AutomationEngine::DEFAULT_LEASE_SECONDS);
        [$id] = $engine->handle(new OrderPaid('42', 'customer-7'));

        $engine->advance($id);

        $now = $this->clock->now()->getTimestamp();
        self::assertCount(2, $probe->leases());
        self::assertSame($now + $expected, $probe->leases()[0]?->getTimestamp());
        self::assertSame($now + $expected, $probe->leases()[1]?->getTimestamp());
    }

    /**
     * @return iterable<string, array{?int, int}>
     */
    public static function leases(): iterable
    {
        yield 'the default' => [null, 300];
        yield 'none' => [0, 1];
        yield 'a negative one' => [-30, 1];
        yield 'one second' => [1, 1];
        yield 'two seconds' => [2, 2];
        yield 'a minute' => [60, 60];
    }

    /**
     * @param list<ScriptedAction|ProbeAction> $actions
     */
    private function engine(array $actions, ?RecordingLogger $logger = null): AutomationEngine
    {
        return new AutomationEngine(new Catalogue([new OrderPaidTrigger()], $actions, [new ExpressionConditions()]), $this->definitions, $this->runs, $this->bus, $this->clock, $logger);
    }

    /**
     * @param list<Step> $steps
     */
    private function definition(string $id, array $steps, ?string $plan = null): AutomationDefinition
    {
        return new AutomationDefinition(AutomationId::fromString($id), 1, 'Automation '.$id, TriggerReference::of('shop.order_paid', Options::of(null === $plan ? [] : ['plan' => $plan])), $steps);
    }
}
