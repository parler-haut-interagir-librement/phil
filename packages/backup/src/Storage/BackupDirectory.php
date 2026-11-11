<?php

declare(strict_types=1);

namespace Phil\Backup\Storage;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Backup\Encryption\BackupCipher;
use Phil\Backup\Exception\BackupFailed;
use Phil\Backup\Exception\InvalidBackupValue;

/**
 * Where backups are written and found: one directory, readable by its owner alone, holding sets named after the
 * database, the time in UTC and an optional label, such as `app-20261107-031500.sql` and
 * `app-20261107-031500.media.tar`. A stem already taken in the directory gets a copy number, `_2` and up, so that two
 * backups of the same label within the same second never overwrite each other.
 */
final readonly class BackupDirectory
{
    public const string MEDIA = 'media.tar';

    /**
     * How many copies of one stem a directory may hold: the bound of the search for a free one.
     */
    public const int COPIES = 100;

    private const string NAME = '/^(?<stem>.+-(?<time>\d{8}-\d{6})(?:-(?<label>[a-z0-9][a-z0-9-]*))?(?:_(?<copy>[2-9]|[1-9]\d+))?)\.%s(?:\.%s)?$/D';

    public function __construct(
        private string $path,
    ) {
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * The stem of a new set for the database taken at the time, the directory created if need be: with a copy number
     * when a set of the directory already has the stem.
     */
    public function newStem(string $database, DateTimeImmutable $at, string $label = ''): string
    {
        if ('' !== $label && 1 !== preg_match('/^[a-z0-9][a-z0-9-]*$/D', $label)) {
            throw InvalidBackupValue::because(\sprintf('The label "%s" holds characters other than lowercase letters, digits and "-", or starts with "-".', $label));
        }

        $this->create();
        $name = preg_replace('/[^A-Za-z0-9_-]+/', '-', pathinfo($database, \PATHINFO_FILENAME)) ?? 'database';

        $stem = \sprintf('%s-%s%s', $name, $at->setTimezone(new DateTimeZone('UTC'))->format('Ymd-His'), '' === $label ? '' : '-'.$label);

        for ($copy = 1; $copy <= self::COPIES; ++$copy) {
            $candidate = 1 === $copy ? $stem : $stem.'_'.$copy;

            if ([] === $this->glob($candidate.'.*')) {
                return $candidate;
            }
        }

        throw BackupFailed::because(\sprintf('The backup directory %s already holds %d backups named %s.', $this->path, self::COPIES, $stem));
    }

    /**
     * The path of a file of the directory.
     */
    public function file(string $name): string
    {
        return $this->path.'/'.$name;
    }

    /**
     * A new empty file of the directory, readable by its owner alone, for a copy in clear that lives while a backup
     * is encrypted, decrypted or tested; the caller removes it.
     */
    public function temporary(): string
    {
        $this->create();
        $file = @tempnam($this->path, '.phil-backup-');

        if (false === $file || \dirname($file) !== $this->path) {
            throw BackupFailed::because(\sprintf('No temporary file can be created in the backup directory %s.', $this->path));
        }

        return $file;
    }

    /**
     * The sets of the directory whose database files have the extension, oldest first: the encrypted ones alone when
     * `encrypted` is true, those in clear alone when it is false, both when it is null.
     *
     * @return list<BackupSet>
     */
    public function sets(string $extension, ?bool $encrypted = null): array
    {
        $sets = [];
        $files = [
            ...(true === $encrypted ? [] : $this->glob('*.'.$extension)),
            ...(false === $encrypted ? [] : $this->glob('*.'.$extension.'.'.BackupCipher::EXTENSION)),
        ];

        foreach ($files as $file) {
            $set = $this->parse($file, $extension);

            if ($set instanceof BackupSet) {
                $sets[] = $set;
            }
        }

        usort($sets, BackupSet::compare(...));

        return $sets;
    }

    /**
     * The sets of the label given, oldest first, of the encryption given as `sets()` takes it.
     *
     * @return list<BackupSet>
     */
    public function labelled(string $extension, string $label, ?bool $encrypted = null): array
    {
        return array_values(array_filter($this->sets($extension, $encrypted), static fn (BackupSet $set): bool => $label === $set->label));
    }

    /**
     * The most recent set without a label, of the encryption given as `sets()` takes it, or null when there is none.
     */
    public function latest(string $extension, ?bool $encrypted = null): ?BackupSet
    {
        $sets = $this->labelled($extension, '', $encrypted);

        return [] === $sets ? null : end($sets);
    }

    /**
     * The set of a database file given by its path, anywhere: named as the directory names its files, or not, in
     * which case its time is its modification time and it has no media archive.
     */
    public function set(string $file, string $extension): BackupSet
    {
        if (!is_file($file)) {
            throw BackupFailed::because(\sprintf('The backup %s does not exist.', $file));
        }

        return $this->parse($file, $extension) ?? new BackupSet(basename($file), new DateTimeImmutable('@'.filemtime($file)), '', $file, null);
    }

    /**
     * Removes the files of the set.
     */
    public function remove(BackupSet $set): void
    {
        foreach ($set->files() as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function parse(string $file, string $extension): ?BackupSet
    {
        if (1 !== preg_match(\sprintf(self::NAME, preg_quote($extension, '/'), BackupCipher::EXTENSION), basename($file), $name)) {
            return null;
        }

        $time = DateTimeImmutable::createFromFormat('!Ymd-His', $name['time'], new DateTimeZone('UTC'));

        if (false === $time || $time->format('Ymd-His') !== $name['time']) {
            return null;
        }

        // The media archive has the encryption of the database file: an encrypted dump is never paired with an
        // archive in clear that anyone writing to the directory could have put beside it, nor the other way round.
        $media = \dirname($file).'/'.$name['stem'].'.'.self::MEDIA.(BackupSet::isEncrypted($file) ? '.'.BackupCipher::EXTENSION : '');
        $media = is_file($media) ? $media : null;

        return new BackupSet($name['stem'], $time, $name['label'] ?? '', $file, $media, (int) ($name['copy'] ?? 1));
    }

    /**
     * @return list<string>
     */
    private function glob(string $pattern): array
    {
        $files = glob($this->path.'/'.$pattern);

        return false === $files ? [] : $files;
    }

    private function create(): void
    {
        if (!is_dir($this->path) && !@mkdir($this->path, 0o700, true)) {
            throw BackupFailed::because(\sprintf('The backup directory %s cannot be created.', $this->path));
        }
    }
}
