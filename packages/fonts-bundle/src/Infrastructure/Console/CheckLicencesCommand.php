<?php

declare(strict_types=1);

namespace Phil\FontsBundle\Infrastructure\Console;

use Override;
use Phil\Fonts\Licence\LicenceRecord;
use Phil\FontsBundle\Application\Licences;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:fonts:licences [--write].
 *
 * Compares the font files with the licence register: each file added, removed or changed since the register was
 * written is listed, and the command fails, so that a build stops on a font whose licence nobody checked. Once they are
 * checked, `--write` records the files as they are.
 */
#[AsCommand(name: 'phil:fonts:licences', description: 'Lists the font files whose licence must be checked, and records them once checked')]
final class CheckLicencesCommand extends Command
{
    public function __construct(
        private readonly Licences $licences,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('write', null, InputOption::VALUE_NONE, 'Record the font files as they are, once their licences are checked');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $kept = $this->licences->kept();
        $current = $this->licences->current();

        if (true === $input->getOption('write')) {
            $this->licences->write();
            $output->writeln(\sprintf('The licence register "%s" records %d font file(s).', $this->licences->path(), \count($current->records())));

            return Command::SUCCESS;
        }

        $changed = $kept->changedFiles($current);

        if ([] === $changed) {
            $output->writeln('The licence register records every font file as it is.');

            return Command::SUCCESS;
        }

        foreach ($changed as $file) {
            $output->writeln(\sprintf('Check the licence of %s: %s.', $file, match (true) {
                !$kept->get($file) instanceof LicenceRecord => 'added',
                !$current->get($file) instanceof LicenceRecord => 'removed',
                default => 'changed',
            }));
        }

        $output->writeln('Then run phil:fonts:licences --write.');

        return Command::FAILURE;
    }
}
