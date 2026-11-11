<?php

declare(strict_types=1);

namespace Phil\BackupBundle\Infrastructure\Console;

use DateTimeImmutable;
use Override;
use Phil\Backup\Backups;
use Phil\Backup\Exception\BackupError;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:backup:create [--label=before-migration] [--no-media] [--no-rotate].
 *
 * Backs the database and the media directories up into a new set of the backup directory, encrypted when a key is
 * configured, then removes the sets the rotation has expired. A labelled set is kept apart from rotation.
 */
#[AsCommand(name: 'phil:backup:create', description: 'Backs the database and the media files up')]
final class CreateBackupCommand extends Command
{
    public function __construct(
        private readonly Backups $backups,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('label', null, InputOption::VALUE_REQUIRED, 'A label, in lowercase letters, digits and "-", that keeps the backup apart from rotation')
            ->addOption('no-media', null, InputOption::VALUE_NONE, 'Back the database up alone')
            ->addOption('no-rotate', null, InputOption::VALUE_NONE, 'Keep every earlier backup this time');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $label = $input->getOption('label');

        try {
            $set = $this->backups->create(new DateTimeImmutable(), \is_string($label) ? $label : '', true !== $input->getOption('no-media'));
            $output->writeln(\sprintf('The %s is backed up in %s.', $this->backups->client()->identity()->describe(), $set->database));

            if (null !== $set->media) {
                $output->writeln(\sprintf('The media files are archived in %s.', $set->media));
            }

            foreach (true === $input->getOption('no-rotate') ? [] : $this->backups->prune() as $expired) {
                $output->writeln(\sprintf('The expired backup %s is removed.', $expired->stem));
            }
        } catch (BackupError $backupError) {
            $output->writeln($backupError->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
