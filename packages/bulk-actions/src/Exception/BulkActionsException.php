<?php

declare(strict_types=1);

namespace Phil\BulkActions\Exception;

use Throwable;

/**
 * Every exception of the library. Its message names the field, the target or the action at fault, never the values of
 * an item.
 */
interface BulkActionsException extends Throwable
{
}
