<?php

declare(strict_types=1);

namespace Phil\Backup\Tests\Unit\Fixture;

use Override;
use Phil\Backup\Database\CommandRunner;
use Phil\Backup\Exception\BackupFailed;

/**
 * A runner whose every tool fails, after noting the option file it was given.
 */
final class FailingRunner implements CommandRunner
{
    public ?string $optionFile = null;

    #[Override]
    public function find(array $candidates): string
    {
        return $candidates[0];
    }

    #[Override]
    public function run(array $command, array $environment = [], ?string $input = null): string
    {
        $this->optionFile = substr($command[1] ?? '', 16);

        throw BackupFailed::failed($command[0], 'Access denied');
    }
}
