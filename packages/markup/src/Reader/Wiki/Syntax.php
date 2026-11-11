<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Wiki;

use Phil\Markup\Lexer\Cursor;
use Phil\Ujo\Block;

/**
 * What one wiki adds to the shared reader: its own blocks — headings, lists, tables, preformatted text —, the
 * characters that start its inline syntax and the tokens they make.
 *
 * @internal
 */
interface Syntax
{
    public function dialect(): Dialect;

    /**
     * The source as the reader reads it, once its new lines are made `\n`: comments left out, for a wiki that has them.
     */
    public function prepare(string $source): string;

    /**
     * The characters that may start a token of the wiki's inline syntax.
     */
    public function triggers(): string;

    /**
     * A token at the position: HTML, marks of emphasis, or several of them; `null`, the cursor where it was, when
     * nothing of the wiki starts there.
     *
     * @return list<Toggle|string>|Toggle|string|null
     */
    public function inline(Cursor $cursor, Spans $spans): array|Toggle|string|null;

    /**
     * The blocks a native block at the current line makes, its lines consumed; `null` when the line starts none.
     *
     * @return list<Block>|null
     */
    public function block(Engine $engine, int $depth): ?array;
}
