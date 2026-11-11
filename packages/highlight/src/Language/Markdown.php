<?php

declare(strict_types=1);

namespace Phil\Highlight\Language;

use Override;
use Phil\Highlight\Language;
use Phil\Highlight\Scanner\Cursor;
use Phil\Highlight\Scanner\Output;
use Phil\Highlight\TokenType;

/**
 * Markdown, line by line: fenced code blocks, headings, thematic breaks and setext underlines, quote and list marks,
 * task boxes; and inside a line, code spans, strong emphasis, emphasis, links, images and autolinks. Each inline mark
 * must close on its line.
 */
final readonly class Markdown implements Language
{
    #[Override]
    public function name(): string
    {
        return 'markdown';
    }

    #[Override]
    public function aliases(): array
    {
        return ['md', 'mkd', 'mdx', 'commonmark', 'gfm'];
    }

    #[Override]
    public function tokenize(string $code): array
    {
        $cursor = new Cursor($code);
        $output = new Output();
        $fence = '';

        while (!$cursor->done()) {
            $output->add($cursor->span(' '));
            $fence = '' === $fence ? $this->line($cursor, $output) : $this->fenced($cursor, $output, $fence);
            $output->add($cursor->take(1));
        }

        return $output->tokens();
    }

    /**
     * A line inside a fenced block: code, or the fence that closes it. The fence still open, or the empty text.
     */
    private function fenced(Cursor $cursor, Output $output, string $fence): string
    {
        $line = $cursor->line();

        if (str_starts_with($line, $fence) && '' === trim(rtrim($line, " \t"), $fence)) {
            $output->add($line, TokenType::Punctuation);

            return '';
        }

        $output->add($line, TokenType::Code);

        return $fence;
    }

    /**
     * A line outside a fenced block. The fence it opens, or the empty text.
     */
    private function line(Cursor $cursor, Output $output): string
    {
        $rest = substr($cursor->text, $cursor->position, strcspn($cursor->text, "\n", $cursor->position));
        $marks = str_replace([' ', "\t"], '', $rest);
        $unique = count_chars($marks, 3);
        $hashes = strspn($rest, '#');

        if ($cursor->at('```') || $cursor->at('~~~')) {
            $fence = $cursor->span($cursor->char());
            $output->add($fence, TokenType::Punctuation);
            $output->add($cursor->line(), TokenType::Meta);

            return $fence;
        }

        if ($hashes > 0 && $hashes < 7 && str_contains(" \t", $rest[$hashes] ?? ' ')) {
            $output->add($cursor->line(), TokenType::Heading);
        } elseif (\strlen($marks) >= 3 && 1 === \strlen($unique) && str_contains('-*_=', $unique)) {
            $output->add($cursor->line(), TokenType::Punctuation);
        } else {
            $this->blocks($cursor, $output);
            $this->inline($cursor, $output);
        }

        return '';
    }

    /**
     * The quote marks, list mark and task box that open a line.
     */
    private function blocks(Cursor $cursor, Output $output): void
    {
        while ($cursor->at('>')) {
            $output->add($cursor->take(1), TokenType::Punctuation);
            $output->add($cursor->span(' '));
        }

        $digits = strspn($cursor->text, Cursor::DIGITS, $cursor->position, 9);
        $list = match (true) {
            $cursor->in('-*+') => 1,
            $digits > 0 && $cursor->in('.)', $digits) => $digits + 1,
            default => null,
        };

        if (null !== $list && $cursor->in(" \t", $list)) {
            $output->add($cursor->take($list), TokenType::Punctuation);
            $output->add($cursor->span(' '));
            $output->add($cursor->at('[ ] ') || $cursor->at('[x] ') || $cursor->at('[X] ') ? $cursor->take(3) : '', TokenType::Punctuation);
        }
    }

    /**
     * The rest of a line, read for inline marks. Each search for a closing mark is remembered, so a line of a thousand
     * lone marks is read in linear time.
     */
    private function inline(Cursor $cursor, Output $output): void
    {
        $line = $cursor->line();
        $length = \strlen($line);
        $memo = [];
        $at = 0;

        while ($at < $length) {
            $plain = strcspn($line, '`*_[!<\\', $at);
            $output->add(substr($line, $at, $plain));
            $at += $plain;

            if ($at < $length) {
                [$size, $type] = $this->mark($line, $at, $memo);
                $output->add(substr($line, $at, $size), $type);
                $at += $size;
            }
        }
    }

    /**
     * The mark at `$at`: its length and type, or 1 and `null` for a lone mark.
     *
     * @param array<string, int> $memo
     *
     * @return array{int, ?TokenType}
     */
    private function mark(string $line, int $at, array &$memo): array
    {
        $char = $line[$at];
        $double = substr($line, $at, 2);

        return match (true) {
            '\\' === $char => [2, null],
            '`' === $char => $this->span($line, $at, str_repeat('`', strspn($line, '`', $at)), TokenType::Code, $memo),
            '**' === $double, '__' === $double => $this->emphasis($line, $at, $double, TokenType::Strong, $memo),
            '*' === $char, '_' === $char && (0 === $at || 0 === strspn($line[$at - 1], Cursor::WORD)) => $this->emphasis($line, $at, $char, TokenType::Emphasis, $memo),
            '[' === $char, '![' === $double => $this->link($line, $at, $memo),
            '<' === $char => $this->autolink($line, $at, $memo),
            default => [1, null],
        };
    }

    /**
     * A span that opens with `$mark` and closes with it, a character at least between.
     *
     * @param array<string, int> $memo
     *
     * @return array{int, ?TokenType}
     */
    private function span(string $line, int $at, string $mark, TokenType $type, array &$memo): array
    {
        $close = $this->find($line, $mark, $at + \strlen($mark) + 1, $memo);

        return null === $close ? [\strlen($mark), null] : [$close + \strlen($mark) - $at, $type];
    }

    /**
     * Emphasis or strong emphasis: a span whose opening mark is followed by a character that is not a space.
     *
     * @param array<string, int> $memo
     *
     * @return array{int, ?TokenType}
     */
    private function emphasis(string $line, int $at, string $mark, TokenType $type, array &$memo): array
    {
        return str_contains(" \t", $line[$at + \strlen($mark)] ?? ' ') ? [\strlen($mark), null] : $this->span($line, $at, $mark, $type, $memo);
    }

    /**
     * `[text](address)`, `[text][label]`, or an image.
     *
     * @param array<string, int> $memo
     *
     * @return array{int, ?TokenType}
     */
    private function link(string $line, int $at, array &$memo): array
    {
        $bracket = $this->find($line, ']', $at, $memo);

        if (null === $bracket) {
            return [1, null];
        }

        $next = $bracket + 1;
        $close = match ($line[$next] ?? '') {
            '(' => $this->find($line, ')', $next, $memo),
            '[' => $this->find($line, ']', $next, $memo),
            default => null,
        };

        return null === $close ? [1, null] : [$close + 1 - $at, TokenType::Link];
    }

    /**
     * `<https://…>` or `<name@host>`.
     *
     * @param array<string, int> $memo
     *
     * @return array{int, ?TokenType}
     */
    private function autolink(string $line, int $at, array &$memo): array
    {
        $close = $this->find($line, '>', $at, $memo);
        $inner = null === $close ? ' ' : substr($line, $at + 1, $close - $at - 1);
        $address = !str_contains($inner, ' ') && (str_starts_with($inner, 'http://') || str_starts_with($inner, 'https://') || str_contains($inner, '@'));

        return $address ? [\strlen($inner) + 2, TokenType::Link] : [1, null];
    }

    /**
     * The first `$needle` at or after `$from`, remembered: a needle found at or after `$from` was the first after an
     * earlier, smaller one; a needle not found stays not found.
     *
     * @param array<string, int> $memo
     */
    private function find(string $line, string $needle, int $from, array &$memo): ?int
    {
        $known = $memo[$needle] ?? -1;

        if ($known < $from) {
            $found = strpos($line, $needle, min($from, \strlen($line)));
            $known = $memo[$needle] = false === $found ? \PHP_INT_MAX : $found;
        }

        return \PHP_INT_MAX === $known ? null : $known;
    }
}
