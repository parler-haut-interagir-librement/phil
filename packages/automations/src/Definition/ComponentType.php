<?php

declare(strict_types=1);

namespace Phil\Automations\Definition;

use Phil\Automations\Exception\InvalidDefinition;

/**
 * The rule of a trigger's or an action's type: a dotted lowercase name, `newsletter.subscribe`, `webhook`.
 */
final class ComponentType
{
    public const string PATTERN = '/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$/D';

    public const int MAX_LENGTH = 128;

    /**
     * @throws InvalidDefinition when the type is not a dotted lowercase name of at most 128 characters
     */
    public static function check(string $type): string
    {
        if (\strlen($type) > self::MAX_LENGTH || 1 !== preg_match(self::PATTERN, $type)) {
            throw InvalidDefinition::because(\sprintf('"%s" is not a component type: dotted lowercase names, each a letter then letters, digits or "_", at most %d characters.', $type, self::MAX_LENGTH));
        }

        return $type;
    }
}
