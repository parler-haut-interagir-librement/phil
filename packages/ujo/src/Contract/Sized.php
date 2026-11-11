<?php

declare(strict_types=1);

namespace Phil\Ujo\Contract;

use Phil\Ujo\Schema\Field;

/**
 * A field whose normalized values have a size: the characters a reader sees of a text, the items of a list. A
 * property of such a field can be required filled in, and the field bounded, for publication.
 */
interface Sized extends Field
{
    /**
     * The size of a value this field normalized.
     */
    public function size(mixed $value): int;

    /**
     * What the size counts, in the plural: `characters`, `items`.
     */
    public function unit(): string;
}
