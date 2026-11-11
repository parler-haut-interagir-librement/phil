<?php

declare(strict_types=1);

namespace Phil\Backup\Database;

use Phil\Backup\Exception\BackupFailed;

/**
 * Finds and runs the database client tools. An application gives one that runs processes; a test gives a recording
 * one to see the command lines without a database server.
 */
interface CommandRunner
{
    /**
     * The path of the first of these tools that is installed.
     *
     * @param non-empty-list<string> $candidates
     *
     * @throws BackupFailed when none is
     */
    public function find(array $candidates): string;

    /**
     * Runs the command, with these environment variables and, when given, the file as its standard input, and gives
     * back its standard output.
     *
     * @param non-empty-list<string> $command
     * @param array<string, string>  $environment
     *
     * @throws BackupFailed when it exits with an error
     */
    public function run(array $command, array $environment = [], ?string $input = null): string;
}
