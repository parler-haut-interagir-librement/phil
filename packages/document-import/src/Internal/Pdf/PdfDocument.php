<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Pdf;

use Phil\DocumentImport\Exception\InvalidDocument;
use Phil\DocumentImport\Exception\LimitExceeded;
use Phil\DocumentImport\Exception\UnsupportedFormat;
use Phil\DocumentImport\Limits;

/**
 * The objects of a PDF file: found through its cross-reference tables and streams, newest first, or — when they are
 * missing or damaged — by scanning the file for its objects; objects kept in object streams read from them; each
 * object parsed once, when asked. An encrypted file is refused.
 *
 * @internal
 */
final class PdfDocument
{
    /** How many cross-reference sections are followed through their `Prev`, against a chain that loops. */
    private const int CHAIN = 32;

    public PdfDictionary $trailer;

    public readonly PdfFilters $filters;

    /** @var array<int, int> */
    private array $offsets = [];

    /** @var array<int, array{int, int}> the object stream and the index of each compressed object */
    private array $compressed = [];

    /** @var array<int, mixed> */
    private array $objects = [];

    /** @var array<int, array<int, mixed>> */
    private array $streams = [];

    /** @var list<int> the objects being parsed, against an object whose parsing asks for itself */
    private array $resolving = [];

    /**
     * @throws InvalidDocument
     * @throws LimitExceeded
     * @throws UnsupportedFormat
     */
    public function __construct(private readonly string $bytes, private readonly Limits $limits)
    {
        if (!str_contains(substr($bytes, 0, 1024), '%PDF-')) {
            throw InvalidDocument::because('The file is not a PDF document: it does not start with "%PDF-".');
        }

        $this->filters = new PdfFilters($limits->entryBytes, $limits->totalBytes);
        $this->trailer = new PdfDictionary();
        $this->crossReferences();

        if (!$this->dictionary($this->trailer->get('Root'))->get('Pages') instanceof PdfReference && !$this->dictionary($this->trailer->get('Root'))->get('Pages') instanceof PdfDictionary) {
            $this->scan();
        }

        if (null !== $this->trailer->get('Encrypt')) {
            throw UnsupportedFormat::because('The PDF is encrypted, which this library does not read: save it again without password nor restrictions.');
        }
    }

    /**
     * A value with its reference followed: an indirect object is never itself a reference, the parser reading one
     * only inside an array or a dictionary.
     *
     * @throws LimitExceeded
     */
    public function resolve(mixed $value): mixed
    {
        return $value instanceof PdfReference ? $this->object($value->number) : $value;
    }

    /**
     * The dictionary a value is, or leads to — a stream's dictionary for a stream —, empty otherwise.
     *
     * @throws LimitExceeded
     */
    public function dictionary(mixed $value): PdfDictionary
    {
        $value = $this->resolve($value);

        return match (true) {
            $value instanceof PdfDictionary => $value,
            $value instanceof PdfStream => $value->dictionary,
            default => new PdfDictionary(),
        };
    }

    /**
     * The decoded bytes of a stream and the filter that stopped the decoding, if any.
     *
     * @return array{string, ?string}
     *
     * @throws LimitExceeded
     */
    public function decode(PdfStream $stream): array
    {
        $filters = $this->resolve($stream->dictionary->get('Filter'));
        $parameters = $this->resolve($stream->dictionary->get('DecodeParms') ?? $stream->dictionary->get('DP'));

        return $this->filters->decode(
            $stream->raw,
            \is_array($filters) ? array_map($this->resolve(...), $filters) : $filters,
            \is_array($parameters) ? array_map($this->resolve(...), $parameters) : $parameters,
        );
    }

    /**
     * @throws LimitExceeded
     */
    public function object(int $number): mixed
    {
        if (\array_key_exists($number, $this->objects) || \in_array($number, $this->resolving, true)) {
            return $this->objects[$number] ?? null;
        }

        $this->resolving[] = $number;

        try {
            $object = match (true) {
                isset($this->offsets[$number]) => $this->parseAt($this->offsets[$number]),
                isset($this->compressed[$number]) => $this->fromStream(...$this->compressed[$number]),
                default => null,
            };
        } finally {
            array_pop($this->resolving);
        }

        return $this->objects[$number] = $object;
    }

    /**
     * The cross-reference sections from the last `startxref` back through their `Prev`, each entry kept from the
     * newest section that has it.
     *
     * @throws LimitExceeded
     */
    private function crossReferences(): void
    {
        $at = strrpos($this->bytes, 'startxref');
        $offset = false === $at || 1 !== preg_match('/\G\s*\K\d+/', $this->bytes, $match, 0, $at + 9) ? null : (int) $match[0];
        $seen = [];

        while (null !== $offset && !\in_array($offset, $seen, true) && isset($this->bytes[$offset]) && \count($seen) < self::CHAIN) {
            $seen[] = $offset;
            $section = 'xref' === substr($this->bytes, $offset, 4) ? $this->table($offset) : $this->stream($offset);

            if (!$section instanceof PdfDictionary) {
                return;
            }

            foreach ($section->entries as $key => $value) {
                if (!isset($this->trailer->entries[$key])) {
                    $this->trailer = new PdfDictionary([...$this->trailer->entries, $key => $value]);
                }
            }

            $hybrid = $section->get('XRefStm');

            if (\is_int($hybrid)) {
                $this->stream($hybrid);
            }

            $previous = $section->get('Prev');
            $offset = \is_int($previous) ? $previous : null;
        }
    }

    /**
     * A cross-reference table and its trailer.
     *
     * @throws LimitExceeded
     */
    private function table(int $offset): ?PdfDictionary
    {
        $end = strpos($this->bytes, 'trailer', $offset);
        $trailer = false === $end ? null : new PdfParser($this->bytes, $end + 7, $this->limits->depth)->value();

        if (!$trailer instanceof PdfDictionary) {
            return null;
        }

        preg_match_all('/(\d+)\s+(\d+)\s*[\r\n]+((?:\d{10}\s\d{5}\s[nf]\s*)*)/', substr($this->bytes, $offset + 4, $end - $offset - 4), $sections, \PREG_SET_ORDER);

        foreach ($sections as [, $start, , $entries]) {
            preg_match_all('/(\d{10})\s(\d{5})\s([nf])/', $entries, $rows, \PREG_SET_ORDER);

            foreach ($rows as $index => [, $position, , $kind]) {
                $this->register((int) $start + $index, 'n' === $kind ? (int) $position : null);
            }
        }

        return $trailer;
    }

    /**
     * A cross-reference stream: its entries, and its dictionary as the trailer.
     *
     * @throws LimitExceeded
     */
    private function stream(int $offset): ?PdfDictionary
    {
        $stream = $this->parseAt($offset);

        if (!$stream instanceof PdfStream || 'XRef' !== $stream->dictionary->name('Type')) {
            return null;
        }

        [$typeWidth, $valueWidth, $indexWidth] = $this->widths($stream->dictionary->get('W'));
        $size = $typeWidth + $valueWidth + $indexWidth;
        $index = $stream->dictionary->get('Index');
        $index = \is_array($index) ? $index : [0, $stream->dictionary->get('Size')];
        [$data] = $this->decode($stream);
        $at = 0;

        for ($pair = 0; $pair + 1 < \count($index); $pair += 2) {
            [$start, $count] = [$index[$pair], $index[$pair + 1]];

            if (!\is_int($start) || !\is_int($count)) {
                continue;
            }

            $count = min($count, $this->limits->objects);

            for ($entry = 0; $entry < $count && $size > 0 && $at + $size <= \strlen($data); ++$entry, $at += $size) {
                $type = $this->field($data, $at, $typeWidth, 1);
                $value = $this->field($data, $at + $typeWidth, $valueWidth, 0);
                $position = $this->field($data, $at + $typeWidth + $valueWidth, $indexWidth, 0);
                $this->entry($start + $entry, [$type, $value, $position]);
            }
        }

        return $stream->dictionary;
    }

    /**
     * The widths of the three fields of a cross-reference stream's rows: those it declares, three whole numbers of no
     * more than eight bytes, or one, two and one byte.
     *
     * @return array{int, int, int}
     */
    private function widths(mixed $declared): array
    {
        if (!\is_array($declared) || 3 !== \count($declared)) {
            return [1, 2, 1];
        }

        $widths = [];

        foreach ($declared as $width) {
            if (!\is_int($width) || $width < 0 || $width > 8) {
                return [1, 2, 1];
            }

            $widths[] = $width;
        }

        return [$widths[0], $widths[1], $widths[2]];
    }

    /**
     * @param array{int, int, int} $fields
     *
     * @throws LimitExceeded
     */
    private function entry(int $number, array $fields): void
    {
        match ($fields[0]) {
            1 => $this->register($number, $fields[1]),
            2 => $this->known($number) ? null : $this->compress($number, $fields[1], $fields[2]),
            default => $this->register($number, null),
        };
    }

    /**
     * Whether a newer section gave the object already: at an offset, in an object stream, or free.
     */
    private function known(int $number): bool
    {
        return isset($this->offsets[$number]) || isset($this->compressed[$number]) || \array_key_exists($number, $this->objects);
    }

    /**
     * @throws LimitExceeded
     */
    private function compress(int $number, int $stream, int $index): void
    {
        $this->count();
        $this->compressed[$number] = [$stream, $index];
    }

    private function field(string $data, int $at, int $width, int $default): int
    {
        if (0 === $width) {
            return $default;
        }

        $value = 0;

        for ($byte = 0; $byte < $width; ++$byte) {
            $value = ($value << 8) | \ord($data[$at + $byte]);
        }

        return $value;
    }

    /**
     * An object of a section, unless a newer section gave it already; a free object is marked so that an older
     * section does not bring it back.
     *
     * @throws LimitExceeded
     */
    private function register(int $number, ?int $offset): void
    {
        if ($this->known($number)) {
            return;
        }

        $this->count();

        if (null === $offset) {
            $this->objects[$number] = null;
        } else {
            $this->offsets[$number] = $offset;
        }
    }

    /**
     * @throws LimitExceeded
     */
    private function count(): void
    {
        if (\count($this->offsets) + \count($this->compressed) >= $this->limits->objects) {
            throw LimitExceeded::because(\sprintf('The PDF declares more than %d objects.', $this->limits->objects));
        }
    }

    /**
     * The objects found by scanning the file, the last definition of each kept, those of its object streams added,
     * and the trailer found again: the last `trailer` dictionary, or the catalog.
     *
     * @throws LimitExceeded
     */
    private function scan(): void
    {
        $this->offsets = [];
        $this->compressed = [];
        $this->objects = [];
        preg_match_all('/(?<![0-9])\d{1,10}\s+\d{1,5}\s+obj\b/', $this->bytes, $found, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE);

        if (\count($found) > $this->limits->objects) {
            throw LimitExceeded::because(\sprintf('The PDF declares more than %d objects.', $this->limits->objects));
        }

        foreach ($found as $match) {
            $this->offsets[(int) $match[0][0]] = $match[0][1];
        }

        foreach (array_keys($this->offsets) as $number) {
            $object = $this->object($number);

            if ($object instanceof PdfStream && 'ObjStm' === $object->dictionary->name('Type')) {
                foreach (array_keys($this->streamObjects($number)) as $index => $inner) {
                    $this->compressed[$inner] ??= [$number, $index];
                }
            }

            if ('Catalog' === $this->dictionary($object)->name('Type') && null === $this->dictionary($this->trailer->get('Root'))->get('Pages')) {
                $this->trailer = new PdfDictionary([...$this->trailer->entries, 'Root' => new PdfReference($number)]);
            }
        }

        $at = strrpos($this->bytes, 'trailer');
        $trailer = false === $at ? null : new PdfParser($this->bytes, $at + 7, $this->limits->depth)->value();

        if ($trailer instanceof PdfDictionary) {
            $this->trailer = new PdfDictionary([...$this->trailer->entries, ...$trailer->entries]);
        }
    }

    /**
     * The object at an offset: `n g obj`, its value, and the bytes of its stream when it has one.
     *
     * @throws LimitExceeded
     */
    private function parseAt(int $offset): mixed
    {
        if (1 !== preg_match('/\G\s*\d+\s+\d+\s+obj\b/', $this->bytes, $match, 0, $offset)) {
            return null;
        }

        $parser = new PdfParser($this->bytes, $offset + \strlen($match[0]), $this->limits->depth);
        $value = $parser->value();
        $parser->skipSpace();

        if (!$value instanceof PdfDictionary || 'stream' !== substr($this->bytes, $parser->at, 6)) {
            return $value;
        }

        return new PdfStream($value, $this->streamData($value, $parser->at + 6));
    }

    /**
     * The bytes of a stream from the end of its `stream` keyword: as long as its length says when `endstream`
     * follows, up to `endstream` otherwise.
     *
     * @throws LimitExceeded
     */
    private function streamData(PdfDictionary $dictionary, int $at): string
    {
        $at += "\r\n" === substr($this->bytes, $at, 2) ? 2 : (str_contains("\r\n", $this->bytes[$at] ?? 'x') ? 1 : 0);
        $length = $this->resolve($dictionary->get('Length'));

        if (\is_int($length) && $length >= 0 && 1 === preg_match('/\G\s*endstream/', $this->bytes, $match, 0, $at + $length)) {
            return substr($this->bytes, $at, $length);
        }

        $end = strpos($this->bytes, 'endstream', $at);

        return rtrim(substr($this->bytes, $at, false === $end ? null : $end - $at), "\r\n");
    }

    /**
     * @throws LimitExceeded
     */
    private function fromStream(int $stream, int $index): mixed
    {
        return array_values($this->streamObjects($stream))[$index] ?? null;
    }

    /**
     * The objects an object stream holds, by number.
     *
     * @return array<int, mixed>
     *
     * @throws LimitExceeded
     */
    private function streamObjects(int $number): array
    {
        if (isset($this->streams[$number])) {
            return $this->streams[$number];
        }

        $this->streams[$number] = [];
        $stream = $this->object($number);

        if (!$stream instanceof PdfStream) {
            return [];
        }

        [$data] = $this->decode($stream);
        $count = $stream->dictionary->get('N');
        $first = $stream->dictionary->get('First');
        $header = new PdfParser($data, 0, $this->limits->depth);
        $objects = [];

        for ($index = 0; \is_int($count) && \is_int($first) && $index < min($count, $this->limits->objects); ++$index) {
            $inner = $header->value();
            $offset = $header->value();

            if (!\is_int($inner) || !\is_int($offset)) {
                break;
            }

            $objects[$inner] = new PdfParser($data, $first + $offset, $this->limits->depth)->value();
        }

        return $this->streams[$number] = $objects;
    }
}
