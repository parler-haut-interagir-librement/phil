<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Application\Exception;

use InvalidArgumentException;
use Phil\PagedMedia\Exception\PagedMediaException;

/**
 * The parameters of a printed template that it cannot draw from: a value missing or of the wrong kind, named by its
 * path in the parameters.
 */
final class InvalidTemplateParameters extends InvalidArgumentException implements PagedMediaException
{
    public static function at(string $template, string $path, string $expected): self
    {
        return new self(\sprintf('The template "%s" expects %s at "%s".', $template, $expected, $path));
    }
}
