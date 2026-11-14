<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Infrastructure\Console;

use Override;
use Phil\FormsBundle\Application\Retention;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:forms:purge --batch=500.
 *
 * Run every night by cron or a schedule: the entries whose retention is over are deleted, a batch at a time until
 * none is left.
 */
#[AsCommand(name: 'phil:forms:purge', description: 'Deletes the form entries kept past their retention')]
final class PurgeEntriesCommand extends Command
{
    public function __construct(
        private readonly Retention $retention,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('batch', null, InputOption::VALUE_REQUIRED, 'How many entries to delete at once', '500');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $batch = $input->getOption('batch');

        if (!\is_string($batch) || 1 !== preg_match('/^[1-9]\d{0,5}$/D', $batch)) {
            $output->writeln('<error>The batch is a whole number from 1 to 999999.</error>');

            return Command::INVALID;
        }

        $total = 0;

        do {
            $deleted = $this->retention->purge((int) $batch);
            $total += $deleted;
        } while ($deleted === (int) $batch);

        $output->writeln(\sprintf('%d entry(ies) deleted.', $total));

        return Command::SUCCESS;
    }
}
