<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Infrastructure\Console;

use DateInterval;
use Override;
use Phil\BrowserSecurityBundle\Domain\Contract\ReportRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:csp:purge-reports --older-than=30.
 *
 * Removes the violations not reported for some days — the retention of the configuration by default —, for the
 * scheduler to run every night.
 */
#[AsCommand(name: 'phil:csp:purge-reports', description: 'Removes the violations not reported for some days')]
final class PurgeReportsCommand extends Command
{
    public function __construct(
        private readonly ReportRepository $reports,
        private readonly ClockInterface $clock,
        private readonly int $retention,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('older-than', null, InputOption::VALUE_REQUIRED, 'Days without a report after which a violation is removed', (string) $this->retention);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = $input->getOption('older-than');

        if (!\is_string($days) || 1 !== preg_match('/^(?:0|[1-9]\d{0,4})$/D', $days)) {
            $output->writeln('<error>The number of days is a whole number from 0 to 99999.</error>');

            return Command::INVALID;
        }

        $purged = $this->reports->purge($this->clock->now()->sub(new DateInterval('P'.$days.'D')));
        $output->writeln(\sprintf('%d violation(s) removed.', $purged));

        return Command::SUCCESS;
    }
}
