<?php

declare(strict_types=1);

namespace Phil\Ujo;

use Generator;
use Phil\Ujo\Exception\InvalidDocument;

/**
 * One block of a UJO document: an identifier unique in the document, a type, the data of that type
 * and, optionally, the data of the tunes applied to it. Nested blocks — the content of a column or a toggle — are
 * `Block` objects inside `data`.
 *
 * A block is a value: its `with*()` methods return a copy. The constructor checks nothing; `Block::fromArray()` checks
 * the structure, and `Normalizer` the data of each type.
 */
final readonly class Block
{
    private const string ID = '/^[A-Za-z0-9_-]{1,64}$/D';

    private const string TYPE = '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/D';

    /**
     * @param array<array-key, mixed> $data
     * @param array<string, mixed>    $tunes
     */
    public function __construct(
        public string $id,
        public string $type,
        public array $data = [],
        public array $tunes = [],
    ) {
    }

    /**
     * A block read from its JSON form, `{"id", "type", "data", "tunes"}`, with its structure checked: an identifier of
     * 1 to 64 letters, digits, `_` or `-`; a type starting with a letter; `data` an object; `tunes`, when present, an
     * object; no other key.
     *
     * @throws InvalidDocument
     */
    public static function fromArray(mixed $block, string $path): self
    {
        if (!\is_array($block)) {
            throw InvalidDocument::at($path, 'a block must be an object');
        }

        foreach (array_keys($block) as $key) {
            if (!\in_array($key, ['id', 'type', 'data', 'tunes'], true)) {
                throw InvalidDocument::at($path.'.'.$key, 'a block holds only "id", "type", "data" and "tunes"');
            }
        }

        $id = $block['id'] ?? null;
        $type = $block['type'] ?? null;
        $data = $block['data'] ?? null;
        $tunes = $block['tunes'] ?? [];

        if (!\is_string($id) || 1 !== preg_match(self::ID, $id)) {
            throw InvalidDocument::at($path.'.id', 'the identifier must be 1 to 64 letters, digits, "_" or "-"');
        }

        if (!\is_string($type) || 1 !== preg_match(self::TYPE, $type)) {
            throw InvalidDocument::at($path.'.type', 'the type must be a name starting with a letter');
        }

        if (!\is_array($data)) {
            throw InvalidDocument::at($path.'.data', 'the data must be an object');
        }

        if (!\is_array($tunes)) {
            throw InvalidDocument::at($path.'.tunes', 'the tunes must be an object');
        }

        $named = [];

        foreach ($tunes as $name => $tune) {
            if (!\is_string($name)) {
                throw InvalidDocument::at($path.'.tunes', 'the tunes must be an object');
            }

            $named[$name] = $tune;
        }

        return new self($id, $type, $data, $named);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function withData(array $data): self
    {
        return new self($this->id, $this->type, $data, $this->tunes);
    }

    /**
     * @param array<string, mixed> $tunes
     */
    public function withTunes(array $tunes): self
    {
        return new self($this->id, $this->type, $this->data, $tunes);
    }

    public function withType(string $type): self
    {
        return new self($this->id, $type, $this->data, $this->tunes);
    }

    /**
     * The data of one tune, `null` when the block does not have it.
     */
    public function tune(string $name): mixed
    {
        return $this->tunes[$name] ?? null;
    }

    /**
     * The blocks nested directly in this one, in the order of its data: the content of each column, of a toggle, of
     * a note. Blocks nested in those are theirs.
     *
     * @return list<Block>
     */
    public function children(): array
    {
        return iterator_to_array(self::find($this->data), false);
    }

    /**
     * The JSON form of the block: `tunes` is left out when the block has none, and nested blocks are written in their
     * own JSON form.
     *
     * @return array{id: string, type: string, data: array<array-key, mixed>, tunes?: array<string, mixed>}
     */
    public function toArray(): array
    {
        /** @var array<array-key, mixed> $data */
        $data = self::export($this->data);
        $block = ['id' => $this->id, 'type' => $this->type, 'data' => $data];

        if ([] !== $this->tunes) {
            $block['tunes'] = $this->tunes;
        }

        return $block;
    }

    private static function export(mixed $value): mixed
    {
        if ($value instanceof self) {
            return $value->toArray();
        }

        return \is_array($value) ? array_map(self::export(...), $value) : $value;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return Generator<int, Block>
     */
    private static function find(array $data): Generator
    {
        foreach ($data as $value) {
            if ($value instanceof self) {
                yield $value;
            } elseif (\is_array($value)) {
                yield from self::find($value);
            }
        }
    }
}
