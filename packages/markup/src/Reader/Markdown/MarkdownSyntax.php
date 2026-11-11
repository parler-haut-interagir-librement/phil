<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Markdown;

/**
 * The built-in syntax of Markdown a dialect may turn off, when it gives its lines another meaning.
 */
enum MarkdownSyntax: string
{
    /** The `:::` blocks, `::: note Title` up to `:::`. */
    case ColonBlocks = 'colon_blocks';
}
