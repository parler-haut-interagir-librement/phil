<?php

declare(strict_types=1);

namespace Phil\PrintPreflight;

/**
 * How much a finding matters, from the one that stops the print to the one that only informs.
 */
enum Severity: string
{
    /** The printer would refuse the file, or print it wrong: the file must be fixed first. */
    case Error = 'error';

    /** The file prints, but probably not as meant: someone should look. */
    case Warning = 'warning';

    /** Worth knowing, nothing to fix: a conversion the printer will make. */
    case Notice = 'notice';
}
