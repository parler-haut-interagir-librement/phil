<?php

declare(strict_types=1);

namespace Phil\Epub\Internal;

use Phil\Epub\Exception\InvalidBook;

/**
 * Paths inside the package directory: checked, resolved from a reference and written relative to a document.
 *
 * @internal
 */
final class Paths
{
    private const string PATH = '#^[A-Za-z0-9_-][A-Za-z0-9._-]*(/[A-Za-z0-9_-][A-Za-z0-9._-]*)*$#D';

    /**
     * @throws InvalidBook
     */
    public static function check(string $path): void
    {
        if (1 !== preg_match(self::PATH, $path)) {
            throw InvalidBook::because(\sprintf('A path in the book is relative to the package directory and made of letters, digits, ".", "_", "-" and "/", no segment starting with a dot; "%s" given.', $path));
        }
    }

    /**
     * The path a relative reference found in the document at `$from` leads to, or an empty path when it leaves the
     * package.
     */
    public static function resolve(string $from, string $reference): string
    {
        $segments = explode('/', $from);
        array_pop($segments);

        foreach (explode('/', $reference) as $segment) {
            if ('..' === $segment) {
                if ([] === $segments) {
                    return '';
                }

                array_pop($segments);
            } elseif ('' !== $segment && '.' !== $segment) {
                $segments[] = $segment;
            }
        }

        return implode('/', $segments);
    }

    /**
     * The reference that leads from the document at `$from` to the file at `$to`, both paths in the package.
     */
    public static function relative(string $from, string $to): string
    {
        $directory = explode('/', $from);
        array_pop($directory);
        $target = explode('/', $to);

        while ([] !== $directory && \count($target) > 1 && $directory[0] === $target[0]) {
            array_shift($directory);
            array_shift($target);
        }

        return str_repeat('../', \count($directory)).implode('/', $target);
    }
}
