<?php

declare(strict_types=1);

namespace Phil\Backup\Database;

/**
 * Which database a client speaks to — its platform, host and name — for file names, messages and the restore guard.
 * A SQLite database has no host, and its name is its file.
 */
final readonly class DatabaseIdentity
{
    public function __construct(
        public string $platform,
        public ?string $host,
        public string $name,
    ) {
    }

    public function describe(): string
    {
        return null === $this->host ? \sprintf('%s database %s', $this->platform, $this->name) : \sprintf('%s database %s on %s', $this->platform, $this->name, $this->host);
    }

    /**
     * Whether the database is a file, which a restore test copies to a file of its own.
     */
    public function isFile(): bool
    {
        return 'sqlite' === $this->platform;
    }

    /**
     * The name a person types again to confirm a restore: the database's name, or the file name of a SQLite database
     * without its directory.
     */
    public function typedName(): string
    {
        return $this->isFile() ? basename($this->name) : $this->name;
    }
}
