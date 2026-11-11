<?php

declare(strict_types=1);

namespace Phil\Highlight\Language;

/**
 * A kind of quoted text: how it opens and closes, its escape character, whether it may span lines, and whether it holds
 * a single character or escape — a character literal, so that a Rust lifetime, `'a`, is not read as the start of a
 * quoted text. A doubled closing mark, `'it''s'` in SQL, needs nothing: the two quoted texts it makes are joined.
 */
final readonly class Quote
{
    public string $close;

    /**
     * @param string $escape the escape character, or the empty text for none
     */
    public function __construct(
        public string $open,
        ?string $close = null,
        public string $escape = '\\',
        public bool $multiline = false,
        public bool $character = false,
    ) {
        $this->close = $close ?? $open;
    }
}
