<?php

declare(strict_types=1);

namespace Phil\Stockroom;

use Phil\Stockroom\Contract\Items;
use Phil\Stockroom\Exception\InvalidStock;

/**
 * Reads a load list written as text, one line per item, and writes one back:
 *
 * ```text
 * # The tavern scene
 * 12 x foam-sword
 * 4 lantern @ crate-3
 * banner-red
 * ```
 *
 * A line is a quantity — one when it is left out —, an optional `x`, the code on the item's label, and optionally `@`
 * and the identifier of the container to take it from. Blank lines and lines starting with `#` are skipped. The text is
 * at most 64 KiB and holds at most as many lines as a load list.
 */
final readonly class LoadListReader
{
    /** The longest text read, in bytes. */
    public const int MAX_BYTES = 65_536;

    private const string LINE = '/^(?:(?<quantity>\d{1,7})\s*(?:[x×]\s+|×|\s+))?(?<code>[A-Za-z0-9][A-Za-z0-9._:-]{0,127})(?:\s*@\s*(?<from>[A-Za-z0-9][A-Za-z0-9._:-]{0,127}))?$/Du';

    public function __construct(
        private Items $items,
    ) {
    }

    /**
     * The lines of the text, each item named by its identifier.
     *
     * @return list<LoadLine>
     *
     * @throws InvalidStock when the text is too long, not UTF-8, or a line is not one or names an item that does not
     *                      exist; the message gives the number of the line
     */
    public function read(string $text): array
    {
        if (\strlen($text) > self::MAX_BYTES || !mb_check_encoding($text, 'UTF-8')) {
            throw InvalidStock::because(\sprintf('A load list is UTF-8 text of at most %d bytes.', self::MAX_BYTES));
        }

        $lines = [];

        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $text)) as $number => $raw) {
            $line = trim($raw);

            if ('' === $line || str_starts_with($line, '#')) {
                continue;
            }

            if (\count($lines) >= LoadList::MAX_LINES) {
                throw InvalidStock::because(\sprintf('A load list holds at most %d lines.', LoadList::MAX_LINES));
            }

            $lines[] = $this->line($line, $number + 1);
        }

        return $lines;
    }

    /**
     * The text of the lines, one per line, each item named by its code.
     *
     * @param iterable<LoadLine> $lines
     *
     * @throws InvalidStock when a line names an item that does not exist
     */
    public function write(iterable $lines): string
    {
        $text = '';

        foreach ($lines as $line) {
            $item = $this->items->get($line->item) ?? throw InvalidStock::because(\sprintf('There is no item "%s".', $line->item));
            $text .= $line->quantity.' x '.$item->code.(null === $line->from ? '' : ' @ '.$line->from)."\n";
        }

        return $text;
    }

    /**
     * @throws InvalidStock when the line is not one, or names an item that does not exist
     */
    private function line(string $line, int $number): LoadLine
    {
        if (1 !== preg_match(self::LINE, $line, $match)) {
            throw InvalidStock::because(\sprintf('Line %d is not "quantity x code @ container": "%s".', $number, mb_strimwidth($line, 0, 60, '…')));
        }

        $item = $this->items->byCode($match['code']) ?? throw InvalidStock::because(\sprintf('Line %d names the item "%s", which does not exist.', $number, $match['code']));
        $quantity = '' === $match['quantity'] ? 1 : (int) $match['quantity'];

        try {
            return new LoadLine($item->id, $quantity, '' === ($match['from'] ?? '') ? null : $match['from']);
        } catch (InvalidStock $invalidStock) {
            throw InvalidStock::because(\sprintf('Line %d: %s', $number, $invalidStock->getMessage()));
        }
    }
}
