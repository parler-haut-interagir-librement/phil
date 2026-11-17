<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Infrastructure\Console;

use Override;
use Phil\Outbox\Relay;
use Phil\Outbox\RelayReport;
use Phil\OutboxBundle\Infrastructure\Worker\RelayRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Relays the outbox, batch after batch: until stopped by default, sleeping between empty batches, or until the outbox
 * is empty, after some batches or after some time. SIGINT and SIGTERM end the run after the batch under way, so that a
 * process manager or a worker restarts it cleanly.
 */
#[AsCommand(name: 'phil:outbox:relay', description: 'Relays the records of the outbox to their listeners')]
final class RelayCommand extends Command implements SignalableCommandInterface
{
    private bool $stopping = false;

    public function __construct(
        private readonly RelayRunner $runner,
        private readonly int $batchSize = Relay::BATCH,
    ) {
        parent::__construct();
    }

    /**
     * @return list<int>
     */
    #[Override]
    public function getSubscribedSignals(): array
    {
        // SIGINT and SIGTERM, which Console dispatches when PHP has the pcntl extension.
        return \extension_loaded('pcntl') ? [2, 15] : [];
    }

    #[Override]
    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->stopping = true;

        return false;
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'The most records of a batch, from 1 to 1000', (string) $this->batchSize)
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'The most batches; none when 0', '0')
            ->addOption('stop-when-empty', null, InputOption::VALUE_NONE, 'End the run at the first batch that finds nothing')
            ->addOption('time-limit', null, InputOption::VALUE_REQUIRED, 'The seconds after which the run ends; none when 0', '0')
            ->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'The seconds to wait after a batch that found nothing', '1');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->stopping = false;
        $batchSize = $this->number($input, 'batch-size', 1, Relay::MAX_BATCH);
        $limit = $this->number($input, 'limit', 0, \PHP_INT_MAX);
        $timeLimit = $this->number($input, 'time-limit', 0, \PHP_INT_MAX);
        $sleep = $this->number($input, 'sleep', 0, 3600);

        if (\in_array(null, [$batchSize, $limit, $timeLimit, $sleep], true)) {
            $io->error('--batch-size is a number from 1 to 1000, --sleep from 0 to 3600, --limit and --time-limit 0 or more.');

            return Command::INVALID;
        }

        $report = $this->runner->run(
            (int) $batchSize,
            (int) $limit,
            true === $input->getOption('stop-when-empty'),
            (int) $timeLimit,
            $sleep,
            fn (): bool => $this->stopping,
            static function (RelayReport $batch) use ($io): void {
                if ($io->isVerbose() && !$batch->isEmpty()) {
                    $io->writeln(self::summary($batch));
                }
            },
        );

        if (!$report instanceof RelayReport) {
            $io->note('Another relay holds the lock: this one gives way.');

            return Command::SUCCESS;
        }

        $io->success(self::summary($report));

        return Command::SUCCESS;
    }

    /**
     * The number an option holds, or null when it holds none within its bounds.
     */
    private function number(InputInterface $input, string $option, int $minimum, int $maximum): ?float
    {
        $value = $input->getOption($option);

        if (!\is_string($value) || !is_numeric($value) || (float) $value < $minimum || (float) $value > $maximum) {
            return null;
        }

        return (float) $value;
    }

    private static function summary(RelayReport $report): string
    {
        return \sprintf(
            'Claimed %d: %d delivered, %d to try again, %d dead, %d left to their lease, %d taken over.',
            $report->claimed,
            $report->delivered,
            $report->retried,
            $report->dead,
            $report->expired,
            $report->lost,
        );
    }
}
