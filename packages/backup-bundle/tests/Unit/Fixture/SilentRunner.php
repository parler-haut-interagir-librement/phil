<?php

declare(strict_types=1);

namespace Phil\BackupBundle\Tests\Unit\Fixture;

use Override;
use Phil\Backup\Database\CommandRunner;

/**
 * A runner that finds every tool and runs none, for a server database the tests do not have; asked for its help, a
 * tool answers as a MySQL client able to turn its shell commands off.
 */
final class SilentRunner implements CommandRunner
{
    /** @var list<list<string>> */
    public array $commands = [];

    #[Override]
    public function find(array $candidates): string
    {
        return '/usr/bin/'.$candidates[0];
    }

    #[Override]
    public function run(array $command, array $environment = [], ?string $input = null): string
    {
        if (['--help'] === \array_slice($command, 1)) {
            return "  --system-command    Enable (by default) or disable the system mysql command.\n";
        }

        $this->commands[] = $command;

        return '';
    }
}
