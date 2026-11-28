<?php

declare(strict_types=1);

namespace Phil\Rules\Exception;

use InvalidArgumentException;

/**
 * A rule the library refuses to load: malformed, of an unknown version, naming a condition type nobody registered, or
 * with operands its type does not accept. The path is the JSON Pointer of the faulty node inside the condition.
 */
final class InvalidRule extends InvalidArgumentException implements RulesException
{
    public static function malformed(string $path, string $why): self
    {
        return new self(\sprintf('The rule is malformed at "%s": %s.', $path, $why));
    }

    public static function notJson(string $why): self
    {
        return new self(\sprintf('The rule is not valid JSON: %s.', $why));
    }

    public static function unsupportedVersion(mixed $version): self
    {
        $shown = \is_int($version) ? (string) $version : get_debug_type($version);

        return new self(\sprintf('The rule is of version %s; this library reads version 1.', $shown));
    }

    public static function unknownType(string $path, string $type): self
    {
        return new self(\sprintf('The condition at "%s" is of type "%s", which no package registered.', $path, self::shown($type)));
    }

    public static function operand(string $path, string $type, string $operand, string $why): self
    {
        return new self(\sprintf('The operand "%s" of the condition "%s" at "%s" %s.', self::shown($operand), $type, $path, $why));
    }

    public static function unsupportedNode(string $path, string $class): self
    {
        return new self(\sprintf('The node at "%s" is a %s; a condition is All, Any, Not or Leaf.', $path, $class));
    }

    /**
     * A name read from a rule, shortened and without control characters, to show in a message.
     */
    public static function shown(string $name): string
    {
        return mb_substr((string) preg_replace('/[\x00-\x1F\x7F]/', '?', $name), 0, 64);
    }
}
