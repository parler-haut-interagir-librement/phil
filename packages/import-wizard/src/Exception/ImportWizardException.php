<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Exception;

use Throwable;

/**
 * Every exception of the library: a file, a profile, a mapping or a plan refused. Its message names the column, the
 * line or the limit at fault, never the value of a cell.
 */
interface ImportWizardException extends Throwable
{
}
