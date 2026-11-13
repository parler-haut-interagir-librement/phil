<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Messenger;

use DateTimeImmutable;
use Phil\Automations\Catalogue\Catalogue;
use Phil\Automations\Definition\AutomationDefinition;
use Phil\Automations\Definition\AutomationId;
use Phil\Automations\Definition\InMemoryDefinitions;
use Phil\Automations\Definition\Step;
use Phil\Automations\Definition\TriggerReference;
use Phil\Automations\Engine\AutomationEngine;
use Phil\Automations\Messenger\AdvanceRun;
use Phil\Automations\Messenger\AdvanceRunHandler;
use Phil\Automations\Messenger\AutomationsSchedule;
use Phil\Automations\Messenger\ResumeDueRuns;
use Phil\Automations\Messenger\ResumeDueRunsHandler;
use Phil\Automations\Run\InMemoryRunStore;
use Phil\Automations\Run\RunState;
use Phil\Automations\Tests\Unit\Fixture\OrderPaid;
use Phil\Automations\Tests\Unit\Fixture\OrderPaidTrigger;
use Phil\Automations\Tests\Unit\Fixture\RecordingBus;
use Phil\Automations\Tests\Unit\Fixture\ScriptedAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;

#[CoversClass(AdvanceRun::class)]
#[CoversClass(AdvanceRunHandler::class)]
#[CoversClass(ResumeDueRuns::class)]
#[CoversClass(ResumeDueRunsHandler::class)]
#[CoversClass(AutomationsSchedule::class)]
final class MessengerTest extends TestCase
{
    public function testTheHandlersAdvanceAndResumeRuns(): void
    {
        $clock = new MockClock('2026-11-13 10:00:00 UTC');
        $bus = new RecordingBus();
        $runs = new InMemoryRunStore();
        $definitions = new InMemoryDefinitions([new AutomationDefinition(AutomationId::fromString('a'), 1, 'A', TriggerReference::of('shop.order_paid'), [Step::delay('wait', 60), Step::action('one', 'test.act')])]);
        $engine = new AutomationEngine(new Catalogue([new OrderPaidTrigger()], [new ScriptedAction()]), $definitions, $runs, $bus, $clock);
        [$id] = $engine->handle(new OrderPaid('1', 'c'));

        new AdvanceRunHandler($engine)(new AdvanceRun($id->value));
        self::assertSame(RunState::Waiting, $runs->get($id)?->state);

        $clock->modify('+60 seconds');
        new ResumeDueRunsHandler($engine)(new ResumeDueRuns(10));
        $last = end($bus->envelopes);
        self::assertNotFalse($last);
        self::assertInstanceOf(AdvanceRun::class, $last->getMessage());
        self::assertCount(3, $bus->envelopes);
        self::assertSame(500, new ResumeDueRuns()->limit);
    }

    public function testTheScheduleSendsResumeDueRunsRegularly(): void
    {
        $messages = new AutomationsSchedule('5 minutes', 50)->getSchedule()->getRecurringMessages();

        self::assertCount(1, $messages);
        $trigger = $messages[0]->getTrigger();
        self::assertInstanceOf(PeriodicalTrigger::class, $trigger);
        self::assertSame('every 5 minutes', (string) $trigger);
        $message = iterator_to_array($messages[0]->getMessages(new MessageContext('automations', 'id', $trigger, new DateTimeImmutable())))[0];
        self::assertInstanceOf(ResumeDueRuns::class, $message);
        self::assertSame(50, $message->limit);
        self::assertSame('every 1 minute', (string) new AutomationsSchedule()->getSchedule()->getRecurringMessages()[0]->getTrigger());
    }

    public function testTheScheduleSendsFiveHundredRunsAtMostByDefault(): void
    {
        $messages = new AutomationsSchedule()->getSchedule()->getRecurringMessages();
        $trigger = $messages[0]->getTrigger();
        $message = iterator_to_array($messages[0]->getMessages(new MessageContext('automations', 'id', $trigger, new DateTimeImmutable())))[0];

        self::assertInstanceOf(ResumeDueRuns::class, $message);
        self::assertSame(500, $message->limit);
    }
}
