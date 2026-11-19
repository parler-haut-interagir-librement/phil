<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal;

use Closure;

/**
 * The paragraph being read: its inline text, and how it becomes a block. A line without a block — the text of a
 * table cell or of a note — cannot be cut by an image or a page break.
 *
 * @internal
 */
final class Line
{
    public private(set) Inline $inline;

    /**
     * @param (Closure(string): void)|null $emit
     */
    public function __construct(
        private readonly ?Closure $emit = null,
    ) {
        $this->inline = new Inline();
    }

    public function holdsBlocks(): bool
    {
        return $this->emit instanceof Closure;
    }

    /**
     * The text read so far written as its block, unless it is blank, and a new text started.
     */
    public function flush(): void
    {
        if ($this->emit instanceof Closure && !$this->inline->isBlank()) {
            ($this->emit)($this->inline->toHtml());
        }

        $this->inline = new Inline();
    }
}
