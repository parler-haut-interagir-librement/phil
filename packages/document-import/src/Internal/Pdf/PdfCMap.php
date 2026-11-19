<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Pdf;

use Phil\DocumentImport\Internal\Codepage;

/**
 * A `ToUnicode` CMap: the code space that says how many bytes each code takes, and the text of each code, from its
 * `bfchar` entries and its `bfrange` ranges — kept as ranges, never expanded.
 *
 * @internal
 */
final class PdfCMap
{
    /** How many entries a CMap may hold, against a CMap that never ends. */
    private const int ENTRIES = 65_536;

    /** @var array<int, list<array{int, int}>> the low and high code of each code space range, by its length in bytes */
    private array $spaces = [];

    /** @var array<string, string> */
    private array $chars = [];

    /** @var list<array{int, int, int, string|list<string>}> length, first and last code, and the text of the first code or of each code */
    private array $ranges = [];

    public function __construct(string $cmap)
    {
        preg_match_all('/begincodespacerange(.*?)endcodespacerange/s', $cmap, $blocks);

        foreach ($blocks[1] as $block) {
            preg_match_all('/<([0-9a-fA-F]+)>\s*<([0-9a-fA-F]+)>/', $block, $pairs, \PREG_SET_ORDER);

            foreach ($pairs as [, $low, $high]) {
                $this->spaces[intdiv(\strlen($low) + 1, 2)][] = [(int) hexdec($low), (int) hexdec($high)];
            }
        }

        preg_match_all('/beginbfchar(.*?)endbfchar/s', $cmap, $blocks);

        foreach ($blocks[1] as $block) {
            preg_match_all('/<([0-9a-fA-F]+)>\s*<([0-9a-fA-F]*)>/', $block, $pairs, \PREG_SET_ORDER);

            foreach (\array_slice($pairs, 0, self::ENTRIES) as [, $code, $text]) {
                $this->chars[strtolower($code)] = $this->utf16($text);
            }
        }

        preg_match_all('/beginbfrange(.*?)endbfrange/s', $cmap, $blocks);

        foreach ($blocks[1] as $block) {
            preg_match_all('/<([0-9a-fA-F]+)>\s*<([0-9a-fA-F]+)>\s*(<[0-9a-fA-F]*>|\[[^\]]*\])/', $block, $ranges, \PREG_SET_ORDER);

            foreach (\array_slice($ranges, 0, self::ENTRIES) as [, $low, $high, $target]) {
                $this->range($low, $high, $target);
            }
        }
    }

    /**
     * The byte lengths of the codes this CMap's code space allows, shortest first; empty when it declares none.
     *
     * @return list<int>
     */
    public function lengths(): array
    {
        $lengths = array_keys($this->spaces);
        sort($lengths);

        return $lengths;
    }

    /**
     * Whether the bytes are a code of the code space.
     */
    public function isCode(string $bytes): bool
    {
        $value = (int) hexdec(sodium_bin2hex($bytes));

        return array_any($this->spaces[\strlen($bytes)] ?? [], static fn (array $range): bool => $value >= $range[0] && $value <= $range[1]);
    }

    /**
     * The text of a code, `null` when the CMap does not map it.
     */
    public function text(string $bytes): ?string
    {
        $hex = sodium_bin2hex($bytes);

        if (isset($this->chars[$hex])) {
            return $this->chars[$hex];
        }

        $value = (int) hexdec($hex);

        foreach ($this->ranges as [$length, $low, $high, $target]) {
            if ($length === \strlen($bytes) && $value >= $low && $value <= $high) {
                return \is_array($target) ? $target[$value - $low] ?? null : $this->shifted($target, $value - $low);
            }
        }

        return null;
    }

    private function range(string $low, string $high, string $target): void
    {
        $first = (int) hexdec($low);
        $last = (int) hexdec($high);

        if ($last >= $first) {
            $this->ranges[] = [intdiv(\strlen($low) + 1, 2), $first, $last, $this->target($target)];
        }
    }

    /**
     * The text of the first code of a range, or the text of each of its codes.
     *
     * @return string|list<string>
     */
    private function target(string $target): string|array
    {
        if (!str_starts_with($target, '[')) {
            return $this->utf16(trim($target, '<>'));
        }

        preg_match_all('/<([0-9a-fA-F]*)>/', $target, $texts);

        return array_map($this->utf16(...), \array_slice($texts[1], 0, self::ENTRIES));
    }

    /**
     * The text of the first code of a range with its last character moved forward by the position of a code.
     */
    private function shifted(string $text, int $offset): string
    {
        if ('' === $text) {
            return '';
        }

        $characters = mb_str_split($text);
        $last = array_pop($characters);

        return implode('', $characters).Codepage::character(mb_ord($last) + $offset);
    }

    private function utf16(string $hex): string
    {
        $bytes = sodium_hex2bin(1 === \strlen($hex) % 2 ? $hex.'0' : $hex);
        $text = mb_convert_encoding($bytes, 'UTF-8', 'UTF-16BE');

        return str_replace("\0", '', $text);
    }
}
