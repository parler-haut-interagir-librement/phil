<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Engine;

use Phil\Automations\Catalogue\Catalogue;
use Phil\Automations\Definition\AutomationDefinition;
use Phil\Automations\Definition\AutomationId;
use Phil\Automations\Definition\Condition;
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
use Phil\Automations\Run\AutomationRun;
use Phil\Automations\Run\CompletedStep;
use Phil\Automations\Run\InMemoryRunStore;
use Phil\Automations\Run\RunId;
use Phil\Automations\Run\RunState;
use Phil\Automations\Tests\Unit\Fixture\OrderPaid;
use Phil\Automations\Tests\Unit\Fixture\OrderPaidTrigger;
use Phil\Automations\Tests\Unit\Fixture\PlainAction;
use Phil\Automations\Tests\Unit\Fixture\RecordingBus;
use Phil\Automations\Tests\Unit\Fixture\ScriptedAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[CoversClass(AutomationEngine::class)]
final class AutomationEngineTest extends TestCase
{
    private MockClock $clock;

    private RecordingBus $bus;

    private InMemoryRunStore $runs;

    private InMemoryDefinitions $definitions;

    private ScriptedAction $action;

    private PlainAction $plain;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-13 10:00:00 UTC');
        $this->bus = new RecordingBus();
        $this->runs = new InMemoryRunStore();
        $this->definitions = new InMemoryDefinitions();
        $this->action = new ScriptedAction();
        $this->plain = new PlainAction();
    }

    public function testAnEventStartsOneRunPerMatchingDefinitionOnce(): void
    {
        $this->definitions->publish($this->definition('a', [Step::action('one', 'test.act')]));
        $this->definitions->publish($this->definition('b', [Step::action('one', 'test.act')], 'pro'));
        $this->definitions->publish($this->definition('c', [Step::action('one', 'test.act')]), false);

        $engine = $this->engine();

        $started = $engine->handle(new OrderPaid('42', 'customer-7'));

        self::assertEquals([RunId::for(AutomationId::fromString('a'), 'order:42')], $started);
        self::assertCount(1, $this->bus->envelopes);
        $message = $this->bus->envelopes[0]->getMessage();
        self::assertInstanceOf(AdvanceRun::class, $message);
        self::assertSame($started[0]->value, $message->runId);
        self::assertSame([], $engine->handle(new OrderPaid('42', 'customer-7')));
        self::assertSame([], $engine->handle(new stdClass()));
        self::assertCount(1, $this->bus->envelopes);
    }

    public function testARunGoesThroughItsActionsAndCompletes(): void
    {
        $this->definitions->publish($this->definition('a', [Step::action('one', 'test.act', Options::of(['label' => 'x'])), Step::action('two', 'test.act')]));
        $engine = $this->engine();
        [$id] = $engine->handle(new OrderPaid('42', 'customer-7', 'pro'));

        $engine->advance($id);

        $run = $this->stored($id);
        self::assertSame(RunState::Completed, $run->state);
        self::assertNull($run->cursor);
        self::assertNull($run->resumeAt);
        self::assertCount(2, $this->action->calls);
        [$first, $second] = $this->action->calls;
        self::assertSame('one', $first->stepId);
        self::assertSame('x', $first->options->string('label'));
        self::assertSame($id->value.'/one', $first->idempotencyKey);
        self::assertSame('customer-7', $first->subject);
        self::assertSame(['order' => '42', 'plan' => 'pro'], $first->payload->values);
        self::assertSame(0, $first->attempt);
        self::assertSame([], $first->outputs);
        self::assertSame(['one'], array_keys($second->outputs));
        self::assertSame(['done' => 'one'], $second->outputs['one']->values);
        self::assertSame(['one', 'two'], array_map(static fn (CompletedStep $step): string => $step->stepId, $run->completed));

        $engine->advance($id);
        self::assertCount(2, $this->action->calls);
    }

    public function testADelaySavesTheRunAsWaitingAndSendsADelayedMessage(): void
    {
        $this->definitions->publish($this->definition('a', [Step::delay('wait', 3600), Step::action('one', 'test.act')]));
        $engine = $this->engine();
        [$id] = $engine->handle(new OrderPaid('42', 'customer-7'));

        $engine->advance($id);

        $run = $this->stored($id);
        self::assertSame(RunState::Waiting, $run->state);
        self::assertSame('1', $run->cursor?->toString());
        self::assertSame('2026-11-13 11:00:00', $run->resumeAt?->format('Y-m-d H:i:s'));
        self::assertCount(2, $this->bus->envelopes);
        $delay = $this->bus->envelopes[1]->last(DelayStamp::class);
        self::assertInstanceOf(DelayStamp::class, $delay);
        self::assertSame(3_600_000, $delay->getDelay());

        $this->clock->modify('+3599 seconds');
        $engine->advance($id);
        self::assertSame(0, $this->calls());

        $this->clock->modify('+1 second');
        self::assertSame(1, $engine->resumeDue());
        $engine->advance($id);
        self::assertSame(1, $this->calls());
        self::assertSame(RunState::Completed, $this->stored($id)->state);
    }

    public function testATrailingDelayCompletesTheRun(): void
    {
        $this->definitions->publish($this->definition('a', [Step::action('one', 'test.act'), Step::delay('wait', 60)]));
        $engine = $this->engine();
        [$id] = $engine->handle(new OrderPaid('42', 'customer-7'));

        $engine->advance($id);

        self::assertSame(RunState::Completed, $this->stored($id)->state);
        self::assertCount(1, $this->bus->envelopes);
    }

    public function testAFilterThatDoesNotHoldEndsTheRunWithoutFailure(): void
    {
        $this->definitions->publish($this->definition('a', [
            Step::action('one', 'test.act'),
            Step::filter('pro', Condition::expression("payload['plan'] == 'pro'")),
            Step::action('two', 'test.act'),
        ]));
        $engine = $this->engine();
        [$basic] = $engine->handle(new OrderPaid('1', 'c'));
        [$pro] = $engine->handle(new OrderPaid('2', 'c', 'pro'));

        $engine->advance($basic);
        $engine->advance($pro);

        self::assertSame(RunState::Filtered, $this->stored($basic)->state);
        self::assertSame([], $this->action->compensations);
        self::assertSame(RunState::Completed, $this->stored($pro)->state);
        self::assertCount(3, $this->action->calls);
    }

    public function testABranchFollowsOneOfItsPathsThenTheStepsAfterIt(): void
    {
        $this->definitions->publish($this->definition('a', [
            Step::branch('plan', Condition::expression("payload['plan'] == 'pro' and subject == 'c'"), [Step::action('pro', 'test.act')], [Step::action('basic', 'test.act')]),
            Step::branch('empty', Condition::expression("(outputs['pro']['done'] ?? '') == 'pro'"), [], [Step::action('after-empty', 'test.act')]),
            Step::action('last', 'test.act'),
        ]));
        $engine = $this->engine();
        [$basic] = $engine->handle(new OrderPaid('1', 'c'));
        [$pro] = $engine->handle(new OrderPaid('2', 'c', 'pro'));

        $engine->advance($basic);
        $engine->advance($pro);

        self::assertSame(['basic', 'after-empty', 'last', 'pro', 'last'], array_map(static fn (ActionContext $context): string => $context->stepId, $this->action->calls));
    }

    public function testARetryableFailureWaitsWithABackoffUntilTheStepRunsOutOfRetries(): void
    {
        $this->action = new ScriptedAction('test.act', [new RuntimeException('down'), ActionFailed::retryable('still down'), Payload::of(['ok' => true])]);
        $this->definitions->publish($this->definition('a', [Step::action('one', 'test.act', retries: 2)]));
        $engine = $this->engine();
        [$id] = $engine->handle(new OrderPaid('42', 'c'));

        $engine->advance($id);
        $run = $this->stored($id);
        self::assertSame(RunState::Waiting, $run->state);
        self::assertSame(1, $run->attempt);
        self::assertSame('2026-11-13 10:00:30', $run->resumeAt?->format('Y-m-d H:i:s'));
        self::assertSame(30_000, $this->bus->envelopes[1]->last(DelayStamp::class)?->getDelay());

        $this->clock->modify('+30 seconds');
        $engine->advance($id);
        $run = $this->stored($id);
        self::assertSame(2, $run->attempt);
        self::assertSame('2026-11-13 10:01:30', $run->resumeAt?->format('Y-m-d H:i:s'));

        $this->clock->modify('+60 seconds');
        $engine->advance($id);
        self::assertSame(RunState::Completed, $this->stored($id)->state);
        self::assertSame([0, 1, 2], array_map(static fn (ActionContext $context): int => $context->attempt, $this->action->calls));
        self::assertSame([$id->value.'/one'], array_values(array_unique(array_map(static fn (ActionContext $context): string => $context->idempotencyKey, $this->action->calls))));
    }

    public function testTheBackoffStopsAtAnHour(): void
    {
        $this->action = new ScriptedAction('test.act', [new RuntimeException('down')]);
        $this->definitions->publish($this->definition('a', [Step::action('one', 'test.act', retries: 10)]));
        $engine = $this->engine();
        [$id] = $engine->handle(new OrderPaid('42', 'c'));

        for ($attempt = 0; $attempt < 8; ++$attempt) {
            $engine->advance($id);
            $this->clock->modify('+2 hours');
        }

        $last = end($this->bus->envelopes);
        self::assertNotFalse($last);
        self::assertSame(3_600_000, $last->last(DelayStamp::class)?->getDelay());
        self::assertSame(1_920_000, $this->bus->envelopes[7]->last(DelayStamp::class)?->getDelay());
    }

    public function testAPermanentFailureCompensatesTheStepsDoneInReverseOrder(): void
    {
        $this->action = new ScriptedAction('test.act', [Payload::of(['n' => 1]), Payload::of(['n' => 2]), ActionFailed::permanent('refused')]);
        $this->definitions->publish($this->definition('a', [
            Step::action('one', 'test.act', Options::of(['label' => 'first'])),
            Step::action('plain', 'test.plain'),
            Step::action('two', 'test.act'),
            Step::action('three', 'test.act', retries: 5),
        ]));
        $engine = $this->engine();
        [$id] = $engine->handle(new OrderPaid('42', 'c'));

        $engine->advance($id);

        $run = $this->stored($id);
        self::assertSame(RunState::Failed, $run->state);
        self::assertSame('Step "three": refused', $run->failure);
        self::assertSame(['two', 'one'], array_map(static fn (array $compensation): string => $compensation[0]->stepId, $this->action->compensations));
        self::assertSame(['n' => 2], $this->action->compensations[0][1]->values);
        self::assertSame('first', $this->action->compensations[1][0]->options->string('label'));
        self::assertSame(0, $this->action->compensations[1][0]->attempt);
        self::assertSame(1, $this->plain->calls);
    }

    public function testAFailedCompensationIsReported(): void
    {
        $this->action = new ScriptedAction('test.act', [Payload::empty(), ActionFailed::permanent('refused')], new RuntimeException('cannot undo'));
        $this->definitions->publish($this->definition('a', [Step::action('one', 'test.act'), Step::action('two', 'test.act')]));
        $engine = $this->engine();
        [$id] = $engine->handle(new OrderPaid('42', 'c'));

        $engine->advance($id);

        $run = $this->stored($id);
        self::assertSame(RunState::CompensationFailed, $run->state);
        self::assertSame('Step "two": refused Undoing step "one" failed: cannot undo', $run->failure);
    }

    public function testAConditionThatFailsFailsTheRun(): void
    {
        $this->definitions->publish($this->definition('a', [Step::action('one', 'test.act'), Step::filter('bad', Condition::expression("payload['missing'] == 1"))]));
        $engine = $this->engine();
        [$id] = $engine->handle(new OrderPaid('42', 'c'));

        $engine->advance($id);

        $run = $this->stored($id);
        self::assertSame(RunState::Failed, $run->state);
        self::assertStringStartsWith('Step "bad": The condition', (string) $run->failure);
        self::assertCount(1, $this->action->compensations);
    }

    public function testAnUnknownActionFailsTheRun(): void
    {
        $this->definitions->publish($this->definition('a', [Step::action('one', 'test.unknown', retries: 3)]));
        $engine = $this->engine();
        [$id] = $engine->handle(new OrderPaid('42', 'c'));

        $engine->advance($id);

        self::assertSame(RunState::Failed, $this->stored($id)->state);
        self::assertSame('Step "one": No action of type "test.unknown" is registered.', $this->stored($id)->failure);
    }

    public function testARunWhoseVersionIsGoneFails(): void
    {
        $definition = $this->definition('a', [Step::action('one', 'test.act')]);
        $this->definitions->publish($definition);
        $engine = $this->engine();
        [$id] = $engine->handle(new OrderPaid('42', 'c'));
        $other = new AutomationEngine($this->catalogue(), new InMemoryDefinitions(), $this->runs, $this->bus, $this->clock);

        $other->advance($id);

        self::assertSame(RunState::Failed, $this->stored($id)->state);
        self::assertSame('Version 1 of the automation is no longer kept.', $this->stored($id)->failure);
        self::assertSame([], $this->action->calls);
    }

    public function testARunHeldByAnotherWorkerIsLeftAlone(): void
    {
        $this->definitions->publish($this->definition('a', [Step::action('one', 'test.act')]));
        $engine = $this->engine();
        [$id] = $engine->handle(new OrderPaid('42', 'c'));
        $run = $this->stored($id);
        self::assertTrue($this->runs->save($run->claimed($this->clock->now(), $this->clock->now()->modify('+300 seconds'))));

        $engine->advance($id);
        self::assertCount(0, $this->action->calls);

        $this->clock->modify('+301 seconds');
        $engine->advance($id);
        self::assertCount(1, $this->action->calls);
        $engine->advance(RunId::for(AutomationId::fromString('a'), 'other'));
    }

    public function testTheLeaseIsRenewedAfterEachAction(): void
    {
        $this->definitions->publish($this->definition('a', [Step::action('one', 'test.act'), Step::delay('wait', 10)]));
        $engine = new AutomationEngine($this->catalogue(), $this->definitions, $this->runs, $this->bus, $this->clock, leaseSeconds: 60);
        [$id] = $engine->handle(new OrderPaid('42', 'c'));

        $engine->advance($id);

        self::assertSame(RunState::Completed, $this->stored($id)->state);
        self::assertSame(3, $this->stored($id)->revision);
    }

    private function calls(): int
    {
        return \count($this->action->calls);
    }

    private function engine(): AutomationEngine
    {
        return new AutomationEngine($this->catalogue(), $this->definitions, $this->runs, $this->bus, $this->clock);
    }

    private function catalogue(): Catalogue
    {
        return new Catalogue([new OrderPaidTrigger()], [$this->action, $this->plain], [new ExpressionConditions()]);
    }

    /**
     * @param list<Step> $steps
     */
    private function definition(string $id, array $steps, ?string $plan = null): AutomationDefinition
    {
        return new AutomationDefinition(AutomationId::fromString($id), 1, 'Automation '.$id, TriggerReference::of('shop.order_paid', Options::of(null === $plan ? [] : ['plan' => $plan])), $steps);
    }

    private function stored(RunId $id): AutomationRun
    {
        $run = $this->runs->get($id);
        self::assertInstanceOf(AutomationRun::class, $run);

        return $run;
    }
}
