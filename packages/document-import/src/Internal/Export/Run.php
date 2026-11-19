<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Export;

use Phil\DocumentImport\Internal\Mark;

/**
 * A piece of text with its character formatting and link, a line break, or the reference to a footnote by its
 * identifier.
 *
 * @internal
 */
final readonly class Run
{
    /**
     * @param list<Mark> $marks
     */
    public function __construct(
        public string $text,
        public array $marks = [],
        public ?string $link = null,
        public bool $break = false,
        public ?string $note = null,
    ) {
    }

    public function has(Mark $mark): bool
    {
        return \in_array($mark, $this->marks, true);
    }
}
