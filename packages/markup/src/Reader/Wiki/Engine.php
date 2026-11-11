<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Wiki;

use Phil\Markup\Reader\Markdown\Context;
use Phil\Markup\Reader\Markdown\Inline;
use Phil\Markup\Reader\Markdown\InlineParser;
use Phil\Ujo\Block;

/**
 * The blocks of a wiki text, read line by line: a blank line ends a paragraph; an extension tag alone on its line
 * opens a container — a note, columns, a stat block —, which the closing tag of its name, or of a container around
 * it, closes; the other lines are tried as the wiki's own blocks, and the lines left gather into paragraphs. A
 * container never closed holds the rest of the text, so that each line is read once.
 *
 * @internal
 */
final class Engine
{
    /** @var array<array-key, mixed> the metadata of the source, from its `metadata` and `style` tags */
    public array $metadata = [];

    /** @var list<string> */
    private readonly array $lines;

    private int $at = 0;

    /** What is left of the current line, read in its place. */
    private ?string $rest = null;

    private readonly Containers $containers;

    /**
     * @param list<string> $lines
     */
    public function __construct(
        public readonly Spans $spans,
        private readonly Syntax $syntax,
        public readonly InlineParser $inline,
        array $lines,
    ) {
        $this->containers = new Containers($this);
        $this->lines = $this->containers->split($lines);
    }

    /**
     * @return list<Block>
     */
    public function document(): array
    {
        return $this->parse(0, []);
    }

    public function context(): Context
    {
        return $this->spans->context;
    }

    public function containers(): Containers
    {
        return $this->containers;
    }

    /**
     * The current line; `null` past the end.
     */
    public function line(): ?string
    {
        return $this->rest ?? $this->lines[$this->at] ?? null;
    }

    public function advance(int $count = 1): void
    {
        $this->at += $count;
        $this->rest = null;
    }

    /**
     * Makes what is left of the current line the line read next.
     */
    public function replace(string $line): void
    {
        $this->rest = $line;
    }

    /**
     * Inline text read as HTML, without the white space at its edges.
     */
    public function inline(string $text): Inline
    {
        return Inline::html(trim($this->spans->parse($text), " \t\n"));
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<string, mixed>    $tunes
     */
    public function block(string $type, array $data, array $tunes = []): Block
    {
        return $this->spans->context->block($type, $data, $tunes);
    }

    /**
     * The blocks from the current line up to the end, or to a line whose tag is one of the stops, left unread for the
     * container that waits for it.
     *
     * @param list<string> $stops
     *
     * @return list<Block>
     */
    public function parse(int $depth, array $stops): array
    {
        $blocks = [];
        $paragraph = [];

        for ($line = $this->line(); null !== $line; $line = $this->line()) {
            $blank = '' === trim($line);
            $found = $blank ? null : $this->containers->opening($line);

            if (null !== $found && \in_array($found[0]->key(), $stops, true)) {
                break;
            }

            $made = match (true) {
                $blank => [],
                null !== $found => $this->containers->block($found[0], $found[1], $depth, $stops),
                default => $this->syntax->block($this, $depth),
            };

            if (null === $made) {
                $paragraph[] = $line;
                $this->advance();

                continue;
            }

            if ($blank) {
                $this->advance();
            }

            foreach ([...$this->paragraph($paragraph), ...$made] as $block) {
                $blocks[] = $block;
            }

            $paragraph = [];
        }

        foreach ($this->paragraph($paragraph) as $block) {
            $blocks[] = $block;
        }

        return $blocks;
    }

    /**
     * @param list<string> $lines
     *
     * @return list<Block>
     */
    private function paragraph(array $lines): array
    {
        $text = $this->inline(implode("\n", $lines));

        return '' === $text->pieces[0][1] ? [] : [$this->block('paragraph', ['text' => $text])];
    }
}
