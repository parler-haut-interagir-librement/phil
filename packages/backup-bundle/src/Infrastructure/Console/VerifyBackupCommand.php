<?php

declare(strict_types=1);

namespace Phil\BackupBundle\Infrastructure\Console;

use Override;
use Phil\Backup\Backups;
use Phil\Backup\Database\DatabaseIdentity;
use Phil\Backup\Exception\BackupError;
use Phil\Backup\Storage\BackupSet;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:backup:verify [var/backup/app-20261107-031500.sql.enc].
 *
 * Tests that a backup restores — the file given, or the most recent set of the backup directory without a label —
 * without touching the database: its files decrypt and authenticate, its media archive reads whole, and its database
 * is restored into a copy and checked there — a temporary file for SQLite, the scratch database of
 * `phil_backup.verify.database` for a server.
 */
#[AsCommand(name: 'phil:backup:verify', description: 'Tests that a backup restores, without touching the database')]
final class VerifyBackupCommand extends Command
{
    public function __construct(
        private readonly Backups $backups,
        private readonly ?string $scratch = null,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::OPTIONAL, 'The database file of the backup; by default the most recent of the backup directory');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('file');

        try {
            $set = $this->backups->find(\is_string($file) ? $file : null);

            if (!$set instanceof BackupSet) {
                $output->writeln('There is no backup to test in the backup directory.');

                return Command::INVALID;
            }

            $verification = $this->backups->verify($set, $this->scratch);
        } catch (BackupError $backupError) {
            $output->writeln($backupError->getMessage());

            return Command::FAILURE;
        }

        if ([] !== array_filter($set->files(), BackupSet::isEncrypted(...))) {
            $output->writeln(\sprintf('The files of %s decrypt and authenticate.', $set->stem));
        }

        $output->writeln($verification->restoredInto instanceof DatabaseIdentity
            ? \sprintf('The database of %s restores: it was restored into the %s and checked.', $set->stem, $verification->restoredInto->describe())
            : \sprintf('The database of %s was not restored: give phil_backup.verify.database a scratch database to restore it into.', $set->stem));

        if (null !== $verification->mediaFiles) {
            $output->writeln(\sprintf('The media archive of %s reads whole: %d file(s).', $set->stem, $verification->mediaFiles));
        }

        return Command::SUCCESS;
    }
}
