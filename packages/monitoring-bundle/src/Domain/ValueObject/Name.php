<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Domain\ValueObject;

use Phil\MonitoringBundle\Domain\Exception\InvalidValue;

/**
 * The rule every name of the bundle follows — a check, a task: an identifier of up to 100 letters, digits, dots,
 * dashes, underscores and colons, starting with a letter or a digit. A name is shown to monitors and operators, so it
 * never carries anything but an identifier.
 */
final readonly class Name
{
    public const string PATTERN = '[A-Za-z0-9][A-Za-z0-9_.:-]{0,99}';

    private function __construct()
    {
    }

    /**
     * @return non-empty-string
     */
    public static function check(string $name, string $what): string
    {
        if (1 !== preg_match('/^'.self::PATTERN.'$/D', $name)) {
            throw InvalidValue::because(\sprintf('The %s name "%s" is not an identifier of up to 100 letters, digits, dots, dashes, underscores and colons.', $what, mb_substr($name, 0, 120)));
        }

        return $name;
    }
}
