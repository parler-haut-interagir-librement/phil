<?php

declare(strict_types=1);

namespace Phil\InstantMessenger\Exception;

use InvalidArgumentException;

/**
 * A text that is not a handle on the messaging service it is given for.
 */
final class InvalidHandle extends InvalidArgumentException implements InstantMessengerException
{
    public static function notOfType(string $input, string $type): self
    {
        return new self(\sprintf('"%s" is not a %s handle.', mb_substr($input, 0, 100), $type));
    }

    public static function noType(string $input): self
    {
        return new self(\sprintf('"%s" is a handle on none of the accepted services.', mb_substr($input, 0, 100)));
    }
}
