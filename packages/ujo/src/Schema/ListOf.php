<?php

declare(strict_types=1);

namespace Phil\Ujo\Schema;

use Override;
use Phil\Ujo\Contract\Sized;
use Phil\Ujo\Exception\InvalidDocument;

/**
 * A list of values of one field, with a number of items within bounds.
 */
final readonly class ListOf implements Sized
{
    public function __construct(
        private Field $item,
        private ?int $min = null,
        private ?int $max = null,
    ) {
    }

    /**
     * @return list<mixed>
     */
    #[Override]
    public function normalize(mixed $value, string $path, Context $context): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            throw InvalidDocument::at($path, 'a list is expected');
        }

        $count = \count($value);

        if (null !== $this->min && $count < $this->min || null !== $this->max && $count > $this->max) {
            throw InvalidDocument::at($path, \sprintf('%d items, where %d to %s are expected', $count, $this->min ?? 0, $this->max ?? 'any number'));
        }

        $normalized = [];

        foreach ($value as $index => $item) {
            $normalized[] = $this->item->normalize($item, $path.'['.$index.']', $context);
        }

        return $normalized;
    }

    #[Override]
    public function size(mixed $value): int
    {
        return \is_array($value) ? \count($value) : 0;
    }

    #[Override]
    public function unit(): string
    {
        return 'items';
    }
}
