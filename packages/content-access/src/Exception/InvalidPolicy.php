<?php

declare(strict_types=1);

namespace Phil\ContentAccess\Exception;

use InvalidArgumentException;
use Throwable;

/**
 * An access policy the library refuses: an audience and options that contradict each other, or a stored form that is
 * malformed, of an unknown version or holding a refused rule. The path is the JSON Pointer of the faulty member.
 */
final class InvalidPolicy extends InvalidArgumentException implements ContentAccessException
{
    public static function contradiction(string $why): self
    {
        return new self(\sprintf('The access policy is contradictory: %s.', $why));
    }

    public static function malformed(string $path, string $why): self
    {
        return new self(\sprintf('The access policy is malformed at "%s": %s.', $path, $why));
    }

    public static function notJson(string $why): self
    {
        return new self(\sprintf('The access policy is not valid JSON: %s.', $why));
    }

    public static function tooLarge(int $bytes, int $max): self
    {
        return new self(\sprintf('The access policy holds %d bytes; at most %d are read.', $bytes, $max));
    }

    public static function unsupportedVersion(mixed $version): self
    {
        $shown = \is_int($version) ? (string) $version : get_debug_type($version);

        return new self(\sprintf('The access policy is of version %s; this library reads version 1.', $shown));
    }

    public static function rule(Throwable $previous): self
    {
        return new self(\sprintf('The rule of the access policy at "/rule" is refused: %s', $previous->getMessage()), 0, $previous);
    }
}
