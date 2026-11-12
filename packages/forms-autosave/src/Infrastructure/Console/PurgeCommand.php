<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Infrastructure\Console;

use Override;
use Phil\FormsAutosave\Application\Drafts;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:forms-autosave:purge.
 *
 * Run every night by cron or a schedule: the drafts of the server whose time has passed are deleted.
 */
#[AsCommand(name: 'phil:forms-autosave:purge', description: 'Deletes the expired drafts kept on the server')]
final class PurgeCommand extends Command
{
    public function __construct(
        private readonly Drafts $drafts,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(\sprintf('%d expired draft(s) deleted.', $this->drafts->purge()));

        return Command::SUCCESS;
    }
}
