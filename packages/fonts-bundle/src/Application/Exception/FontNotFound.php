<?php

declare(strict_types=1);

namespace Phil\FontsBundle\Application\Exception;

use InvalidArgumentException;
use Phil\Fonts\Exception\FontsException;

/**
 * A font the catalogue does not hold: no file at a path, or no face of a family, weight and style.
 */
final class FontNotFound extends InvalidArgumentException implements FontsException
{
    public static function path(string $path, string $directory): self
    {
        return new self(\sprintf('There is no font file "%s" in "%s".', $path, $directory));
    }

    public static function face(string $family, int $weight, string $style, string $formats): self
    {
        return new self(\sprintf('No %s file of the catalogue holds the %s %d face of "%s".', $formats, $style, $weight, $family));
    }
}
