<?php

declare(strict_types=1);

namespace Phil\Ujo\Schema;

use Override;
use Phil\Ujo\Exception\InvalidDocument;

/**
 * `true` or `false`.
 */
final readonly class Boolean implements Field
{
    #[Override]
    public function normalize(mixed $value, string $path, Context $context): bool
    {
        if (!\is_bool($value)) {
            throw InvalidDocument::at($path, 'true or false is expected');
        }

        return $value;
    }
}
