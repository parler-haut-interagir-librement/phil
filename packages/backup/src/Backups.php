<?php

declare(strict_types=1);

namespace Phil\Backup;

use DateTimeImmutable;
use Phil\Backup\Database\DatabaseClient;
use Phil\Backup\Database\DatabaseIdentity;
use Phil\Backup\Encryption\BackupCipher;
use Phil\Backup\Exception\BackupFailed;
use Phil\Backup\Exception\InvalidBackupValue;
use Phil\Backup\Media\MediaArchive;
use Phil\Backup\Rotation\RotationPolicy;
use Phil\Backup\Storage\BackupDirectory;
use Phil\Backup\Storage\BackupSet;
use Throwable;

/**
 * The library in one object: creates a backup set of the database and its media, encrypted when a key is given,
 * rotates the sets, restores one, and tests that one restores.
 *
 * With a key, a set in clear is refused, whether it is found, restored or tested: anyone able to write to the
 * backup storage could have put it there, and nothing authenticates it. `allowUnencrypted` accepts it, for the sets
 * written before the key was given.
 *
 * A copy in clear exists only while it is encrypted, decrypted or tested, as a temporary file of the backup
 * directory readable by its owner alone, removed whatever happens. Every file of a set is narrowed to its owner: a
 * backup holds every person's data.
 */
final readonly class Backups
{
    public function __construct(
        private DatabaseClient $client,
        private BackupDirectory $directory,
        private ?BackupCipher $cipher = null,
        private MediaArchive $media = new MediaArchive([]),
        private ?RotationPolicy $rotation = null,
        private bool $allowUnencrypted = false,
    ) {
    }

    public function client(): DatabaseClient
    {
        return $this->client;
    }

    public function directory(): BackupDirectory
    {
        return $this->directory;
    }

    /**
     * The set of the database file given, wherever it is, or the most recent set of the directory without a label;
     * null when the directory has none. With a key, a set in clear is refused, and never the most recent one, unless
     * sets in clear are allowed.
     */
    public function find(?string $file): ?BackupSet
    {
        if (null === $file) {
            return $this->directory->latest($this->client->extension(), $this->refusesClear() ? true : null);
        }

        $set = $this->directory->set($file, $this->client->extension());

        foreach ($set->files() as $part) {
            $this->accept($part);
        }

        return $set;
    }

    /**
     * Backs the database up, and the media directories unless told not to, into a new set; a set left half written
     * by a failure is removed.
     */
    public function create(DateTimeImmutable $at, string $label = '', bool $media = true): BackupSet
    {
        $stem = $this->directory->newStem($this->client->identity()->name, $at, $label);
        $suffix = $this->cipher instanceof BackupCipher ? '.'.BackupCipher::EXTENSION : '';
        $archive = $media && !$this->media->isEmpty() ? $this->directory->file($stem.'.'.BackupDirectory::MEDIA.$suffix) : null;
        $set = new BackupSet($stem, $at, $label, $this->directory->file($stem.'.'.$this->client->extension().$suffix), $archive);

        try {
            $this->seal($set->database, $this->client->backup(...));

            if (null !== $archive) {
                $this->seal($archive, $this->media->write(...));
            }
        } catch (Throwable $throwable) {
            $this->directory->remove($set);

            throw $throwable;
        }

        return $set;
    }

    /**
     * Removes the sets the rotation has expired, and gives them back; none without a rotation.
     *
     * @return list<BackupSet>
     */
    public function prune(): array
    {
        $expired = $this->rotation?->expired($this->directory->sets($this->client->extension())) ?? [];

        foreach ($expired as $set) {
            $this->directory->remove($set);
        }

        return $expired;
    }

    /**
     * Removes the sets of the label but the `keep` most recent ones, and gives them back, oldest first: the copies
     * taken before each restore, which rotation never touches, are bounded this way.
     *
     * @return list<BackupSet>
     */
    public function keepLatest(string $label, int $keep): array
    {
        if ('' === $label || $keep < 1) {
            throw InvalidBackupValue::because('The sets kept by label are those of a label, at least one of them.');
        }

        $expired = \array_slice($this->directory->labelled($this->client->extension(), $label), 0, -$keep);

        foreach ($expired as $set) {
            $this->directory->remove($set);
        }

        return $expired;
    }

    /**
     * Replaces the database's content with the set, then writes its media files, unless told not to; the media
     * archive is read whole before the database is touched. With `prune`, the files of the media directories that
     * the archive does not hold are then removed, so that each directory is left as it was backed up.
     *
     * Whether the restore may happen is the caller's decision, through `RestoreGuard`.
     */
    public function restore(BackupSet $set, bool $media = true, bool $prune = false): Restoration
    {
        if (!$media || null === $set->media) {
            $this->open($set->database, $this->client->restore(...));

            return new Restoration(0, 0);
        }

        return $this->open($set->media, function (string $archive) use ($set, $prune): Restoration {
            $this->media->verify($archive);
            $this->open($set->database, $this->client->restore(...));

            return new Restoration($this->media->extract($archive), $prune ? $this->media->prune($archive) : 0);
        });
    }

    /**
     * Tests the set: decrypts every file, which authenticates it, reads the media archive whole, and restores the
     * database into a copy — a temporary file for SQLite, else the scratch database given — and checks it there.
     * Without a scratch database, a server database is not restored.
     */
    public function verify(BackupSet $set, ?string $scratch = null): Verification
    {
        $identity = $this->client->identity();

        $restored = $this->open($set->database, function (string $file) use ($identity, $scratch): ?DatabaseIdentity {
            if ($identity->isFile()) {
                $copy = $this->directory->temporary();

                try {
                    return $this->test($this->client->withDatabase($copy), $file);
                } finally {
                    unlink($copy);
                }
            }

            if (null === $scratch) {
                return null;
            }

            if ($scratch === $identity->name) {
                throw BackupFailed::because(\sprintf('The restore test would replace the %s itself: give it a scratch database of its own.', $identity->describe()));
            }

            return $this->test($this->client->withDatabase($scratch), $file);
        });

        return new Verification($restored, null === $set->media ? null : $this->open($set->media, $this->media->verify(...)));
    }

    private function test(DatabaseClient $client, string $file): DatabaseIdentity
    {
        $client->restore($file);
        $client->check();

        return $client->identity();
    }

    /**
     * Writes the file through the writer, encrypting it on the way when there is a key, and narrows it to its owner.
     *
     * @param callable(string): mixed $writer
     */
    private function seal(string $file, callable $writer): void
    {
        if (!$this->cipher instanceof BackupCipher) {
            $writer($file);
            chmod($file, 0o600);

            return;
        }

        $clear = $this->directory->temporary();

        try {
            $writer($clear);
            $this->cipher->encrypt($clear, $file);
        } finally {
            unlink($clear);
        }
    }

    /**
     * Gives the file to the reader in clear: as it is, or decrypted into a temporary file.
     *
     * @template T
     *
     * @param callable(string): T $reader
     *
     * @return T
     */
    private function open(string $file, callable $reader): mixed
    {
        $this->accept($file);

        if (!BackupSet::isEncrypted($file)) {
            return $reader($file);
        }

        if (!$this->cipher instanceof BackupCipher) {
            throw BackupFailed::because(\sprintf('The backup %s is encrypted, and no backup key is given.', $file));
        }

        $clear = $this->directory->temporary();

        try {
            $this->cipher->decrypt($file, $clear);

            return $reader($clear);
        } finally {
            unlink($clear);
        }
    }

    /**
     * Whether a file in clear is refused: with a key, unless sets in clear are allowed.
     */
    private function refusesClear(): bool
    {
        return $this->cipher instanceof BackupCipher && !$this->allowUnencrypted;
    }

    private function accept(string $file): void
    {
        if ($this->refusesClear() && !BackupSet::isEncrypted($file)) {
            throw BackupFailed::because(\sprintf('The backup %s is in clear, and a backup key is given: sets in clear are refused unless they are allowed.', $file));
        }
    }
}
