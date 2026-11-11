<?php

declare(strict_types=1);

namespace Phil\Backup\Tests\Unit\Fixture;

use Override;
use Phil\Backup\Database\CommandRunner;
use Phil\Backup\Exception\BackupFailed;

/**
 * Records the command lines instead of running them, with the content of an option file the command names while it
 * still exists; finds only the tools it is told are installed, and answers with the output it is given. A tool asked
 * for its `--help` answers with the help given, by default that of a MySQL client able to turn its shell commands
 * off, and the question is recorded apart.
 */
final class RecordingRunner implements CommandRunner
{
    /** @var list<array{command: list<string>, environment: array<string, string>, input: ?string, options: ?string}> */
    public array $runs = [];

    /** @var list<string> */
    public array $helps = [];

    /**
     * @param list<string> $installed
     */
    public function __construct(
        private readonly array $installed = ['sqlite3', 'mysqldump', 'mysql', 'mariadb-dump', 'mariadb', 'pg_dump', 'pg_restore'],
        private readonly string $output = '',
        private readonly string $help = "  --system-command    Enable (by default) or disable the system mysql command.\n",
    ) {
    }

    #[Override]
    public function find(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if (\in_array($candidate, $this->installed, true)) {
                return '/usr/bin/'.$candidate;
            }
        }

        throw BackupFailed::missing($candidates);
    }

    #[Override]
    public function run(array $command, array $environment = [], ?string $input = null): string
    {
        if (['--help'] === \array_slice($command, 1)) {
            $this->helps[] = $command[0];

            return $this->help;
        }

        $options = null;

        foreach ($command as $argument) {
            if (str_starts_with($argument, '--defaults-file=')) {
                $file = substr($argument, 16);
                $options = (string) file_get_contents($file);
                $options .= \sprintf('# mode %o', fileperms($file) & 0o777);
            }
        }

        $this->runs[] = ['command' => $command, 'environment' => $environment, 'input' => $input, 'options' => $options];

        return $this->output;
    }
}
