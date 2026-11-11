<?php

declare(strict_types=1);

namespace Phil\Markup\Lexer;

/**
 * A text read forward, byte by byte, by the readers of every format: a lexer asks what comes next, consumes it, and
 * moves back when a rule does not match. The characters that make syntax are ASCII, so bytes are enough: a character
 * of several bytes is consumed whole by the methods that consume runs.
 */
final class Cursor
{
    private int $position = 0;

    private readonly int $length;

    public function __construct(
        public readonly string $text,
    ) {
        $this->length = \strlen($text);
    }

    public function position(): int
    {
        return $this->position;
    }

    /**
     * Goes back, or forward, to a position read before: how a rule that does not match gives the text back.
     */
    public function moveTo(int $position): void
    {
        $this->position = max(0, min($position, $this->length));
    }

    public function isEnd(): bool
    {
        return $this->position >= $this->length;
    }

    /**
     * The byte at the position, `''` at the end.
     */
    public function current(): string
    {
        return $this->text[$this->position] ?? '';
    }

    /**
     * The byte at an offset from the position, `''` outside the text.
     */
    public function peek(int $offset = 1): string
    {
        $at = $this->position + $offset;

        return $at < 0 ? '' : $this->text[$at] ?? '';
    }

    /**
     * Whether the text at the position starts with this one.
     */
    public function comesNext(string $text): bool
    {
        return str_starts_with(substr($this->text, $this->position, \strlen($text)), $text);
    }

    /**
     * Moves past this text when it comes next.
     */
    public function consume(string $text): bool
    {
        if (!$this->comesNext($text)) {
            return false;
        }

        $this->position += \strlen($text);

        return true;
    }

    /**
     * The bytes up to an offset from the position, consumed.
     */
    public function advance(int $length = 1): string
    {
        $consumed = substr($this->text, $this->position, $length);
        $this->position += \strlen($consumed);

        return $consumed;
    }

    /**
     * The run of bytes, from the position, that are all among these characters, consumed.
     */
    public function consumeWhile(string $characters): string
    {
        return $this->advance(strspn($this->text, $characters, $this->position));
    }

    /**
     * The run of bytes, from the position, that are none of these characters, consumed: it stops before the first
     * one, or at the end.
     */
    public function consumeUntil(string $characters): string
    {
        return $this->advance(strcspn($this->text, $characters, $this->position));
    }

    /**
     * The text up to a delimiter, consumed with the delimiter; `null`, and nothing consumed, when it never comes.
     */
    public function consumeThrough(string $delimiter): ?string
    {
        $at = strpos($this->text, $delimiter, $this->position);

        if (false === $at) {
            return null;
        }

        $consumed = $this->advance($at - $this->position);
        $this->position += \strlen($delimiter);

        return $consumed;
    }

    /**
     * What is left from the position, consumed.
     */
    public function rest(): string
    {
        return $this->advance($this->length);
    }
}
