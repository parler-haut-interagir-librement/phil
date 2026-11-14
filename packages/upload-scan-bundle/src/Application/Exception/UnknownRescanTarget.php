<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Application\Exception;

use LogicException;

/**
 * A `RescanFile` message that names a target no service declares.
 */
final class UnknownRescanTarget extends LogicException
{
    public static function named(string $name): self
    {
        return new self(\sprintf('No rescan target is named "%s".', $name));
    }
}
