<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Tests\Unit\Infrastructure;

use Phil\ConsoleBundle\Application\EnvironmentGuard;
use Phil\ConsoleBundle\Domain\SummaryMode;
use Phil\ConsoleBundle\Domain\TimestampFormat;
use Phil\ConsoleBundle\Domain\ValueObject\ConsoleSettings;
use Phil\ConsoleBundle\Infrastructure\Console\CommandGuardReader;
use Phil\ConsoleBundle\Infrastructure\Console\ConsoleOptions;
use Phil\ConsoleBundle\Infrastructure\Console\Run;
use Phil\ConsoleBundle\Infrastructure\Console\StreamStamps;
use Phil\ConsoleBundle\Infrastructure\EventListener\ConsoleListener;
use Phil\ConsoleBundle\Tests\Unit\Fixture\ClassicCommand;
use Phil\ConsoleBundle\Tests\Unit\Fixture\ConfirmedCommand;
use Phil\ConsoleBundle\Tests\Unit\Fixture\ForbiddenCommand;
use Phil\ConsoleBundle\Tests\Unit\Fixture\Gauge;
use Phil\ConsoleBundle\Tests\Unit\Fixture\MemoryConsoleOutput;
use Phil\ConsoleBundle\Tests\Unit\Fixture\RecordingSwitch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(ConsoleListener::class)]
#[CoversClass(ConsoleOptions::class)]
#[CoversClass(Run::class)]
final class ConsoleListenerTest extends TestCase
{
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-03-14 09:26:53.589+00:00');
    }

    public function testAForbiddenCommandDoesNotRun(): void
    {
        $tester = $this->tester('staging');

        self::assertSame(77, $tester->run(['command' => 'test:forbidden'], ['capture_stderr_separately' => true]));
        self::assertSame('', $tester->getDisplay());
        self::assertStringContainsString('The command "test:forbidden" cannot run in the "staging" environment.', $tester->getErrorOutput());
    }

    public function testAGuardedCommandRunsElsewhere(): void
    {
        $tester = $this->tester('dev');

        self::assertSame(0, $tester->run(['command' => 'test:forbidden']));
        self::assertSame("ran\n", $tester->getDisplay());
    }

    public function testTheEnvironmentsNameConfirms(): void
    {
        $tester = $this->tester('prod');
        $tester->setInputs(['prod']);

        self::assertSame(0, $tester->run(['command' => 'test:confirmed'], ['interactive' => true]));
        self::assertSame('The command "test:confirmed" runs in the "prod" environment. Type "prod" to continue: ran'."\n", $tester->getDisplay());

        $tester->setInputs(['production']);
        self::assertSame(77, $tester->run(['command' => 'test:confirmed'], ['interactive' => true, 'capture_stderr_separately' => true]));
        self::assertStringContainsString('did not run', $tester->getErrorOutput());
    }

    public function testANonInteractiveRunNeedsTheForcingOption(): void
    {
        $tester = $this->tester('prod');

        self::assertSame(77, $tester->run(['command' => 'test:confirmed'], ['interactive' => false, 'capture_stderr_separately' => true]));
        self::assertStringContainsString('add --force-environment', $tester->getErrorOutput());

        self::assertSame(0, $tester->run(['command' => 'test:confirmed', '--force-environment' => true], ['interactive' => false, 'capture_stderr_separately' => true]));
        self::assertSame("ran\n", $tester->getDisplay());
        self::assertSame("The confirmation of the \"prod\" environment is skipped by --force-environment.\n", $tester->getErrorOutput());
    }

    public function testTheTimestamps(): void
    {
        $tester = $this->tester('test');

        self::assertSame(3, $tester->run(['command' => 'test:classic', '--timestamps' => 'relative', '--force-environment' => true], ['capture_stderr_separately' => true]));
        self::assertSame("[+0.000s] one line\n[+0.000s] two\n[+0.000s] lines\n", $tester->getDisplay());
        self::assertSame("The confirmation of the \"test\" environment is skipped by --force-environment.\n", $tester->getErrorOutput());

        $tester->run(['command' => 'test:slow', '--timestamps' => null]);
        self::assertSame("[2026-03-14T09:26:53.589+00:00] before\n[2026-03-14T09:26:55.589+00:00] after\n", $tester->getDisplay());

        $tester->run(['command' => 'test:slow']);
        self::assertSame("before\nafter\n", $tester->getDisplay());
    }

    public function testTheTimestampsOfTheConfiguration(): void
    {
        $tester = $this->tester('test', new ConsoleSettings(timestamps: true, format: TimestampFormat::Relative));

        $tester->run(['command' => 'test:slow']);
        self::assertSame("[+0.000s] before\n[+2.000s] after\n", $tester->getDisplay());

        $tester->run(['command' => 'test:slow', '--timestamps' => 'none']);
        self::assertSame("before\nafter\n", $tester->getDisplay());

        $tester->run(['command' => 'test:slow', '--timestamps' => 'iso']);
        self::assertStringStartsWith('[2026-03-14T09:', $tester->getDisplay());
    }

    public function testAnUnknownFormatFails(): void
    {
        $tester = $this->tester('test');

        self::assertSame(1, $tester->run(['command' => 'test:slow', '--timestamps' => 'epoch'], ['capture_stderr_separately' => true]));
        self::assertStringContainsString('The "--timestamps" option accepts "iso", "relative" or "none", not "epoch".', $tester->getErrorOutput());
        self::assertSame('', $tester->getDisplay());
    }

    public function testACommandsOwnOptionIsLeftAlone(): void
    {
        $tester = $this->tester('test', new ConsoleSettings(timestamps: true), new RecordingSwitch());
        $command = $tester->application->find('test:slow');
        $command->addOption(ConsoleOptions::TIMESTAMPS, null, InputOption::VALUE_REQUIRED, 'Its own', 'x');
        $command->addOption(ConsoleOptions::DEBUG, null, InputOption::VALUE_NONE);

        $tester->run(['command' => 'test:slow', '--timestamps' => 'epoch', '--debug-phil' => true]);

        self::assertStringStartsWith('[2026-03-14T09:26:53.589+00:00] before', $tester->getDisplay());
        self::assertSame([], $tester->switch->calls);
    }

    public function testTheSummary(): void
    {
        $tester = $this->tester('test', gauge: new Gauge([1_000_000, 2_048_576]));

        $tester->run(['command' => 'test:slow', '-v' => true], ['capture_stderr_separately' => true]);
        self::assertSame("test:slow: exit code 0 in 2.000 s, memory +1.0 MiB, peak 4.0 MiB.\n", $tester->getErrorOutput());

        $tester->run(['command' => 'test:slow'], ['capture_stderr_separately' => true]);
        self::assertSame('', $tester->getErrorOutput());
    }

    public function testTheSummaryIsStampedAndAlwaysShown(): void
    {
        $tester = $this->tester('test', new ConsoleSettings(timestamps: true, format: TimestampFormat::Relative, summary: SummaryMode::Always));

        $tester->run(['command' => 'test:slow'], ['capture_stderr_separately' => true]);
        self::assertSame("[+2.000s] test:slow: exit code 0 in 2.000 s, memory +0 B, peak 4.0 MiB.\n", $tester->getErrorOutput());

        $tester->run(['command' => 'test:slow'], ['capture_stderr_separately' => true]);
        self::assertSame("[+0.000s] before\n[+2.000s] after\n", $tester->getDisplay());
    }

    public function testTheDebugChannels(): void
    {
        $tester = $this->tester('test', switch: new RecordingSwitch());

        $tester->run(['command' => 'test:slow', '--debug-phil' => ' doctrine, ,mailer,doctrine'], ['capture_stderr_separately' => true]);
        self::assertSame(['enable ["doctrine","mailer"]', 'disable'], $tester->switch->calls);
        self::assertSame("No logger channel named \"mailer\"; the channels are \"app\", \"doctrine\".\ndebug on\n", $tester->getErrorOutput());

        $tester->switch->calls = [];
        $tester->run(['command' => 'test:slow', '--debug-phil' => null], ['capture_stderr_separately' => true]);
        self::assertSame(['enable ["app","doctrine"]', 'disable'], $tester->switch->calls);
        self::assertSame("debug on\n", $tester->getErrorOutput());

        $tester->switch->calls = [];
        $tester->run(['command' => 'test:slow', '--debug-phil' => ' ']);
        self::assertSame(['enable ["app","doctrine"]', 'disable'], $tester->switch->calls);

        $tester->switch->calls = [];
        $tester->run(['command' => 'test:slow']);
        self::assertSame([], $tester->switch->calls);
    }

    public function testTheConfiguredDebugChannels(): void
    {
        $tester = $this->tester('test', new ConsoleSettings(debugChannels: ['doctrine']), new RecordingSwitch());

        $tester->run(['command' => 'test:slow', '--debug-phil' => null]);
        self::assertSame(['enable ["doctrine"]', 'disable'], $tester->switch->calls);
    }

    public function testWithoutSwitchTheDebugOptionDoesNotExist(): void
    {
        $tester = $this->tester('test');

        self::assertSame(1, $tester->run(['command' => 'test:slow', '--debug-phil' => null], ['capture_stderr_separately' => true]));
        self::assertStringContainsString('The "--debug-phil" option does not exist.', $tester->getErrorOutput());
        self::assertFalse($tester->application->find('test:slow')->getDefinition()->hasOption(ConsoleOptions::DEBUG));
    }

    public function testTheOptionsAreTheCommands(): void
    {
        $tester = $this->tester('test', switch: new RecordingSwitch());
        $tester->run(['command' => 'test:slow', '--help' => true]);

        self::assertFalse($tester->application->getDefinition()->hasOption(ConsoleOptions::TIMESTAMPS));
        $definition = $tester->application->find('help')->getDefinition();
        self::assertTrue($definition->hasOption(ConsoleOptions::TIMESTAMPS));
        self::assertTrue($definition->hasOption(EnvironmentGuard::FORCE_OPTION));
        self::assertTrue($definition->hasOption(ConsoleOptions::DEBUG));
        self::assertFalse($definition->getOption(ConsoleOptions::TIMESTAMPS)->getDefault());
        self::assertFalse($definition->getOption(ConsoleOptions::DEBUG)->getDefault());
        self::assertTrue($definition->getOption(ConsoleOptions::TIMESTAMPS)->isValueOptional());
        self::assertTrue($definition->getOption(ConsoleOptions::DEBUG)->isValueOptional());
        self::assertFalse($definition->getOption(EnvironmentGuard::FORCE_OPTION)->acceptValue());
        self::assertSame('Prefix each output line with the time: "iso", "relative" or "none"', $definition->getOption(ConsoleOptions::TIMESTAMPS)->getDescription());

        $tester->run(['command' => 'test:slow', '--timestamps' => 'iso']);
        self::assertStringStartsWith('[2026', $tester->getDisplay());
    }

    public function testACommandWithoutApplicationGetsTheOptions(): void
    {
        $command = new Command('a');
        $input = new ArrayInput(['--timestamps' => 'iso', '--unknown' => true]);

        self::assertSame([ConsoleOptions::TIMESTAMPS, EnvironmentGuard::FORCE_OPTION], new ConsoleOptions()->register($command, $input, false));
        self::assertTrue($command->getDefinition()->hasOption(ConsoleOptions::TIMESTAMPS));
        self::assertFalse($command->getDefinition()->hasOption(ConsoleOptions::DEBUG));
    }

    public function testAnApplicationOptionIsLeftToTheApplication(): void
    {
        $application = new Application();
        $application->getDefinition()->addOption(new InputOption(ConsoleOptions::TIMESTAMPS, null, InputOption::VALUE_NONE));
        $command = new Command('a');
        $application->addCommand($command);

        self::assertSame([EnvironmentGuard::FORCE_OPTION], new ConsoleOptions()->register($command, new ArrayInput([]), false));
        self::assertFalse($command->getNativeDefinition()->hasOption(ConsoleOptions::TIMESTAMPS));
        self::assertTrue($command->getNativeDefinition()->hasOption(EnvironmentGuard::FORCE_OPTION));
    }

    public function testTheInputIsBoundToTheOptions(): void
    {
        $command = new Command('a');
        $input = new ArrayInput(['--timestamps' => 'iso']);

        new ConsoleOptions()->register($command, $input, true);

        self::assertSame('iso', $input->getOption(ConsoleOptions::TIMESTAMPS));
        self::assertFalse($input->getOption(ConsoleOptions::DEBUG));
    }

    public function testAnEventWithoutCommandIsIgnored(): void
    {
        $listener = $this->listener();
        $input = new ArrayInput([]);
        $output = new MemoryConsoleOutput();

        $listener->onCommand(new ConsoleCommandEvent(null, $input, $output));
        $listener->onTerminate(new ConsoleTerminateEvent(new Command('a'), $input, $output, 0));

        self::assertSame('', $output->display().$output->errors());
    }

    public function testResetEndsTheInterruptedRuns(): void
    {
        $switch = new RecordingSwitch();
        $listener = $this->listener(new ConsoleSettings(timestamps: true, summary: SummaryMode::Always), $switch);
        $command = new Command('a');
        $input = new ArrayInput(['--debug-phil' => 'app']);
        $output = new MemoryConsoleOutput();

        $listener->onCommand(new ConsoleCommandEvent($command, $input, $output));
        $listener->reset();
        $listener->onTerminate(new ConsoleTerminateEvent($command, $input, $output, 0));

        $output->writeln('after');

        self::assertSame(['enable ["app"]', 'disable'], $switch->calls);
        self::assertSame("after\n", $output->display());
        self::assertSame("[2026-03-14T09:26:53.589+00:00] debug on\n", $output->errors());
    }

    public function testTheSummaryIsAComment(): void
    {
        $listener = $this->listener(new ConsoleSettings(summary: SummaryMode::Always));
        $command = new Command('a');
        $input = new ArrayInput([]);
        $output = new MemoryConsoleOutput(decorated: true);

        $listener->onCommand(new ConsoleCommandEvent($command, $input, $output));
        $listener->onTerminate(new ConsoleTerminateEvent(new Command('b'), $input, $output, 0));

        self::assertSame("\033[33ma: exit code 0 in 0.000 s, memory +0 B, peak 4.0 MiB.\033[39m\n", $output->errors());
    }

    public function testAPlainOutputGetsTheSummary(): void
    {
        $listener = $this->listener(new ConsoleSettings(summary: SummaryMode::Always));
        $command = new Command('a');
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $listener->onCommand(new ConsoleCommandEvent($command, $input, $output));
        $listener->onTerminate(new ConsoleTerminateEvent($command, $input, $output, 5));

        self::assertSame("a: exit code 5 in 0.000 s, memory +0 B, peak 4.0 MiB.\n", $output->fetch());
    }

    private function listener(ConsoleSettings $settings = new ConsoleSettings(), ?RecordingSwitch $switch = null, ?Gauge $gauge = null, string $environment = 'test'): ConsoleListener
    {
        return new ConsoleListener(
            new ConsoleOptions(),
            new CommandGuardReader(),
            new EnvironmentGuard($environment),
            new StreamStamps($this->clock),
            $gauge ?? new Gauge(),
            $this->clock,
            $settings,
            $switch,
        );
    }

    private function tester(string $environment, ConsoleSettings $settings = new ConsoleSettings(), ?RecordingSwitch $switch = null, ?Gauge $gauge = null): ListenedTester
    {
        $clock = $this->clock;
        $listener = $this->listener($settings, $switch, $gauge, $environment);
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ConsoleEvents::COMMAND, $listener->onCommand(...));
        $dispatcher->addListener(ConsoleEvents::TERMINATE, $listener->onTerminate(...));

        $application = new Application();
        $application->setAutoExit(false);
        $application->setDispatcher($dispatcher);
        $application->addCommand(new Command('test:forbidden', new ForbiddenCommand()));
        $application->addCommand(new Command('test:confirmed', new ConfirmedCommand()));
        $application->addCommand(new ClassicCommand());
        $application->addCommand(new Command('test:slow')->setCode(static function (OutputInterface $output) use ($clock): int {
            $output->writeln('before');
            $clock->sleep(2);
            $output->writeln('after');

            return 0;
        }));

        return new ListenedTester($application, $switch ?? new RecordingSwitch());
    }
}

/**
 * An application tester with its application and its switch at hand.
 */
final class ListenedTester extends ApplicationTester
{
    public function __construct(
        public readonly Application $application,
        public readonly RecordingSwitch $switch,
    ) {
        parent::__construct($application);
    }
}
