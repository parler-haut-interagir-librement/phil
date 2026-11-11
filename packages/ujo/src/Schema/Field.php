<?php

declare(strict_types=1);

namespace Phil\Ujo\Schema;

use Phil\Ujo\Exception\InvalidDocument;

/**
 * One value of a block's data: checks it, and returns it in canonical form.
 */
interface Field
{
    /**
     * @throws InvalidDocument when the value breaks the field, naming its path
     */
    public function normalize(mixed $value, string $path, Context $context): mixed;
}
