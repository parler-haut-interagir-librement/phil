<?php

declare(strict_types=1);

namespace Phil\Ujo\Schema;

use Closure;
use Override;

/**
 * A field built when first used, for shapes that hold themselves: the items of a nested list, the entries of an index.
 */
final readonly class Lazy implements Field
{
    /**
     * @param Closure(): Field $field
     */
    public function __construct(
        private Closure $field,
    ) {
    }

    #[Override]
    public function normalize(mixed $value, string $path, Context $context): mixed
    {
        return ($this->field)()->normalize($value, $path, $context);
    }
}
