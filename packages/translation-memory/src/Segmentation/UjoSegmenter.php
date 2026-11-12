<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Segmentation;

use Phil\TranslationMemory\Fingerprint;
use Phil\TranslationMemory\Segment;
use Phil\TranslationMemory\SegmentState;
use Phil\TranslationMemory\SourceSegment;
use Phil\TranslationMemory\Text;
use Phil\Ujo\Block;
use Phil\Ujo\Document;

/**
 * Cuts a UJO document into segments, and puts translated segments back into it.
 *
 * Each text field of each block, nested blocks included, in reading order, is one segment — or, by sentence, one per
 * sentence. A key names where the text is: the block's identifier and the path of the field in its data,
 * `b3/text`, `b7/items.0.content`, `b9/content.1.2`, and `#n` for the n-th sentence of the field, `b3/text#2`. Keys
 * stay the same as long as the blocks keep their identifiers. Fields without words — empty, white space, tags only —
 * make no segment.
 */
final readonly class UjoSegmenter
{
    private const string KEY = '/^[A-Za-z0-9_]{1,64}$/D';

    private const int DEPTH = 16;

    private TranslatableFields $fields;

    private SentenceSplitter $splitter;

    public function __construct(
        public Granularity $granularity = Granularity::Block,
        ?TranslatableFields $fields = null,
        ?SentenceSplitter $splitter = null,
    ) {
        $this->fields = $fields ?? new TranslatableFields();
        $this->splitter = $splitter ?? new SentenceSplitter();
    }

    /**
     * The segments of a document, in reading order.
     *
     * @return list<SourceSegment>
     */
    public function segment(Document $document): array
    {
        $segments = [];

        foreach ($document->walk() as $block) {
            if (!$this->fields->isTranslated($block->type)) {
                continue;
            }

            foreach ($this->texts($block->data, [], null) as $path => $text) {
                foreach ($this->pieces($block->id.'/'.$path, $text) as [$key, $piece]) {
                    if (null !== $key) {
                        $segments[] = new SourceSegment($key, trim($piece));
                    }
                }
            }
        }

        return $segments;
    }

    /**
     * The document translated: each piece of text replaced by the target of its segment when the segment is in one of
     * the accepted states and was made from that very text — same fingerprint —; every other piece kept in the
     * source language. The white space around each sentence is kept.
     *
     * @param iterable<Segment>  $segments
     * @param list<SegmentState> $states   the states whose targets are used
     */
    public function assemble(Document $document, iterable $segments, array $states = [SegmentState::Translated, SegmentState::Reviewed]): Document
    {
        $targets = [];

        foreach ($segments as $segment) {
            if (null !== $segment->target && \in_array($segment->state, $states, true)) {
                $targets[$segment->key] = $segment;
            }
        }

        return $document->withBlocks(array_map(fn (Block $block): Block => $this->translated($block, $targets), $document->blocks));
    }

    /**
     * @param array<string, Segment> $targets
     */
    private function translated(Block $block, array $targets): Block
    {
        return $block->withData($this->translatedData($block, $block->data, [], null, $targets));
    }

    /**
     * @param array<array-key, mixed> $data
     * @param list<int|string>        $path
     * @param array<string, Segment>  $targets
     *
     * @return array<array-key, mixed>
     */
    private function translatedData(Block $block, array $data, array $path, ?string $name, array $targets, bool $valid = true): array
    {
        foreach ($data as $key => $value) {
            $named = \is_int($key) ? $name : $key;
            $at = [...$path, $key];
            $readable = $valid && $this->isKey((string) $key) && \count($at) <= self::DEPTH;

            if ($value instanceof Block) {
                $data[$key] = $this->translated($value, $targets);
            } elseif (\is_array($value)) {
                $data[$key] = $this->translatedData($block, $value, $at, $named, $targets, $readable);
            } elseif (\is_string($value) && $readable && $this->fields->isTranslated($block->type) && $this->fields->holdsWords($named)) {
                $data[$key] = $this->translatedText($block->id.'/'.implode('.', $at), $value, $targets);
            }
        }

        return $data;
    }

    /**
     * @param array<string, Segment> $targets
     */
    private function translatedText(string $key, string $text, array $targets): string
    {
        $translated = '';

        foreach ($this->pieces($key, $text) as [$pieceKey, $piece]) {
            $core = trim($piece);
            $segment = null === $pieceKey ? null : $targets[$pieceKey] ?? null;

            if (!$segment instanceof Segment || null === $segment->target || !Fingerprint::of($core)->equals($segment->fingerprint)) {
                $translated .= $piece;

                continue;
            }

            $start = \strlen($piece) - \strlen(ltrim($piece));
            $translated .= substr($piece, 0, $start).$segment->target.substr($piece, $start + \strlen($core));
        }

        return $translated;
    }

    /**
     * The pieces of a text, the whole text or each sentence, each with its key; a piece without words has none.
     *
     * @return list<array{?string, string}>
     */
    private function pieces(string $key, string $text): array
    {
        $block = Granularity::Block === $this->granularity;
        $number = 0;
        $pieces = [];

        foreach ($block ? [$text] : $this->splitter->split($text) as $piece) {
            $pieces[] = [Text::hasWords($piece) ? ($block ? $key : $key.'#'.++$number) : null, $piece];
        }

        return $pieces;
    }

    /**
     * The texts of a block's data by path, nested blocks left to their own turn.
     *
     * @param array<array-key, mixed> $data
     * @param list<int|string>        $path
     *
     * @return iterable<string, string>
     */
    private function texts(array $data, array $path, ?string $name): iterable
    {
        foreach ($data as $key => $value) {
            $named = \is_int($key) ? $name : $key;
            $at = [...$path, $key];

            if (!$this->isKey((string) $key)) {
                continue;
            }

            if (\is_string($value) && $this->fields->holdsWords($named)) {
                yield implode('.', $at) => $value;
            } elseif (\is_array($value) && \count($at) < self::DEPTH) {
                yield from $this->texts($value, $at, $named);
            }
        }
    }

    private function isKey(string $key): bool
    {
        return 1 === preg_match(self::KEY, $key);
    }
}
