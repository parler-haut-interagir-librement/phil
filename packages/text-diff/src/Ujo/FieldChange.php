<?php

declare(strict_types=1);

namespace Phil\TextDiff\Ujo;

use Phil\TextDiff\Diff;

/**
 * One value of a block that changed: its path in the block — `type`, `data.text`, `data.items.0.content`,
 * `tunes.alignment.alignment` —, its old and new value in their JSON form, `null` where the value is missing, and,
 * when both are texts, their difference word by word, tags kept whole.
 */
final readonly class FieldChange
{
    public function __construct(
        public string $path,
        public mixed $old,
        public mixed $new,
        public ?Diff $diff,
    ) {
    }
}
