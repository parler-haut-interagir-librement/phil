<?php

declare(strict_types=1);

namespace Phil\SamlSp\Xml;

use InvalidArgumentException;

/**
 * The bounds of a document read from the outside: its bytes, the depth of its elements and the number of its nodes
 * (elements, attributes and texts). A message of the browser fits `message()`; the metadata of an identity provider
 * fits `metadata()`.
 */
final readonly class XmlBudget
{
    public const int MAX_BYTES = 16 * 1024 * 1024;

    public const int MAX_DEPTH = 256;

    public const int MAX_NODES = 1_000_000;

    public function __construct(
        public int $maxBytes = 262_144,
        public int $maxDepth = 32,
        public int $maxNodes = 10_000,
    ) {
        if ($maxBytes < 1 || $maxBytes > self::MAX_BYTES) {
            throw new InvalidArgumentException(\sprintf('The bytes of a document are between 1 and %d; %d is not.', self::MAX_BYTES, $maxBytes));
        }

        if ($maxDepth < 1 || $maxDepth > self::MAX_DEPTH) {
            throw new InvalidArgumentException(\sprintf('The depth of a document is between 1 and %d; %d is not.', self::MAX_DEPTH, $maxDepth));
        }

        if ($maxNodes < 1 || $maxNodes > self::MAX_NODES) {
            throw new InvalidArgumentException(\sprintf('The nodes of a document are between 1 and %d; %d is not.', self::MAX_NODES, $maxNodes));
        }
    }

    /**
     * A message posted or redirected by the browser: 256 KiB, 32 levels, 10,000 nodes.
     */
    public static function message(): self
    {
        return new self();
    }

    /**
     * The metadata of one identity provider, or of a small federation: 4 MiB, 48 levels, 200,000 nodes.
     */
    public static function metadata(): self
    {
        return new self(4 * 1024 * 1024, 48, 200_000);
    }
}
