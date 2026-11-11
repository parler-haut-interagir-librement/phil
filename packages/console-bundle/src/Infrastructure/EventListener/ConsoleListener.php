<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Infrastructure\EventListener;

use Override;
use Phil\ConsoleBundle\Application\Contract\DebugSwitch;
use Phil\ConsoleBundle\Application\Contract\MemoryGauge;
use Phil\ConsoleBundle\Application\EnvironmentGuard;
use Phil\ConsoleBundle\Domain\Elapsed;
use Phil\ConsoleBundle\Domain\TimestampFormat;
use Phil\ConsoleBundle\Domain\ValueObject\ConsoleSettings;
use Phil\ConsoleBundle\Domain\ValueObject\RunSummary;
use Phil\ConsoleBundle\Infrastructure\Console\CommandGuardReader;
use Phil\ConsoleBundle\Infrastructure\Console\ConsoleOptions;
use Phil\ConsoleBundle\Infrastructure\Console\Run;
use Phil\ConsoleBundle\Infrastructure\Console\StreamStamps;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\ResetInterface;
use WeakMap;

/**
 * Before a command runs: adds the global options, holds the command to its guards, then attaches the timestamps and
 * the debug logs it asks for. When it ends: writes the summary, then detaches what it attached.
 *
 * What a run needs until its end is kept by its input, and dropped at the end; reset() drops what an interrupted run
 * left, so that nothing outlives a run in a long-running process.
 */
final class ConsoleListener implements ResetInterface
{
    /**
     * @var WeakMap<InputInterface, Run>
     */
    private WeakMap $runs;

    public function __construct(
        private readonly ConsoleOptions $options,
        private readonly CommandGuardReader $reader,
        private readonly EnvironmentGuard $guard,
        private readonly StreamStamps $stamps,
        private readonly MemoryGauge $memory,
        private readonly ClockInterface $clock,
        private readonly ConsoleSettings $settings,
        private readonly ?DebugSwitch $debug = null,
    ) {
        $this->runs = new WeakMap();
    }

    public function onCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();

        if (!$command instanceof Command) {
            return;
        }

        $input = $event->getInput();
        $output = $event->getOutput();
        $owned = $this->options->register($command, $input, $this->debug instanceof DebugSwitch);

        $name = $command->getName() ?? '';
        $this->guard->check($name, $this->reader->guardsOf($command), $input, $output);

        $format = $this->timestamps($input, $owned);
        $channels = $this->channels($input, $owned);
        $start = $this->clock->now();
        $filters = $format instanceof TimestampFormat ? $this->stamps->attach($output, $format, $start) : [];
        $debug = null === $channels ? null : $this->debug;
        $this->runs[$input] = new Run($name, $start, $this->memory->usage(), $filters, $debug);

        if ($debug instanceof DebugSwitch) {
            $this->enable($debug, $channels, $this->errors($output));
        }
    }

    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        $input = $event->getInput();
        $run = $this->runs[$input] ?? null;

        if (null === $run) {
            return;
        }

        unset($this->runs[$input]);
        $output = $event->getOutput();

        if ($this->settings->summary->shows($output->isVerbose())) {
            $summary = new RunSummary(
                $run->command,
                Elapsed::seconds($run->start, $this->clock->now()),
                $this->memory->usage() - $run->memory,
                $this->memory->peak(),
                $event->getExitCode(),
            );
            $this->errors($output)->writeln(\sprintf('<comment>%s</comment>', $summary->text()));
        }

        $this->end($run);
    }

    #[Override]
    public function reset(): void
    {
        foreach ($this->runs as $run) {
            $this->end($run);
        }

        $this->runs = new WeakMap();
    }

    private function end(Run $run): void
    {
        $this->stamps->detach($run->filters);

        $run->debug?->disable();
    }

    /**
     * @param list<string> $owned
     */
    private function timestamps(InputInterface $input, array $owned): ?TimestampFormat
    {
        $value = \in_array(ConsoleOptions::TIMESTAMPS, $owned, true) ? $input->getOption(ConsoleOptions::TIMESTAMPS) : false;

        if (false === $value) {
            return $this->settings->timestamps ? $this->settings->format : null;
        }

        if (null === $value) {
            return $this->settings->format;
        }

        if ('none' === $value) {
            return null;
        }

        return \is_string($value) ? TimestampFormat::tryFrom($value) ?? throw new InvalidOptionException(\sprintf('The "--%s" option accepts "iso", "relative" or "none", not "%s".', ConsoleOptions::TIMESTAMPS, $value)) : null;
    }

    /**
     * @param list<string> $owned
     *
     * @return list<string>|null
     */
    private function channels(InputInterface $input, array $owned): ?array
    {
        if (!$this->debug instanceof DebugSwitch || !\in_array(ConsoleOptions::DEBUG, $owned, true)) {
            return null;
        }

        $value = $input->getOption(ConsoleOptions::DEBUG);

        if (false === $value) {
            return null;
        }

        if (!\is_string($value) || '' === trim($value)) {
            return [] === $this->settings->debugChannels ? $this->debug->channels() : $this->settings->debugChannels;
        }

        return array_values(array_unique(array_filter(array_map(trim(...), explode(',', $value)), static fn (string $channel): bool => '' !== $channel)));
    }

    /**
     * @param list<string> $channels
     */
    private function enable(DebugSwitch $debug, array $channels, OutputInterface $errors): void
    {
        $unknown = array_diff($channels, $debug->channels());

        if ([] !== $unknown) {
            $errors->writeln(\sprintf('<comment>No logger channel named "%s"; the channels are "%s".</comment>', implode('", "', $unknown), implode('", "', $debug->channels())));
        }

        $debug->enable($channels, $errors);
    }

    private function errors(OutputInterface $output): OutputInterface
    {
        return $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
    }
}
