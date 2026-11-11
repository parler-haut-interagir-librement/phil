<?php

declare(strict_types=1);

namespace Phil\Ujo\Type;

use Closure;
use Phil\Ujo\Block;
use Phil\Ujo\Schema\Shape;

/**
 * A type of block: its canonical name, the other names Editor.js tools save it under, the shape of its data, and four
 * hooks that run in this order:
 *
 * - `fold`, for the tools that save their children flat after them: given the data, the number of following blocks
 *   that belong inside, moved to `data.blocks` before anything else;
 * - `upgrade`, for the older or third-party forms of the data: given the block as written, under the name it was saved
 *   with, the block in canonical form;
 * - `check`, for the rules between values the shape cannot say: given the normalized data and its path, it throws;
 * - `publication`, for the rules a document must also keep to be published, scheduled or reviewed — a value filled in
 *   when another is not —: run only at `Validation::Publication`, given the normalized data and its path, it throws.
 *
 * `maxPerDocument` bounds the blocks of the type one document may hold, nested ones included: a block that reads the
 * whole document when rendered, such as a table of contents, is allowed a few times.
 */
final readonly class BlockType
{
    /**
     * @param list<string>                                    $aliases
     * @param ?Closure(array<array-key, mixed>): int          $fold
     * @param ?Closure(Block): Block                          $upgrade
     * @param ?Closure(array<array-key, mixed>, string): void $check
     * @param ?Closure(array<array-key, mixed>, string): void $publication
     */
    public function __construct(
        public string $name,
        public Shape $data,
        public array $aliases = [],
        public ?Closure $fold = null,
        public ?Closure $upgrade = null,
        public ?Closure $check = null,
        public ?int $maxPerDocument = null,
        public ?Closure $publication = null,
    ) {
    }
}
