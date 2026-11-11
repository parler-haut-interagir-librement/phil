<?php

declare(strict_types=1);

namespace Phil\Backup\Storage;

use DateTimeImmutable;
use Phil\Backup\Encryption\BackupCipher;

/**
 * One backup: the database file and, when media directories are backed up, the media archive beside it, both named
 * after the same stem — the database, the time in UTC, and a label for a backup kept apart from rotation:
 * `app-20261107-031500.sql.enc` and `app-20261107-031500.media.tar.enc`, `app-20261107-041000-before-restore.sql`.
 *
 * A second backup of the same label taken within the same second is told apart by its copy number, 2 and up, after
 * an underscore: `app-20261107-041000-before-restore_2.sql`. The first one has none, and its copy number is 1.
 */
final readonly class BackupSet
{
    public function __construct(
        public string $stem,
        public DateTimeImmutable $time,
        public string $label,
        public string $database,
        public ?string $media,
        public int $copy = 1,
    ) {
    }

    /**
     * Orders two sets oldest first: by time, then by copy number, then by stem.
     */
    public static function compare(self $a, self $b): int
    {
        return [$a->time->getTimestamp(), $a->copy, $a->stem] <=> [$b->time->getTimestamp(), $b->copy, $b->stem];
    }

    public function isLabelled(): bool
    {
        return '' !== $this->label;
    }

    public static function isEncrypted(string $file): bool
    {
        return str_ends_with($file, '.'.BackupCipher::EXTENSION);
    }

    /**
     * @return list<string>
     */
    public function files(): array
    {
        return null === $this->media ? [$this->database] : [$this->database, $this->media];
    }
}
