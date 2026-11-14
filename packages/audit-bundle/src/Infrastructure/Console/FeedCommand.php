<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Infrastructure\Console;

use Override;
use Phil\AuditBundle\Application\DashboardFeed;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:audit:feed [segment…] [--max=rows].
 *
 * Sends the facts recorded since the feed's place to the dashboards, at most `--max` rows: run it from a schedule, or
 * let the `AuditRecorded` messages run it after each row.
 */
#[AsCommand(name: 'phil:audit:feed', description: 'Sends the facts of the audit trail recorded since the last run to the dashboards')]
final class FeedCommand extends Command
{
    public function __construct(
        private readonly DashboardFeed $feed,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('segments', InputArgument::IS_ARRAY, 'The segments to send; every one when none is given');
        $this->addOption('max', null, InputOption::VALUE_REQUIRED, 'The most rows this run sends', '100000');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $segments */
        $segments = $input->getArgument('segments');
        $max = $input->getOption('max');

        if (!\is_string($max) || 1 !== preg_match('/^[1-9]\d{0,8}$/D', $max)) {
            $output->writeln('--max is a whole number of rows, from 1 to 999999999.');

            return Command::INVALID;
        }

        $sent = $this->feed->feed([] === $segments ? null : $segments, (int) $max);

        foreach ($sent as $segment => $count) {
            $output->writeln(\sprintf('%s: %d', $segment, $count));
        }

        $output->writeln(\sprintf('%d fact(s) sent.', array_sum($sent)));

        return Command::SUCCESS;
    }
}
