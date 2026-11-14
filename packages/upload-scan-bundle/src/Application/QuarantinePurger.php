<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Application;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use Phil\UploadScanBundle\Application\Contract\QuarantineInventory;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * The retention of the quarantine: the files kept longer than a number of days deleted for good with their record,
 * each deletion logged with its identifier. A run reads the inventory a page at a time and deletes at most a bounded
 * number of files, so that a quarantine left to grow is emptied over several runs rather than in one long one. Keeps
 * nothing between two runs.
 */
final readonly class QuarantinePurger
{
    /** The records read at a time. */
    public const int PAGE_SIZE = 100;

    /** The most files a run deletes by default. */
    public const int DEFAULT_MAX = 10_000;

    public function __construct(
        private QuarantineInventory $inventory,
        private ?ClockInterface $clock = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Deletes the files kept for more than `$days` days, or only counts them on a dry run.
     *
     * @param int $days at least 1
     * @param int $max  the most files deleted, or counted, in this run: at least 1
     *
     * @return int<0, max> the files deleted, or that a run would delete
     *
     * @throws InvalidArgumentException when a bound is below 1
     */
    public function purge(int $days, int $max = self::DEFAULT_MAX, bool $dryRun = false): int
    {
        if ($days < 1 || $max < 1) {
            throw new InvalidArgumentException('A purge keeps files at least one day and deletes at least one file.');
        }

        $before = ($this->clock?->now() ?? new DateTimeImmutable())->sub(new DateInterval('P'.$days.'D'));
        $purged = 0;
        $kept = 0;

        while ($purged < $max) {
            $files = $this->inventory->files(self::PAGE_SIZE, $kept);

            if ([] === $files) {
                break;
            }

            foreach ($files as $file) {
                if ($file->quarantinedAt >= $before) {
                    ++$kept;

                    continue;
                }

                if ($dryRun) {
                    // The file stays listed: the next page starts after it.
                    ++$kept;
                } elseif ($this->inventory->delete($file->id)) {
                    $this->logger?->notice('A quarantined file was purged after its retention.', ['id' => $file->id, 'days' => $days]);
                } else {
                    // Deleted meanwhile, or not deletable: never read it again in this run.
                    ++$kept;

                    continue;
                }

                if (++$purged >= $max) {
                    break;
                }
            }
        }

        return $purged;
    }
}
