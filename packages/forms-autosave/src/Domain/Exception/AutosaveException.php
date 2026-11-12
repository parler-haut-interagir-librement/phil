<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Domain\Exception;

use Throwable;

/**
 * Everything the bundle throws, catchable with a single `catch`.
 */
interface AutosaveException extends Throwable
{
}
