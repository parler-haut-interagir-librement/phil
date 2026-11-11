<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Text;

use Override;
use Phil\Markup\Adapter\Autolink;
use Phil\Markup\Reader\MarkupReader;
use Phil\Markup\Reader\Reading;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Normalizer;

/**
 * Plain text into UJO: a `paragraph` block for each run of lines separated by a blank line, a line break for each other
 * new line, every character escaped and the web addresses made links — the paragraphs `PlainText` compiles, as blocks.
 */
final readonly class TextReader implements MarkupReader
{
    public function __construct(
        private Normalizer $normalizer = new Normalizer(),
    ) {
    }

    #[Override]
    public function read(string $source): Reading
    {
        $paragraphs = [[]];

        foreach (explode("\n", str_replace(["\r\n", "\r", "\0"], ["\n", "\n", "\u{FFFD}"], $source)) as $line) {
            if ('' !== trim($line)) {
                $paragraphs[\count($paragraphs) - 1][] = Autolink::html(trim($line));
            } elseif ([] !== $paragraphs[\count($paragraphs) - 1]) {
                $paragraphs[] = [];
            }
        }

        $blocks = [];

        foreach (array_filter($paragraphs) as $lines) {
            $blocks[] = new Block('b'.(\count($blocks) + 1), 'paragraph', ['text' => implode('<br>', $lines)]);
        }

        return new Reading($this->normalizer->normalize(new Document($blocks)));
    }
}
