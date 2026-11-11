<?php

declare(strict_types=1);

namespace Phil\Ujo\Schema;

use Phil\Ujo\Contract\Sized;

/**
 * A named value of a shape: its field, whether it must be present, the value it takes when absent, whether it may be
 * `null`, and whether it must be filled in for publication — not absent, not `null`, and of a size above zero when its
 * field is `Sized`, not empty otherwise.
 */
final readonly class Property
{
    private function __construct(
        public Field $field,
        public bool $required,
        public bool $hasDefault,
        public mixed $default,
        public bool $nullable,
        public bool $filled = false,
    ) {
    }

    public static function required(Field $field): self
    {
        return new self($field, true, false, null, false);
    }

    /**
     * A value left out of the canonical data when absent.
     */
    public static function optional(Field $field): self
    {
        return new self($field, false, false, null, false);
    }

    /**
     * A value written with its default when absent.
     */
    public static function defaults(Field $field, mixed $default): self
    {
        return new self($field, false, true, $default, false);
    }

    public function nullable(): self
    {
        return new self($this->field, $this->required, $this->hasDefault, $this->default, true, $this->filled);
    }

    /**
     * The value must be filled in at `Validation::Publication`; a draft may leave it empty.
     */
    public function filled(): self
    {
        return new self($this->field, $this->required, $this->hasDefault, $this->default, $this->nullable, true);
    }

    /**
     * Whether a normalized value, `null` when absent, fills the property in.
     */
    public function isFilledBy(mixed $value): bool
    {
        if ($this->field instanceof Sized) {
            return null !== $value && $this->field->size($value) > 0;
        }

        return null !== $value && '' !== $value && [] !== $value;
    }
}
