<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Tests\Benchmark;

use Phil\ConsoleBundle\Application\EnvironmentGuard;
use Phil\ConsoleBundle\Domain\TimestampFormat;
use Phil\ConsoleBundle\Domain\ValueObject\ConsoleSettings;
use Phil\ConsoleBundle\Infrastructure\Console\CommandGuardReader;
use Phil\ConsoleBundle\Infrastructure\Console\ConsoleOptions;
use Phil\ConsoleBundle\Infrastructure\Console\LineStamper;
use Phil\ConsoleBundle\Infrastructure\Console\StreamStamps;
use Phil\ConsoleBundle\Infrastructure\EventListener\ConsoleListener;
use Phil\ConsoleBundle\Infrastructure\Runtime\PhpMemoryGauge;
use Phil\ConsoleBundle\Infrastructure\Runtime\SystemClock;
use Phil\ConsoleBundle\Tests\Unit\Fixture\ForbiddenCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * What the bundle adds to a command: the listener before and after a guarded command that runs, without and with
 * timestamps, and the stamping of a hundred lines written at once.
 *
 * @BeforeMethods("setUp")
 *
 * @Groups({"console"})
 */
final class ConsoleListenerBench
{
    private ConsoleListener $listener;

    private LineStamper $stamper;

    private string $lines;

    public function __construct()
    {
        $this->setUp();
    }

    public function setUp(): void
    {
        $clock = new SystemClock();
        $this->listener = new ConsoleListener(new ConsoleOptions(), new CommandGuardReader(), new EnvironmentGuard('dev'), new StreamStamps($clock), new PhpMemoryGauge(), $clock, new ConsoleSettings());
        $this->stamper = new LineStamper(TimestampFormat::Iso, $clock, $clock->now(), false);
        $this->lines = str_repeat("A line of output, as a command writes it.\n", 100);
    }

    public function benchGuardedCommand(): void
    {
        $this->run([]);
    }

    public function benchWithTimestamps(): void
    {
        $this->run(['--timestamps' => 'iso']);
    }

    public function benchStampHundredLines(): void
    {
        $this->stamper->stamp($this->lines);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function run(array $parameters): void
    {
        $command = new Command('test:forbidden', new ForbiddenCommand());
        $input = new ArrayInput($parameters);
        $output = new NullOutput();

        $this->listener->onCommand(new ConsoleCommandEvent($command, $input, $output));
        $this->listener->onTerminate(new ConsoleTerminateEvent($command, $input, $output, 0));
    }
}
