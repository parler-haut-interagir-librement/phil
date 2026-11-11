<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Markdown\Contract;

use Phil\Markup\Reader\Markdown\Context;
use Phil\Ujo\Block;

/**
 * One kind of block a dialect adds: a rule reads the lines from a line on. A line a rule starts also ends the
 * paragraph above it.
 */
interface BlockRule
{
    /**
     * The blocks the line at `$at` starts, made by `Context::block()` and their inline content left as `Inline`, and
     * the index of the line after them; `null` when the line starts none.
     *
     * @param list<string> $lines the lines of the document or of the container being read, without their tabs
     *
     * @return array{list<Block>, int}|null
     */
    public function read(array $lines, int $at, Context $context): ?array;
}
