<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Exception;

use InvalidArgumentException;

/**
 * A kind of object, or one of its relations, that the model does not declare.
 */
final class UnknownRelation extends InvalidArgumentException implements ResourcePermissionsException
{
    public static function kind(string $kind): self
    {
        return new self(\sprintf('The model declares no kind "%s".', $kind));
    }

    public static function relation(string $kind, string $relation): self
    {
        return new self(\sprintf('The kind "%s" declares no relation "%s".', $kind, $relation));
    }
}
