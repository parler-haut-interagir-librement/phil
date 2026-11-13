<?php

declare(strict_types=1);

namespace Phil\Automations\Exception;

use OutOfBoundsException;

/**
 * A trigger, an action or a condition language that no registry holds.
 */
final class UnknownComponent extends OutOfBoundsException implements AutomationsException
{
    public static function trigger(string $type): self
    {
        return new self(\sprintf('No trigger of type "%s" is registered.', $type));
    }

    public static function action(string $type): self
    {
        return new self(\sprintf('No action of type "%s" is registered.', $type));
    }

    public static function language(string $language): self
    {
        return new self(\sprintf('No condition evaluator reads the language "%s".', $language));
    }
}
