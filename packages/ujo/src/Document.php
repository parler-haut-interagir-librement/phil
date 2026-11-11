<?php

declare(strict_types=1);

namespace Phil\Ujo;

use Generator;
use Phil\Ujo\Exception\InvalidDocument;

/**
 * A UJO (Universal JSON Output) document — the time it was saved, in milliseconds, its ordered blocks and the
 * version of the editor that saved it.
 *
 * A document is a value. `Document::fromArray()` checks its structure only; `Normalizer` checks the data of each block
 * against its type, which `Ujo::decode()` does in one call.
 */
final readonly class Document
{
    /**
     * @param list<Block> $blocks
     */
    public function __construct(
        public array $blocks = [],
        public ?int $time = null,
        public ?string $version = null,
    ) {
    }

    /**
     * A document read from its JSON form: `blocks`, a list, required; `time`, a positive integer, and `version`, a
     * text, optional; no other key.
     *
     * @param array<array-key, mixed> $document
     *
     * @throws InvalidDocument
     */
    public static function fromArray(array $document): self
    {
        foreach (array_keys($document) as $key) {
            if (!\in_array($key, ['time', 'blocks', 'version'], true)) {
                throw InvalidDocument::at((string) $key, 'a document holds only "time", "blocks" and "version"');
            }
        }

        $time = $document['time'] ?? null;
        $version = $document['version'] ?? null;
        $blocks = $document['blocks'] ?? null;

        if (null !== $time && (!\is_int($time) || $time < 0)) {
            throw InvalidDocument::at('time', 'the time must be a positive integer of milliseconds');
        }

        if (null !== $version && !\is_string($version)) {
            throw InvalidDocument::at('version', 'the version must be a text');
        }

        if (!\is_array($blocks) || !array_is_list($blocks)) {
            throw InvalidDocument::at('blocks', 'the blocks must be a list');
        }

        $read = [];

        foreach ($blocks as $index => $block) {
            $read[] = Block::fromArray($block, 'blocks['.$index.']');
        }

        return new self($read, $time, $version);
    }

    /**
     * @param list<Block> $blocks
     */
    public function withBlocks(array $blocks): self
    {
        return new self($blocks, $this->time, $this->version);
    }

    public function isEmpty(): bool
    {
        return [] === $this->blocks;
    }

    /**
     * Every block of the document, nested ones included, depth first: a block comes before the blocks it holds.
     *
     * @return Generator<int, Block>
     */
    public function walk(): Generator
    {
        yield from self::descend($this->blocks);
    }

    /**
     * The block of an identifier, wherever it is nested; `null` when there is none.
     */
    public function find(string $id): ?Block
    {
        foreach ($this->walk() as $block) {
            if ($block->id === $id) {
                return $block;
            }
        }

        return null;
    }

    /**
     * The JSON form of the document, in its canonical order; `time` and `version` are left out when unknown.
     *
     * @return array{time?: int, blocks: list<array<string, mixed>>, version?: string}
     */
    public function toArray(): array
    {
        $document = [];

        if (null !== $this->time) {
            $document['time'] = $this->time;
        }

        $document['blocks'] = array_map(static fn (Block $block): array => $block->toArray(), $this->blocks);

        if (null !== $this->version) {
            $document['version'] = $this->version;
        }

        return $document;
    }

    /**
     * @param list<Block> $blocks
     *
     * @return Generator<int, Block>
     */
    private static function descend(array $blocks): Generator
    {
        foreach ($blocks as $block) {
            yield $block;
            yield from self::descend($block->children());
        }
    }
}
