<?php

declare(strict_types=1);

namespace Phil\Backup\Rotation;

use Phil\Backup\Exception\InvalidBackupValue;
use Phil\Backup\Storage\BackupSet;

/**
 * Which backups to keep: the most recent `last` ones, and the most recent backup of each of the latest `daily` days,
 * `weekly` ISO weeks and `monthly` months that have one — the grandfather-father-son scheme. A backup kept by any
 * rule is kept; the others have expired. Days, weeks and months are those of UTC, the time of the file names.
 *
 * The most recent backup is always kept, since every rule keeps at least one. Labelled backups — the copy taken
 * before a restore, an anonymized copy — are never rotated.
 */
final readonly class RotationPolicy
{
    public function __construct(
        public int $last = 0,
        public int $daily = 0,
        public int $weekly = 0,
        public int $monthly = 0,
    ) {
        if (min($last, $daily, $weekly, $monthly) < 0 || 0 === max($last, $daily, $weekly, $monthly)) {
            throw InvalidBackupValue::because('A rotation keeps zero or more backups by each rule, and at least one in all.');
        }
    }

    /**
     * The sets that have expired, oldest first.
     *
     * @param list<BackupSet> $sets
     *
     * @return list<BackupSet>
     */
    public function expired(array $sets): array
    {
        $rotated = array_filter($sets, static fn (BackupSet $set): bool => !$set->isLabelled());
        usort($rotated, static fn (BackupSet $a, BackupSet $b): int => BackupSet::compare($b, $a));

        $kept = [];

        foreach (\array_slice($rotated, 0, $this->last) as $set) {
            $kept[] = $set->stem;
        }

        foreach (['Y-m-d' => $this->daily, 'o-W' => $this->weekly, 'Y-m' => $this->monthly] as $format => $count) {
            $periods = [];

            foreach ($rotated as $set) {
                $period = $set->time->format($format);

                if (\count($periods) < $count && !\in_array($period, $periods, true)) {
                    $periods[] = $period;
                    $kept[] = $set->stem;
                }
            }
        }

        $expired = [];

        foreach (array_reverse($rotated) as $set) {
            if (!\in_array($set->stem, $kept, true)) {
                $expired[] = $set;
            }
        }

        return $expired;
    }
}
