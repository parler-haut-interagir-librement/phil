<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Infrastructure\Console;

use Phil\SettingsBundle\Application\Migration\MigrationReport;
use Phil\SettingsBundle\Application\Migration\SettingMigrator;
use Phil\SettingsBundle\Domain\Exception\InvalidSetting;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * `phil:settings:migrate` applies the migrations of the stored values not yet applied, each once; `--dry-run` says
 * what they would write and remove, and writes nothing. Run it at each deployment, after the database migrations.
 */
#[AsCommand(name: 'phil:settings:migrate', description: 'Applies the migrations of the stored settings not yet applied')]
final readonly class MigrateSettingsCommand
{
    public function __construct(
        private SettingMigrator $migrator,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option(description: 'Say what would change, and write nothing')] bool $dryRun = false): int
    {
        try {
            $reports = $this->migrator->migrate($dryRun);
        } catch (InvalidSetting $invalidSetting) {
            $io->error($invalidSetting->getMessage());

            return Command::FAILURE;
        }

        if ([] === $reports) {
            $io->success('Every migration of the settings is applied.');

            return Command::SUCCESS;
        }

        $io->table(
            ['Version', 'Written', 'Removed'],
            array_map(static fn (MigrationReport $report): array => [$report->version, $report->written, $report->removed], $reports),
        );

        $dryRun
            ? $io->note(\sprintf('%d migration(s) would be applied; nothing is written.', \count($reports)))
            : $io->success(\sprintf('%d migration(s) applied.', \count($reports)));

        return Command::SUCCESS;
    }
}
