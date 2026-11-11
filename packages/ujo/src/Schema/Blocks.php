<?php

declare(strict_types=1);

namespace Phil\Ujo\Schema;

use Override;
use Phil\Ujo\Block;
use Phil\Ujo\Contract\Sized;

/**
 * A list of nested blocks — the content of a column, a toggle, a note —, each normalized against its own type.
 */
final readonly class Blocks implements Sized
{
    /**
     * @return list<Block>
     */
    #[Override]
    public function normalize(mixed $value, string $path, Context $context): array
    {
        return $context->blocks($value, $path);
    }

    #[Override]
    public function size(mixed $value): int
    {
        return \is_array($value) ? \count($value) : 0;
    }

    #[Override]
    public function unit(): string
    {
        return 'blocks';
    }
}
