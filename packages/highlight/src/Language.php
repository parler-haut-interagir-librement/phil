<?php

declare(strict_types=1);

namespace Phil\Highlight;

/**
 * A language the highlighter reads. A tokenizer never throws and never loses a byte: whatever the source — broken,
 * unfinished, not even of this language —, the texts of its tokens joined are the source.
 */
interface Language
{
    /**
     * The canonical name, in lower case: `javascript`, `cpp`.
     */
    public function name(): string;

    /**
     * Other names the language is asked for by, in lower case: `js`, `c++`.
     *
     * @return list<string>
     */
    public function aliases(): array;

    /**
     * @return list<Token>
     */
    public function tokenize(string $code): array;
}
