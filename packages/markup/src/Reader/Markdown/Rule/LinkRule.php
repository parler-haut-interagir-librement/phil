<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Markdown\Rule;

use Override;
use Phil\Markup\Lexer\Cursor;
use Phil\Markup\Reader\Markdown\Context;
use Phil\Markup\Reader\Markdown\InlineParser;

/**
 * Links and images: `[text](address "title")`, `[text][label]`, `[text][]`, `[label]` and the same after `!`, and
 * footnote references `[^label]`. A label refers to a definition anywhere in the source, but for the definitions a
 * dialect reads itself. The text of an image is its alternative text, its title — written after
 * its address or in its definition — its caption; a link's title is left out, the inline tools having none. The
 * braces right after an image, `{width:200px}`, are its attributes.
 *
 * @internal
 */
final readonly class LinkRule implements InlineRule
{
    #[Override]
    public function triggers(): string
    {
        return '[!';
    }

    #[Override]
    public function lex(Cursor $cursor, InlineParser $parser): ?string
    {
        $start = $cursor->position();
        $image = $cursor->consume('!');
        $open = $cursor->position();
        $note = $image ? null : $this->footnote($cursor, $parser->context);

        if (null !== $note) {
            return $note;
        }

        $cursor->moveTo($open);
        $label = $this->label($cursor);
        $target = null === $label ? null : $this->destination($cursor) ?? $this->reference($cursor, $label, $parser->context);

        if (null === $label || null === $target || (!$image && $parser->inLink())) {
            $cursor->moveTo($start);

            return null;
        }

        [$address, $title] = $target;

        return $image ? $parser->image($address, $label, $this->injection($cursor), $title) : InlineParser::link($address, $parser->parse($label, true));
    }

    private function footnote(Cursor $cursor, Context $context): ?string
    {
        $label = $cursor->consume('[^') ? $cursor->consumeUntil("[] \t\n") : '';
        $number = $cursor->consume(']') ? $context->footnote($label) : null;

        return null === $number ? null : \sprintf('<sup data-footnote="fn-%1$d"><a href="#footnote-fn-%1$d">%1$d</a></sup>', $number);
    }

    /**
     * The text between brackets, nested brackets — up to 32 deep — and escaped ones included, consumed with them.
     */
    private function label(Cursor $cursor): ?string
    {
        if (!$cursor->consume('[')) {
            return null;
        }

        $start = $cursor->position();
        $depth = 1;

        while (true) {
            $cursor->consumeUntil('[]\\');
            $character = $cursor->advance();

            if ('' === $character) {
                return null;
            }

            if ('\\' === $character) {
                $cursor->advance();

                continue;
            }

            $depth += '[' === $character ? 1 : -1;

            if ($depth > 32) {
                return null;
            }

            if (0 === $depth) {
                return substr($cursor->text, $start, $cursor->position() - $start - 1);
            }
        }
    }

    /**
     * `(address "title")`: the address, `<…>` or a run without spaces whose parentheses are balanced — up to 32 deep —, its escapes and
     * entities read, its spaces encoded; and the title, its escapes and entities read, empty without one.
     *
     * @return array{string, string}|null
     */
    private function destination(Cursor $cursor): ?array
    {
        $start = $cursor->position();

        if (!$cursor->consume('(')) {
            return null;
        }

        $cursor->consumeWhile(" \t\n");
        $address = $cursor->consume('<') ? $this->angled($cursor) : $this->bare($cursor);
        $spaces = $cursor->consumeWhile(" \t\n");
        $close = ['"' => '"', "'" => "'", '(' => ')'][$cursor->current()] ?? null;
        $title = '';

        if (null !== $close && '' !== $spaces) {
            $cursor->advance();
            $title = $cursor->consumeThrough($close);
        }

        $cursor->consumeWhile(" \t\n");

        if (null === $address || null === $title || !$cursor->consume(')')) {
            $cursor->moveTo($start);

            return null;
        }

        return [InlineParser::destination($address), InlineParser::title($title)];
    }

    private function angled(Cursor $cursor): ?string
    {
        $address = $cursor->consumeUntil("<>\n");

        return $cursor->consume('>') ? $address : null;
    }

    private function bare(Cursor $cursor): string
    {
        $start = $cursor->position();
        $open = [];

        while (!$cursor->isEnd()) {
            $character = $cursor->current();

            if (str_contains(" \t\n", $character) || (')' === $character && [] === $open) || \count($open) > 32) {
                break;
            }

            match ($character) {
                '(' => $open[] = $character,
                ')' => array_pop($open),
                default => null,
            };
            $cursor->advance('\\' === $character ? 2 : 1);
        }

        return substr($cursor->text, $start, $cursor->position() - $start);
    }

    /**
     * The address and title of a reference — `[label]`, `[]` for the text itself, or nothing for a shortcut —, the
     * reference consumed; `null` when no definition has that label, the caller then giving the text back.
     *
     * @return array{string, string}|null
     */
    private function reference(Cursor $cursor, string $text, Context $context): ?array
    {
        $full = $cursor->consume('[');
        $label = $full ? $cursor->consumeUntil('[]') : '';
        $key = Context::label('' === $label ? $text : $label);
        $address = !$full || $cursor->consume(']') ? $context->references[$key] ?? null : null;

        return null === $address ? null : [$address, $context->titles[$key] ?? ''];
    }

    /**
     * The braces right after an image, spaces allowed before them on the same line: their text, consumed.
     */
    private function injection(Cursor $cursor): string
    {
        $start = $cursor->position();
        $cursor->consumeWhile(' ');

        if ($cursor->comesNext('{{') || !$cursor->consume('{')) {
            $cursor->moveTo($start);

            return '';
        }

        $attributes = $cursor->consumeUntil("}\n");

        if (!$cursor->consume('}')) {
            $cursor->moveTo($start);

            return '';
        }

        return $attributes;
    }
}
