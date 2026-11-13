<?php

declare(strict_types=1);

namespace Phil\Epub\Internal;

/**
 * A heading found in a content document: its level, its identifier and its text.
 *
 * @internal
 */
final readonly class Heading
{
    public function __construct(public int $level, public string $id, public string $text)
    {
    }
}
