<?php

declare(strict_types=1);

namespace Phil\TrashBundle\Infrastructure\Console;

use Override;
use Phil\TrashBundle\Application\Trash;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:trash:purge --limit=500.
 *
 * Run by the scheduler every night: the resources whose retention is over leave the trash for good.
 */
#[AsCommand(name: 'phil:trash:purge', description: 'Purges the trashed resources whose retention is over')]
final class PurgeTrashCommand extends Command
{
    public function __construct(
        private readonly Trash $trash,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'How many resources to purge at most', '500');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = $input->getOption('limit');

        if (!\is_string($limit) || 1 !== preg_match('/^[1-9]\d{0,5}$/D', $limit)) {
            $output->writeln('<error>The limit is a whole number from 1 to 999999.</error>');

            return Command::INVALID;
        }

        $output->writeln(\sprintf('%d resource(s) purged.', $this->trash->purge((int) $limit)));

        return Command::SUCCESS;
    }
}
