<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Infrastructure\Console;

use Phil\UploadScanBundle\Application\QuarantinePurger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * `phil:upload-scan:purge-quarantine`: the files of the quarantine kept longer than the retention deleted for good, for
 * a cron job or a schedule. `--days` replaces the configured retention for one run, `--max` bounds the deletions,
 * `--dry-run` only counts.
 */
#[AsCommand(name: self::NAME, description: 'Deletes the quarantined files kept longer than the retention.')]
final readonly class PurgeQuarantineCommand
{
    public const string NAME = 'phil:upload-scan:purge-quarantine';

    /**
     * @param int<1, max> $retentionDays
     */
    public function __construct(
        private QuarantinePurger $purger,
        private int $retentionDays,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Delete the files kept for more than this many days, instead of the configured retention.')]
        ?int $days = null,
        #[Option(description: 'The most files deleted in this run.')]
        int $max = QuarantinePurger::DEFAULT_MAX,
        #[Option(description: 'Count the files a purge would delete, and delete none.')]
        bool $dryRun = false,
    ): int {
        $days ??= $this->retentionDays;

        if ($days < 1 || $max < 1) {
            $io->error('--days and --max are at least 1.');

            return Command::INVALID;
        }

        $purged = $this->purger->purge($days, $max, $dryRun);

        $io->success(\sprintf($dryRun ? '%d quarantined file(s) kept for more than %d day(s) would be deleted.' : '%d quarantined file(s) kept for more than %d day(s) deleted.', $purged, $days));

        return Command::SUCCESS;
    }
}
