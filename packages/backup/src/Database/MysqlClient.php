<?php

declare(strict_types=1);

namespace Phil\Backup\Database;

use Override;
use Phil\Backup\Exception\BackupFailed;

/**
 * MySQL and MariaDB through their dump and command line clients — `mariadb-dump` and `mariadb` first for MariaDB,
 * `mysqldump` and `mysql` otherwise or when those are missing.
 *
 * **The password never appears on a command line**, where any user of the machine reads it in the process list:
 * the connection is written to an option file readable by its owner alone, given with `--defaults-file` and removed
 * once the tool has run. That file is the only one read: a `~/.my.cnf` left on the server adds no option.
 *
 * **A dump never runs a shell command.** The `mysql` and `mariadb` clients run `\!` and `system` lines on the machine
 * even in batch mode, so a restore turns them off — `--sandbox` for MariaDB 10.5.25 and later, `--system-command=OFF`
 * for MySQL 8.0.40 and later, read from the client's `--help` — and is refused by a client that can do neither. The dump is one transaction (`--single-transaction`), with routines and triggers,
 * and names no database, so that it restores into whichever database the client is given.
 */
final readonly class MysqlClient implements DatabaseClient
{
    /**
     * @param array<string, string> $connection host, port, user, password and unix_socket
     */
    public function __construct(
        private string $database,
        private array $connection,
        private CommandRunner $runner,
        private bool $mariadb,
    ) {
    }

    #[Override]
    public function identity(): DatabaseIdentity
    {
        return new DatabaseIdentity($this->mariadb ? 'mariadb' : 'mysql', $this->connection['host'] ?? null, $this->database);
    }

    #[Override]
    public function extension(): string
    {
        return 'sql';
    }

    #[Override]
    public function backup(string $file): void
    {
        $tool = $this->runner->find($this->mariadb ? ['mariadb-dump', 'mysqldump'] : ['mysqldump']);
        $this->withOptionFile(fn (string $options): string => $this->runner->run([$tool, '--defaults-file='.$options, '--single-transaction', '--routines', '--triggers', '--result-file='.$file, $this->database]));
    }

    #[Override]
    public function restore(string $file): void
    {
        $tool = $this->runner->find($this->mariadb ? ['mariadb', 'mysql'] : ['mysql']);
        $noShell = $this->noShell($tool);
        $this->withOptionFile(fn (string $options): string => $this->runner->run([$tool, '--defaults-file='.$options, $noShell, $this->database], [], $file));
    }

    #[Override]
    public function withDatabase(string $database): self
    {
        return new self($database, $this->connection, $this->runner, $this->mariadb);
    }

    /**
     * The success of `mysql` reading the whole dump is the check: MySQL offers none that a restore needs beyond it.
     */
    #[Override]
    public function check(): void
    {
    }

    /**
     * The option that turns the client's shell commands off, as its help names it.
     */
    private function noShell(string $tool): string
    {
        $help = $this->runner->run([$tool, '--help']);

        foreach (['--sandbox' => '--sandbox', '--system-command' => '--system-command=OFF'] as $option => $argument) {
            if (1 === preg_match('/^\s*'.$option.'(?=[\s=]|$)/m', $help)) {
                return $argument;
            }
        }

        throw BackupFailed::because(\sprintf('%s cannot turn its shell commands off, which a dump could run on this machine: install MySQL 8.0.40 or later, or MariaDB 10.5.25 or later.', $tool));
    }

    /**
     * @param callable(string): string $run
     */
    private function withOptionFile(callable $run): void
    {
        $file = tempnam(sys_get_temp_dir(), 'phil-backup-');

        if (false === $file) {
            throw BackupFailed::because('No option file could be created in the temporary directory.');
        }

        // `tempnam()` creates the file readable and writable by its owner alone.
        try {
            file_put_contents($file, $this->options());
            $run($file);
        } finally {
            unlink($file);
        }
    }

    private function options(): string
    {
        $lines = ['[client]'];

        foreach (['host' => 'host', 'port' => 'port', 'user' => 'user', 'password' => 'password', 'unix_socket' => 'socket'] as $parameter => $option) {
            if (isset($this->connection[$parameter])) {
                $lines[] = \sprintf('%s="%s"', $option, addcslashes($this->connection[$parameter], '\\"'));
            }
        }

        return implode("\n", $lines)."\n";
    }
}
