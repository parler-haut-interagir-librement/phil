<?php

declare(strict_types=1);

namespace Phil\AiBundle\Application\Exception;

use LogicException;
use Phil\AiBundle\Domain\Exception\AiException;

/**
 * A platform the configuration does not define, or one that cannot take the person's own key.
 */
final class UnknownPlatform extends LogicException implements AiException
{
    public static function of(string $platform): self
    {
        return new self(\sprintf('No platform "%s" is defined: add it under phil_ai.platforms.', $platform));
    }

    public static function withoutUserKeys(string $platform): self
    {
        return new self(\sprintf('The platform "%s" takes no key of the person: give it a user_key_factory.', $platform));
    }
}
