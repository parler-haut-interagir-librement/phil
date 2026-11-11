<?php

declare(strict_types=1);

namespace Phil\Ujo\Exception;

use InvalidArgumentException;

/**
 * A document whose structure or block data breaks the model, at a path such as `blocks[2].data.level`.
 */
final class InvalidDocument extends InvalidArgumentException implements UjoException
{
    private function __construct(
        string $message,
        public readonly string $path,
    ) {
        parent::__construct($message);
    }

    public static function at(string $path, string $reason): self
    {
        return new self(\sprintf('The UJO document is invalid at %s: %s.', $path, $reason), $path);
    }
}
