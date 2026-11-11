<?php

declare(strict_types=1);

namespace Phil\Backup\Database;

use Override;

/**
 * PostgreSQL through `pg_dump` and `pg_restore`, in the custom format, which `pg_restore` reads selectively and
 * compressed.
 *
 * **The connection travels in the environment** (`PGHOST`, `PGPORT`, `PGUSER`, `PGPASSWORD`) given to the tool
 * alone, never on its command line. A restore drops each object before recreating it (`--clean --if-exists`), leaves
 * ownership to the connecting role (`--no-owner`) and runs in one transaction, so a backup from one server restores
 * on another, or not at all.
 */
final readonly class PostgresqlClient implements DatabaseClient
{
    /**
     * @param array<string, string> $connection host, port, user and password
     */
    public function __construct(
        private string $database,
        private array $connection,
        private CommandRunner $runner,
    ) {
    }

    #[Override]
    public function identity(): DatabaseIdentity
    {
        return new DatabaseIdentity('postgresql', $this->connection['host'] ?? null, $this->database);
    }

    #[Override]
    public function extension(): string
    {
        return 'dump';
    }

    #[Override]
    public function backup(string $file): void
    {
        $this->runner->run([$this->runner->find(['pg_dump']), '--format=custom', '--file='.$file, '--dbname='.$this->database], $this->environment());
    }

    #[Override]
    public function restore(string $file): void
    {
        $this->runner->run([$this->runner->find(['pg_restore']), '--clean', '--if-exists', '--no-owner', '--single-transaction', '--dbname='.$this->database, $file], $this->environment());
    }

    #[Override]
    public function withDatabase(string $database): self
    {
        return new self($database, $this->connection, $this->runner);
    }

    /**
     * The success of `pg_restore` in one transaction is the check: a backup that does not restore whole leaves the
     * database as it was and fails.
     */
    #[Override]
    public function check(): void
    {
    }

    /**
     * @return array<string, string>
     */
    private function environment(): array
    {
        $environment = [];

        foreach (['host' => 'PGHOST', 'port' => 'PGPORT', 'user' => 'PGUSER', 'password' => 'PGPASSWORD'] as $parameter => $variable) {
            if (isset($this->connection[$parameter])) {
                $environment[$variable] = $this->connection[$parameter];
            }
        }

        return $environment;
    }
}
