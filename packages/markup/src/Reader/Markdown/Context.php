<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Markdown;

use Phil\Markup\Lexer\Slugger;
use Phil\Markup\Reader\Loss;
use Phil\Markup\Reader\LossReason;
use Phil\Markup\Reader\Markdown\Contract\MarkdownDialect;
use Phil\Ujo\Block;

/**
 * What one reading of a source knows besides the line it is at: the dialect, whether raw HTML is kept, the link
 * references and footnotes defined anywhere in the source, what the dialect keeps, the images met in the text, the
 * anchors taken, the identifiers given to blocks and what the reading did not keep. A rule of a dialect makes its
 * blocks by `block()`, and keeps what it remembers of the source in `$extra`.
 */
final class Context
{
    /** The most losses one reading records. */
    public const int MAX_LOSSES = 1000;

    /** @var array<string, string> the address of each reference, by label in lower case */
    public array $references = [];

    /** @var array<string, string> the title of each reference that has one, its escapes and entities read, by label in lower case */
    public array $titles = [];

    /** @var array<string, Inline> the text of each footnote, by label */
    public array $footnotes = [];

    /** @var array<string, int> the number of each footnote referenced, in the order of the first reference */
    public array $numbers = [];

    /** @var array<string, array{string, string, string, string}> the address, alternative text, attributes and caption of each image met, by its mark */
    public array $images = [];

    /** @var array<string, mixed> what the dialect keeps during one reading, by keys of its own */
    public array $extra = [];

    /** @var list<Loss> what the reading did not keep, up to `MAX_LOSSES` */
    public array $losses = [];

    public readonly Slugger $anchors;

    private int $identifiers = 0;

    public function __construct(
        public readonly bool $rawHtml,
        public readonly ?MarkdownDialect $dialect = null,
    ) {
        $this->anchors = new Slugger();
    }

    /**
     * Records something the reading did not keep; past `MAX_LOSSES`, nothing more is recorded.
     */
    public function lose(string $element, string $path, LossReason $reason): void
    {
        if (\count($this->losses) < self::MAX_LOSSES) {
            $this->losses[] = new Loss($element, $path, $reason);
        }
    }

    /**
     * A new block, its identifier the next of the document.
     *
     * @param array<array-key, mixed> $data
     * @param array<string, mixed>    $tunes
     */
    public function block(string $type, array $data, array $tunes = []): Block
    {
        return new Block('b'.++$this->identifiers, $type, $data, $tunes);
    }

    /**
     * Whether the dialect turns off a syntax of plain Markdown.
     */
    public function disables(MarkdownSyntax $syntax): bool
    {
        return \in_array($syntax, $this->dialect?->disables() ?? [], true);
    }

    /**
     * The number of a footnote, given at its first reference; `null` when no footnote has that label.
     */
    public function footnote(string $label): ?int
    {
        if (!isset($this->footnotes[$label])) {
            return null;
        }

        return $this->numbers[$label] ??= \count($this->numbers) + 1;
    }

    /**
     * A label as references are matched: in lower case, its runs of white space one space.
     */
    public static function label(string $label): string
    {
        return mb_strtolower(implode(' ', array_filter(explode(' ', strtr($label, "\t\n", '  ')), static fn (string $word): bool => '' !== $word)));
    }
}
