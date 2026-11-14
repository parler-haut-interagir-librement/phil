<?php

declare(strict_types=1);

namespace Phil\Markup\Exception;

use LogicException;

/**
 * An adapter built on a PHP extension that is not loaded: install it, or use the adapter in pure PHP.
 */
final class MissingExtension extends LogicException implements MarkupException
{
    public function __construct(
        string $message,
        public readonly string $extension = '',
    ) {
        parent::__construct($message);
    }

    public static function named(string $extension, string $adapter): self
    {
        return new self(\sprintf('%s needs the "%s" extension of PHP, which is not loaded.', $adapter, $extension), $extension);
    }
}
