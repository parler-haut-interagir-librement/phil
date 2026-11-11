<?php

declare(strict_types=1);

namespace Phil\PrintCompositor;

use Phil\PrintCompositor\Exception\InvalidLayout;

/**
 * Items drawn in order, the later over the earlier, in one ink: the layers of one ink go to the same print file, those
 * of another ink, such as a glow-in-the-dark layer, to a file of their own.
 */
final readonly class Layer
{
    public const string DEFAULT_INK = 'colour';

    /**
     * @param list<Item> $items
     */
    public function __construct(
        public string $name,
        public array $items,
        public string $ink = self::DEFAULT_INK,
    ) {
        if ('' === trim($name) || '' === trim($ink)) {
            throw InvalidLayout::because('A layer has a name and an ink.');
        }
    }
}
