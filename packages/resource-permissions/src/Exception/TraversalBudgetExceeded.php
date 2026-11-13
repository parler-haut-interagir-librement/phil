<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Exception;

use RuntimeException;

/**
 * A check or a listing went past one of the limits of its budget: too deep, too many reads of the store, too many
 * subjects behind one relation. The answer is unknown, so the caller refuses access.
 */
final class TraversalBudgetExceeded extends RuntimeException implements ResourcePermissionsException
{
    public static function depth(int $limit): self
    {
        return new self(\sprintf('The traversal went deeper than %d steps.', $limit));
    }

    public static function reads(int $limit): self
    {
        return new self(\sprintf('The traversal read the store more than %d times.', $limit));
    }

    public static function fanOut(string $relation, int $limit): self
    {
        return new self(\sprintf('The relation "%s" holds more than %d subject sets or related objects to follow.', $relation, $limit));
    }
}
