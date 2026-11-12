<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use LogicException;
use Phil\Resource\ResourceReference;
use Phil\ScheduledTransitionsBundle\Application\RunReport;
use Phil\ScheduledTransitionsBundle\Application\Scheduler;
use Phil\ScheduledTransitionsBundle\Infrastructure\Console\RunTransitionsCommand;
use Phil\ScheduledTransitionsBundle\Infrastructure\Doctrine\DoctrineScheduledTransitionRepository;
use Phil\ScheduledTransitionsBundle\Infrastructure\Scheduler\RunDueTransitions;
use Phil\ScheduledTransitionsBundle\Infrastructure\Scheduler\RunDueTransitionsHandler;
use Phil\ScheduledTransitionsBundle\Infrastructure\Scheduler\TransitionsSchedule;
use Phil\ScheduledTransitionsBundle\Infrastructure\Workflow\Subject;
use Phil\ScheduledTransitionsBundle\Infrastructure\Workflow\SubjectResolver;
use Phil\ScheduledTransitionsBundle\Infrastructure\Workflow\WorkflowTransitionHandler;
use Phil\ScheduledTransitionsBundle\Tests\Unit\Fixtures\Database;
use Phil\ScheduledTransitionsBundle\Tests\Unit\Fixtures\RecordingHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\SupportStrategy\InstanceOfSupportStrategy;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;

#[CoversClass(RunTransitionsCommand::class)]
#[CoversClass(RunDueTransitions::class)]
#[CoversClass(RunDueTransitionsHandler::class)]
#[CoversClass(TransitionsSchedule::class)]
#[CoversClass(WorkflowTransitionHandler::class)]
#[CoversClass(Subject::class)]
final class IntegrationTest extends TestCase
{
    public function testTheCommandRunsWhatIsDue(): void
    {
        $handler = new RecordingHandler();
        $scheduler = new Scheduler(new DoctrineScheduledTransitionRepository(Database::entityManager()), [$handler], new NativeClock(), 1);
        $scheduler->schedule(ResourceReference::of('page', '1'), 'publish', new DateTimeImmutable('-1 hour'));
        $scheduler->schedule(ResourceReference::of('page', '2'), 'publish', new DateTimeImmutable('-1 hour'));

        $command = new RunTransitionsCommand($scheduler);

        $tester = new CommandTester($command);
        self::assertSame(Command::SUCCESS, $tester->execute(['--limit' => '1']));
        self::assertSame("1 transition(s) applied, 0 failed.\n", $tester->getDisplay());

        $handler->failing = ['2'];
        $failing = new CommandTester($command);
        self::assertSame(Command::FAILURE, $failing->execute([]));
        self::assertSame("0 transition(s) applied, 1 failed.\n", $failing->getDisplay());
        self::assertSame(['phil:scheduled-transitions:run', 'Applies the scheduled transitions that are due', '100'], [$command->getName(), $command->getDescription(), $command->getDefinition()->getOption('limit')->getDefault()]);

        foreach (['0', 'x', '1234567', '01', "5\n"] as $limit) {
            $invalid = new CommandTester($command);
            self::assertSame(Command::INVALID, $invalid->execute(['--limit' => $limit]), $limit);
            self::assertSame("The limit is a whole number from 1 to 999999.\n", $invalid->getDisplay());
        }

        self::assertSame(Command::SUCCESS, new CommandTester($command)->execute(['--limit' => '999999']));
    }

    public function testSymfonySchedulerSendsARunAtEachTick(): void
    {
        $handler = new RecordingHandler();
        $scheduler = new Scheduler(new DoctrineScheduledTransitionRepository(Database::entityManager()), [$handler], new NativeClock());
        $scheduler->schedule(ResourceReference::of('page', '1'), 'publish', new DateTimeImmutable('-1 hour'));
        $scheduler->schedule(ResourceReference::of('page', '2'), 'publish', new DateTimeImmutable('-1 hour'));

        $messages = new TransitionsSchedule('5 minutes', 1)->getSchedule()->getRecurringMessages();
        self::assertCount(1, $messages);
        $trigger = $messages[0]->getTrigger();
        self::assertInstanceOf(PeriodicalTrigger::class, $trigger);
        self::assertSame('every 5 minutes', (string) $trigger);
        $message = iterator_to_array($messages[0]->getMessages(new MessageContext('x', 'y', $trigger, new DateTimeImmutable())), false)[0] ?? null;
        self::assertInstanceOf(RunDueTransitions::class, $message);
        self::assertSame(1, $message->limit);
        self::assertEquals(new RunReport(1, 0), new RunDueTransitionsHandler($scheduler)($message));
        self::assertSame([100, 'phil_transitions'], [new RunDueTransitions()->limit, TransitionsSchedule::NAME]);
        self::assertSame('every 1 minute', (string) new TransitionsSchedule()->getSchedule()->getRecurringMessages()[0]->getTrigger());
        $default = iterator_to_array(new TransitionsSchedule()->getSchedule()->getRecurringMessages()[0]->getMessages(new MessageContext('x', 'y', $trigger, new DateTimeImmutable())), false)[0] ?? null;
        self::assertInstanceOf(RunDueTransitions::class, $default);
        self::assertSame(100, $default->limit);
    }

    public function testAWorkflowAppliesTheTransition(): void
    {
        $subject = new class {
            public string $marking = 'draft';

            public function getMarking(): string
            {
                return $this->marking;
            }

            public function setMarking(string $marking): void
            {
                $this->marking = $marking;
            }
        };
        $definition = new Definition(['draft', 'published'], [new Transition('publish', 'draft', 'published')]);
        $registry = new Registry();
        $registry->addWorkflow(new Workflow($definition, new MethodMarkingStore(true), name: 'page'), new InstanceOfSupportStrategy($subject::class));

        $resolver = new class($subject) implements SubjectResolver {
            /** @var list<object> */
            public array $saved = [];

            public function __construct(
                private readonly object $subject,
            ) {
            }

            public function resolve(ResourceReference $resource): ?object
            {
                return match ($resource->id) {
                    '1' => $this->subject,
                    '2' => new stdClass(),
                    default => null,
                };
            }

            public function saved(object $subject): void
            {
                $this->saved[] = $subject;
            }
        };
        $unrelated = new class implements SubjectResolver {
            public function resolve(ResourceReference $resource): ?object
            {
                return null;
            }

            public function saved(object $subject): void
            {
            }
        };
        $handler = new WorkflowTransitionHandler($registry, [$unrelated, $resolver]);

        self::assertTrue($handler->supports(ResourceReference::of('page', '1'), 'publish'));
        self::assertFalse($handler->supports(ResourceReference::of('page', '2'), 'publish'));
        self::assertFalse($handler->supports(ResourceReference::of('page', '3'), 'publish'));
        self::assertFalse(new WorkflowTransitionHandler(null, [$resolver])->supports(ResourceReference::of('page', '1'), 'publish'));

        $handler->apply(ResourceReference::of('page', '1'), 'publish');
        self::assertSame(['published', [$subject]], [$subject->marking, $resolver->saved]);

        try {
            $handler->apply(ResourceReference::of('page', '3'), 'publish');
            self::fail('A transition was applied to nothing.');
        } catch (LogicException $logicException) {
            self::assertSame('page:3 is gone.', $logicException->getMessage());
        }

        $this->expectException(NotEnabledTransitionException::class);
        $handler->apply(ResourceReference::of('page', '1'), 'publish');
    }
}
