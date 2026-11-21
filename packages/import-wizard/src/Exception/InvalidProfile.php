<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Exception;

use InvalidArgumentException;

/**
 * A profile, a column, a rule, a dialect or a limit that cannot be used: a name out of its pattern, a natural key on an
 * optional column, or serialised data of the wrong shape.
 */
final class InvalidProfile extends InvalidArgumentException implements ImportWizardException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
