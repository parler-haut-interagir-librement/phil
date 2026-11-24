<?php

declare(strict_types=1);

namespace Phil\BackupBundle\Infrastructure\Console;

use Override;
use Phil\Backup\Backups;
use Phil\Backup\Database\DatabaseIdentity;
use Phil\Backup\Exception\BackupError;
use Phil\Backup\Storage\BackupSet;
use Phil\BackupBundle\Infrastructure\Lock\BackupLock;
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
 * `phil_backup.verify.database` for a server. With a lock, the test holds it, so that no rotation removes the set
 * under test.
 */
#[AsCommand(name: 'phil:backup:verify', description: 'Tests that a backup restores, without touching the database')]
final class VerifyBackupCommand extends Command
{
    public function __construct(
        private readonly Backups $backups,
        private readonly ?string $scratch = null,
        private readonly ?BackupLock $lock = null,
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

        $work = function () use ($file): ?array {
            $set = $this->backups->find(\is_string($file) ? $file : null);

            return $set instanceof BackupSet ? [$set, $this->backups->verify($set, $this->scratch)] : null;
        };

        try {
            $tested = $this->lock instanceof BackupLock ? $this->lock->exclusively($work) : $work();
        } catch (BackupError $backupError) {
            $output->writeln($backupError->getMessage());

            return Command::FAILURE;
        }

        if (null === $tested) {
            $output->writeln('There is no backup to test in the backup directory.');

            return Command::INVALID;
        }

        [$set, $verification] = $tested;

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
