<?php

declare(strict_types=1);

namespace Phil\Ujo\Exception;

use LogicException;

/**
 * A mistake in how the library is put together, not in a document: an unknown inline profile, for instance.
 */
final class InvalidConfiguration extends LogicException implements UjoException
{
    public static function unknownProfile(string $profile): self
    {
        return new self(\sprintf('The inline profile "%s" is not registered.', $profile));
    }
}
