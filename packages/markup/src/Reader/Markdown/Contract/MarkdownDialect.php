<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Markdown\Contract;

use Phil\Markup\Reader\Markdown\Context;
use Phil\Markup\Reader\Markdown\MarkdownSyntax;
use Phil\Markup\Reader\Markdown\Rule\InlineRule;

/**
 * A dialect of Markdown, given to `MarkdownReader`: the syntax it adds — inline rules tried after the built-in ones on
 * their characters, block rules tried after the built-in ones on a line —, the built-in syntax it turns off, and what
 * it reads differently: the metadata before the text, the `[label]: value` lines, the marks that start a definition
 * and the start of a paragraph. `BaseDialect` reads each of these as plain Markdown does.
 *
 * A dialect is shared by every reading: it keeps nothing between two calls, and what it remembers during one reading
 * goes into `Context::$extra`.
 */
interface MarkdownDialect
{
    /**
     * The inline rules the dialect adds, tried after the built-in rules of the same characters, in this order.
     *
     * @return list<InlineRule>
     */
    public function inlineRules(): array;

    /**
     * The block rules the dialect adds, tried on a line after the built-in rules, in this order.
     *
     * @return list<BlockRule>
     */
    public function blockRules(): array;

    /**
     * The built-in syntax the dialect turns off, its lines then left to the dialect's rules or to the text.
     *
     * @return list<MarkdownSyntax>
     */
    public function disables(): array;

    /**
     * The metadata written before the text, and the lines after it; `null` to read the YAML front matter of Markdown.
     *
     * @param list<string> $lines
     *
     * @return array{array<array-key, mixed>, list<string>}|null
     */
    public function frontMatter(array $lines): ?array;

    /**
     * Whether the dialect reads a definition line, `[label]: value`, itself — then it makes no link reference. It may
     * keep the value in `Context::$extra`.
     */
    public function define(string $label, string $value, Context $context): bool;

    /**
     * The text of the definition a line starts under a term, its mark left out; `null` when the line starts none. The
     * built-in mark, `: `, is tried first.
     */
    public function definition(string $line): ?string;

    /**
     * The text of a paragraph without the mark it starts with, and the tunes the mark gives the paragraph.
     *
     * @return array{string, array<string, mixed>}
     */
    public function paragraph(string $text): array;
}
