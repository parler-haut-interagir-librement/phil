<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Domain\Exception;

use OutOfBoundsException;

/**
 * A setting or a section no package and no configuration declared.
 */
final class UnknownSetting extends OutOfBoundsException implements SettingsException
{
    public static function named(string $key): self
    {
        return new self(\sprintf('No setting is declared under the key "%s".', $key));
    }

    public static function section(string $key): self
    {
        return new self(\sprintf('No section is declared under the key "%s".', $key));
    }
}
