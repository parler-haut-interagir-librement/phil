<?php

declare(strict_types=1);

namespace Phil\BackupBundle\Infrastructure\Console;

use DateTimeImmutable;
use Override;
use Phil\Backup\Backups;
use Phil\Backup\Exception\BackupError;
use Phil\Backup\Restore\RestoreGuard;
use Phil\Backup\Storage\BackupSet;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 *     bin/console phil:backup:restore [var/backup/app-20261107-031500.sql.enc] --confirm [--database=app] [--no-media] [--prune].
 *
 * Replaces the database's content, and writes the media files, from a set — the file given, or the most recent set of
 * the backup directory without a label. The restore guard runs first: in production it needs
 * `PHIL_BACKUP_ALLOW_PRODUCTION_RESTORE` set to the database's name, then `--confirm`, then the database's name typed
 * again, asked for when the console is interactive. The current content is then backed up, labelled
 * `before-restore`, and the restore starts only once that backup is written; once the restore succeeds, the oldest
 * `before-restore` backups are removed but the latest ones, three by default.
 *
 * The media files the backup does not hold are left, unless `--prune` removes them.
 */
#[AsCommand(name: 'phil:backup:restore', description: 'Restores the database and the media files from a backup')]
final class RestoreBackupCommand extends Command
{
    public const string LABEL = 'before-restore';

    public function __construct(
        private readonly Backups $backups,
        private readonly RestoreGuard $guard,
        private readonly int $keepBeforeRestore = 3,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::OPTIONAL, 'The database file of the backup; by default the most recent of the backup directory')
            ->addOption('confirm', null, InputOption::VALUE_NONE, 'Restore: without it, nothing is done')
            ->addOption('database', null, InputOption::VALUE_REQUIRED, 'The name of the database, typed again')
            ->addOption('no-media', null, InputOption::VALUE_NONE, 'Restore the database alone')
            ->addOption('prune', null, InputOption::VALUE_NONE, 'Remove the media files the backup does not hold');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('file');
        $media = true !== $input->getOption('no-media');
        $prune = true === $input->getOption('prune');
        $database = $this->backups->client()->identity();

        try {
            $set = $this->backups->find(\is_string($file) ? $file : null);

            if (!$set instanceof BackupSet) {
                $output->writeln('There is no backup to restore in the backup directory.');

                return Command::INVALID;
            }

            $allowance = $this->variable(RestoreGuard::VARIABLE);
            $confirmed = true === $input->getOption('confirm');
            $typed = $input->getOption('database');
            $this->guard->checkEnvironment($database, $allowance);

            if ($confirmed && null === $typed && $input->isInteractive()) {
                $typed = new SymfonyStyle($input, $output)->ask(\sprintf('The %s will be replaced with %s. Type its name, %s, to go on', $database->describe(), $set->database, $database->typedName()));
            }

            $this->guard->check($database, $allowance, $confirmed, \is_string($typed) ? $typed : null, $set->database);
        } catch (BackupError $backupError) {
            $output->writeln($backupError->getMessage());

            return Command::INVALID;
        }

        try {
            $before = $this->backups->create(new DateTimeImmutable(), self::LABEL, $media);
            $output->writeln(\sprintf('The current content is backed up in %s first.', $before->database));
            $restoration = $this->backups->restore($set, $media, $prune);
            $removed = $this->backups->keepLatest(self::LABEL, $this->keepBeforeRestore);
        } catch (BackupError $backupError) {
            $output->writeln($backupError->getMessage());

            return Command::FAILURE;
        }

        $output->writeln(\sprintf('The %s is restored from %s.', $database->describe(), $set->database));

        if ($media && null !== $set->media) {
            $output->writeln(\sprintf('%d media file(s) are restored from %s.', $restoration->mediaFiles, $set->media));

            if ($prune) {
                $output->writeln(\sprintf('%d media file(s) the backup does not hold are removed.', $restoration->removedFiles));
            }
        }

        foreach ($removed as $old) {
            $output->writeln(\sprintf('The older backup %s is removed.', $old->stem));
        }

        return Command::SUCCESS;
    }

    /**
     * An environment variable as the application sees it: in `$_SERVER`, which holds the process's environment on
     * the command line and where Symfony's Dotenv writes, or in `$_ENV`.
     */
    private function variable(string $name): ?string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? null;

        return \is_string($value) ? $value : null;
    }
}
