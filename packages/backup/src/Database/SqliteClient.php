<?php

declare(strict_types=1);

namespace Phil\Backup\Database;

use Override;
use Phil\Backup\Exception\BackupFailed;

/**
 * SQLite through `sqlite3`: `.backup` writes a consistent copy of the file even while the application uses it,
 * `.restore` copies a backup back over the database, and `PRAGMA integrity_check` checks a restored copy.
 */
final readonly class SqliteClient implements DatabaseClient
{
    public function __construct(
        private string $path,
        private CommandRunner $runner,
    ) {
    }

    #[Override]
    public function identity(): DatabaseIdentity
    {
        return new DatabaseIdentity('sqlite', null, $this->path);
    }

    #[Override]
    public function extension(): string
    {
        return 'sqlite';
    }

    #[Override]
    public function backup(string $file): void
    {
        $this->runner->run([$this->runner->find(['sqlite3']), $this->path, '.backup '.$this->quoted($file)]);
    }

    #[Override]
    public function restore(string $file): void
    {
        $command = '.restore '.$this->quoted($file);

        // `.restore` creates a missing source file, empty, and restores it: the database would be emptied.
        if (!is_file($file)) {
            throw BackupFailed::because(\sprintf('The backup %s does not exist.', $file));
        }

        $this->runner->run([$this->runner->find(['sqlite3']), $this->path, $command]);
    }

    #[Override]
    public function withDatabase(string $database): self
    {
        return new self($database, $this->runner);
    }

    #[Override]
    public function check(): void
    {
        $result = trim($this->runner->run([$this->runner->find(['sqlite3']), $this->path, 'PRAGMA integrity_check;']));

        if ('ok' !== $result) {
            throw BackupFailed::because(\sprintf('The restored SQLite database fails its integrity check: %s', '' === $result ? 'no answer.' : $result));
        }
    }

    /**
     * A file name as the argument of a dot command, which `sqlite3` reads literally between single quotes. A name
     * holding a quote or a control character, which would end the argument or the command, is refused.
     */
    private function quoted(string $file): string
    {
        if (1 === preg_match("/['\\x00-\\x1F\\x7F]/", $file)) {
            throw BackupFailed::because('sqlite3 cannot be given a file name that holds a quote or a control character.');
        }

        return "'".$file."'";
    }
}
