<?php

declare(strict_types=1);

namespace Phil\Ujo\Exception;

use InvalidArgumentException;

/**
 * A block, or a tune, whose type no registry knows, or that no renderer renders.
 */
final class UnknownBlockType extends InvalidArgumentException implements UjoException
{
    private function __construct(
        string $message,
        public readonly string $type,
    ) {
        parent::__construct($message);
    }

    public static function block(string $type, string $path): self
    {
        return new self(\sprintf('The block type "%s" at %s is not registered.', $type, $path), $type);
    }

    public static function tune(string $tune, string $path): self
    {
        return new self(\sprintf('The tune "%s" at %s is not registered.', $tune, $path), $tune);
    }

    public static function renderer(string $type, string $output): self
    {
        return new self(\sprintf('No %s renderer is registered for the block type "%s".', $output, $type), $type);
    }
}
