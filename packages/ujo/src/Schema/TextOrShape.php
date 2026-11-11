<?php

declare(strict_types=1);

namespace Phil\Ujo\Schema;

use Override;

/**
 * A value written either as a text, normalized by one field, or as an object, normalized by a shape: a cell of a
 * table, a text or `{"text", "colspan", ...}`.
 */
final readonly class TextOrShape implements Field
{
    public function __construct(
        private Field $text,
        private Shape $shape,
    ) {
    }

    #[Override]
    public function normalize(mixed $value, string $path, Context $context): mixed
    {
        return \is_string($value) ? $this->text->normalize($value, $path, $context) : $this->shape->normalize($value, $path, $context);
    }
}
