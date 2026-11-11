<?php

declare(strict_types=1);

namespace Phil\Ujo\Schema;

use Phil\Ujo\Block;
use Phil\Ujo\Exception\InvalidDocument;
use Phil\Ujo\Html\InlineSanitizer;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Validation;

/**
 * The state of one normalization: the identifiers already met, which must be unique in the document, the depth of
 * nesting reached, and the blocks counted, in all and by type. The fields reach the sanitizer, the length limits of
 * HTML, the nested blocks and the level of validation through it.
 */
final class Context
{
    /** @var array<string, string> each identifier, to the path of its block */
    private array $ids = [];

    private int $depth = 0;

    private int $blocks = 0;

    /** @var array<string, int> each type, to the number of its blocks met */
    private array $types = [];

    public function __construct(
        private readonly Normalizer $normalizer,
        private readonly InlineSanitizer $sanitizer,
        private readonly int $maxDepth,
        private readonly int $maxBlocks = Normalizer::DEFAULT_MAX_BLOCKS,
        private readonly int $maxHtmlLength = Normalizer::DEFAULT_MAX_HTML_LENGTH,
        private readonly int $maxRawLength = Normalizer::DEFAULT_MAX_RAW_LENGTH,
        private readonly Validation $level = Validation::Shape,
    ) {
    }

    /**
     * How much of the document is checked.
     */
    public function level(): Validation
    {
        return $this->level;
    }

    /**
     * The longest HTML a field of the profile may hold, in characters.
     */
    public function maxHtmlLength(string $profile): int
    {
        return 'raw' === $profile ? $this->maxRawLength : $this->maxHtmlLength;
    }

    /**
     * Counts a block of a type, refusing one past the limit of the document or of its type.
     *
     * @throws InvalidDocument
     */
    public function count(string $type, ?int $maxPerDocument, string $path): void
    {
        if (++$this->blocks > $this->maxBlocks) {
            throw InvalidDocument::at($path, \sprintf('the document holds more blocks than the %d it may', $this->maxBlocks));
        }

        $this->types[$type] = ($this->types[$type] ?? 0) + 1;

        if (null !== $maxPerDocument && $this->types[$type] > $maxPerDocument) {
            throw InvalidDocument::at($path, \sprintf('the document holds more "%s" blocks than the %d it may', $type, $maxPerDocument));
        }
    }

    public function sanitize(string $html, string $profile): string
    {
        return $this->sanitizer->sanitize($html, $profile);
    }

    /**
     * A list of blocks nested in a block's data, normalized one level deeper.
     *
     * @return list<Block>
     *
     * @throws InvalidDocument
     */
    public function blocks(mixed $blocks, string $path): array
    {
        if (!\is_array($blocks) || !array_is_list($blocks)) {
            throw InvalidDocument::at($path, 'a list of blocks is expected');
        }

        if ($this->depth >= $this->maxDepth) {
            throw InvalidDocument::at($path, \sprintf('blocks are nested deeper than %d levels', $this->maxDepth));
        }

        ++$this->depth;

        try {
            return $this->normalizer->blocks($blocks, $path, $this);
        } finally {
            --$this->depth;
        }
    }

    /**
     * Records an identifier, refusing one already met in the document.
     *
     * @throws InvalidDocument
     */
    public function claim(string $id, string $path): void
    {
        if (isset($this->ids[$id])) {
            throw InvalidDocument::at($path.'.id', \sprintf('the identifier "%s" is already used by the block at %s', $id, $this->ids[$id]));
        }

        $this->ids[$id] = $path;
    }
}
