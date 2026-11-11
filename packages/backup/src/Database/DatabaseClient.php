<?php

declare(strict_types=1);

namespace Phil\Backup\Database;

use Phil\Backup\Exception\BackupFailed;

/**
 * Backs up and restores one database with its platform's own client tools — `sqlite3`, `mysqldump` and `mysql`,
 * `mariadb-dump` and `mariadb`, `pg_dump` and `pg_restore`.
 */
interface DatabaseClient
{
    public function identity(): DatabaseIdentity;

    /**
     * The extension of a backup file, without its dot: `sqlite`, `sql`, `dump`.
     */
    public function extension(): string;

    /**
     * Writes a complete backup of the database to the file.
     *
     * @throws BackupFailed when the tool is missing or fails
     */
    public function backup(string $file): void;

    /**
     * Replaces the database's content with the backup in the file.
     *
     * @throws BackupFailed when the backup is missing, or the tool is missing or fails
     */
    public function restore(string $file): void;

    /**
     * The same client for another database of the same server, or another file for SQLite: where a restore test
     * restores a backup.
     */
    public function withDatabase(string $database): self;

    /**
     * Checks the database after a restore beyond the tool's own success, where the platform offers a check.
     *
     * @throws BackupFailed when the database fails it
     */
    public function check(): void;
}
