<?php

declare(strict_types=1);

namespace Phil\Backup\Exception;

use Throwable;

/**
 * Every exception of the library: catch it to report a backup, a restore or a restore test that did not happen.
 */
interface BackupError extends Throwable
{
}
