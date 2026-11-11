<?php

declare(strict_types=1);

namespace Phil\Ujo\Type;

use Phil\Ujo\Schema\Field;

/**
 * A tune: its canonical name, the other names it is saved under, and the field of its value — an object for most, a
 * text for `textVariant`.
 */
final readonly class TuneType
{
    /**
     * @param list<string> $aliases
     */
    public function __construct(
        public string $name,
        public Field $value,
        public array $aliases = [],
    ) {
    }
}
