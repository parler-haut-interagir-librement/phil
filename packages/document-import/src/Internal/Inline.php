<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal;

/**
 * The text of one paragraph as it is read, run after run, written as inline HTML whose tags open and close only where
 * the formatting changes.
 *
 * @internal
 */
final class Inline
{
    /** @var list<array{string, list<string>}> */
    private array $parts = [];

    private bool $blank = true;

    /**
     * @param list<string> $tags
     */
    public function text(string $text, array $tags): void
    {
        $this->parts[] = [htmlspecialchars($text, \ENT_QUOTES), $tags];
        $this->blank = $this->blank && '' === trim($text);
    }

    /**
     * A line break, which is not text.
     *
     * @param list<string> $tags
     */
    public function lineBreak(array $tags): void
    {
        $this->parts[] = ['<br>', $tags];
    }

    /**
     * Inline HTML already written, a note reference.
     */
    public function html(string $html): void
    {
        $this->parts[] = [$html, []];
        $this->blank = false;
    }

    /**
     * The last characters of the text written last taken back, a hyphen that cut a word at the end of a line.
     */
    public function dropLast(int $characters): void
    {
        $last = array_key_last($this->parts);

        if (null !== $last) {
            $this->parts[$last][0] = mb_substr($this->parts[$last][0], 0, -$characters);
        }
    }

    public function isBlank(): bool
    {
        return $this->blank;
    }

    public function toHtml(): string
    {
        $html = '';
        $open = [];

        foreach ($this->parts as [$part, $tags]) {
            $kept = 0;

            while (isset($open[$kept], $tags[$kept]) && $open[$kept] === $tags[$kept]) {
                ++$kept;
            }

            $html .= $this->close(\array_slice($open, $kept)).implode('', \array_slice($tags, $kept)).$part;
            $open = $tags;
        }

        return trim($html.$this->close($open));
    }

    /**
     * @param list<string> $tags
     */
    private function close(array $tags): string
    {
        $html = '';

        foreach (array_reverse($tags) as $tag) {
            $html .= '</'.substr($tag, 1, strcspn($tag, ' >', 1)).'>';
        }

        return $html;
    }
}
