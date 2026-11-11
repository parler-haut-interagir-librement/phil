<?php

declare(strict_types=1);

namespace Phil\Highlight\Language;

use Override;
use Phil\Highlight\Language;
use Phil\Highlight\Scanner\Cursor;
use Phil\Highlight\Scanner\Output;
use Phil\Highlight\TokenType;

/**
 * YAML, line by line: comments, document markers, sequence dashes, keys, quoted and plain scalars — a plain scalar is
 * a constant, a number or a string —, anchors, aliases, tags, flow collections and the lines of block scalars.
 */
final readonly class Yaml implements Language
{
    private const array CONSTANTS = ['true', 'false', 'yes', 'no', 'on', 'off', 'null', '~'];

    /** How each flow indicator changes the depth of flow collections. */
    private const array FLOW = ['[' => 1, '{' => 1, ']' => -1, '}' => -1, ',' => 0];

    #[Override]
    public function name(): string
    {
        return 'yaml';
    }

    #[Override]
    public function aliases(): array
    {
        return ['yml'];
    }

    #[Override]
    public function tokenize(string $code): array
    {
        $cursor = new Cursor($code);
        $output = new Output();
        $block = null;

        while (!$cursor->done()) {
            $indent = $cursor->span(" \t");
            $output->add($indent);

            if (null !== $block && (\strlen($indent) > $block || $cursor->in("\n"))) {
                $output->add($cursor->line(), TokenType::String);
            } else {
                $block = $this->line($cursor, $output, \strlen($indent));
            }

            $output->add($cursor->take(1));
        }

        return $output->tokens();
    }

    /**
     * One line after its indentation; the indentation of the line when it opens a block scalar.
     */
    private function line(Cursor $cursor, Output $output, int $indent): ?int
    {
        if (0 === $indent && ($cursor->at('---') || $cursor->at('...'))) {
            $output->add($cursor->take(3), TokenType::Meta);
        }

        while ($cursor->at('-') && str_contains(" \t\n", $cursor->char(1))) {
            $output->add($cursor->take(1), TokenType::Punctuation);
            $output->add($cursor->span(" \t"));
        }

        $key = $this->key($cursor);

        if (null !== $key) {
            $output->add($cursor->take($key), TokenType::Property);
            $output->add($cursor->span(" \t"));
            $output->add($cursor->take(1), TokenType::Punctuation);
        }

        return $this->value($cursor, $output, $indent);
    }

    /**
     * The length of the key at the position — a plain or quoted text followed by `:` and a space or the end of the
     * line —, or `null`.
     */
    private function key(Cursor $cursor): ?int
    {
        $text = $cursor->text;
        $start = $cursor->position;

        if ($cursor->in('[]{}&*!|>%@`#')) {
            return null;
        }

        if ($cursor->in('"\'')) {
            $end = $start + 1 + strcspn($text, implode('', [$text[$start], "\n"]), $start + 1);
            $colon = $end + 1 + strspn($text, " \t", $end + 1);

            return $text[$start] === ($text[$end] ?? '') && ':' === ($text[$colon] ?? '') && 1 === strspn($text[$colon + 1] ?? ' ', Cursor::SPACE) ? $end + 1 - $start : null;
        }

        $end = $start;

        do {
            $end += strcspn($text, ":\n#", $end);
            $inner = ':' === ($text[$end] ?? '') && 1 !== strspn($text[$end + 1] ?? ' ', Cursor::SPACE);
            $end += $inner ? 1 : 0;
        } while ($inner);

        return ':' === ($text[$end] ?? '') && $end > $start ? \strlen(rtrim(substr($text, $start, $end - $start), " \t")) : null;
    }

    /**
     * The value of a line, up to its line feed; the indentation of the line when it opens a block scalar.
     */
    private function value(Cursor $cursor, Output $output, int $indent): ?int
    {
        $flow = 0;
        $block = null;

        while (!$cursor->done() && !$cursor->at("\n")) {
            [$text, $type] = match (true) {
                $cursor->in(" \t") => [$cursor->span(" \t"), null],
                $cursor->at('#') => [$cursor->line(), TokenType::Comment],
                $cursor->in('|>') && null === $block && 0 === $flow => [$cursor->span('|>-+0123456789'), TokenType::Operator],
                $cursor->in('&*') => [$cursor->leadUntil(Cursor::SPACE, ',[]{}'), TokenType::Variable],
                $cursor->at('!') => [$cursor->leadUntil(Cursor::SPACE), TokenType::Meta],
                $cursor->in('"\'') => [$cursor->quoted(), TokenType::String],
                $cursor->in('[{') => [$cursor->take(1), TokenType::Punctuation],
                $flow > 0 && $cursor->in(']},') => [$cursor->take(1), TokenType::Punctuation],
                default => $this->scalar($cursor, $flow > 0),
            };

            $output->add($text, $type);
            $flow += TokenType::Punctuation === $type ? self::FLOW[$text] : 0;
            $block = TokenType::Operator === $type ? $indent : $block;
        }

        return $block;
    }

    /**
     * A plain scalar up to a comment, the line feed, or in a flow collection a flow indicator; its trailing spaces are
     * left for the next token.
     *
     * @return array{string, ?TokenType}
     */
    private function scalar(Cursor $cursor, bool $flow): array
    {
        $text = $cursor->untilComment($flow ? "\n#,[]{}" : "\n#", '#');
        $value = rtrim($text, " \t");
        $cursor->position -= \strlen($text) - \strlen($value);

        return [$value, match (true) {
            \in_array(strtolower($value), self::CONSTANTS, true) => TokenType::Constant,
            is_numeric($value) => TokenType::Number,
            default => TokenType::String,
        }];
    }
}
