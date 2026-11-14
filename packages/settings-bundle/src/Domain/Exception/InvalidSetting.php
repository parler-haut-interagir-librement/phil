<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A declaration the bundle cannot use — a malformed key, a default its own rules refuse, a section that does not
 * exist — or a setting read as a type it is not. A mistake of the code, found when the container is built.
 */
final class InvalidSetting extends InvalidArgumentException implements SettingsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
