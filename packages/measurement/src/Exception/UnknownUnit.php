<?php

declare(strict_types=1);

namespace Phil\Measurement\Exception;

use InvalidArgumentException;

/**
 * A unit code, a UCUM code or a family the registry does not hold.
 */
final class UnknownUnit extends InvalidArgumentException implements MeasurementException
{
    public static function code(string $code): self
    {
        return new self(\sprintf('The unit "%s" is not in the registry.', mb_strcut($code, 0, 64)));
    }

    public static function ucum(string $code): self
    {
        return new self(\sprintf('No unit of the registry has the UCUM code "%s".', mb_strcut($code, 0, 64)));
    }

    public static function ambiguousUcum(string $code): self
    {
        return new self(\sprintf('The UCUM code "%s" names a unit of more than one family: name the family too.', mb_strcut($code, 0, 64)));
    }

    public static function family(string $family): self
    {
        return new self(\sprintf('The family "%s" is not in the registry.', mb_strcut($family, 0, 64)));
    }
}
