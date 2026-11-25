<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Pdf;

use Phil\DocumentImport\Exception\LimitExceeded;

/**
 * The bytes of a stream with its filters undone: Flate with its PNG and TIFF predictors, LZW, ASCII hexadecimal,
 * ASCII base-85 and run-length. A stream decodes to no more than `$entryBytes`, and all of them together to no more
 * than `$totalBytes`; a filter it does not know, such as the image filters DCT and JPX, stops the decoding where it
 * stands, and malformed data decodes to nothing.
 *
 * @internal
 */
final class PdfFilters
{
    /** The filters of images, whose bytes are kept as they are. */
    public const array IMAGE = ['DCTDecode', 'JPXDecode', 'CCITTFaxDecode', 'JBIG2Decode'];

    private int $total = 0;

    public function __construct(
        private readonly int $entryBytes,
        private readonly int $totalBytes,
    ) {
    }

    /**
     * The decoded bytes of a stream and the filter that stopped the decoding, `null` when every filter was undone.
     *
     * @param list<mixed>|mixed $filters    a name or a list of names, references resolved
     * @param list<mixed>|mixed $parameters the decoding parameters of each filter
     *
     * @return array{string, ?string}
     *
     * @throws LimitExceeded
     */
    public function decode(string $bytes, mixed $filters, mixed $parameters): array
    {
        $filters = \is_array($filters) ? $filters : [$filters];
        $parameters = \is_array($parameters) ? $parameters : [$parameters];

        foreach ($filters as $index => $filter) {
            $name = $filter instanceof PdfName ? $filter->name : null;

            if (null === $name) {
                continue;
            }

            $decoded = match ($name) {
                'FlateDecode', 'Fl' => $this->inflate($bytes),
                'LZWDecode', 'LZW' => $this->lzw($bytes, $this->entryBytes),
                'ASCIIHexDecode', 'AHx' => $this->asciiHex($bytes),
                'ASCII85Decode', 'A85' => $this->ascii85($bytes),
                'RunLengthDecode', 'RL' => $this->runLength($bytes, $this->entryBytes),
                default => null,
            };

            if (null === $decoded) {
                return [$bytes, $name];
            }

            $options = $parameters[$index] ?? null;
            $bytes = \in_array($name, ['FlateDecode', 'Fl', 'LZWDecode', 'LZW'], true) && $options instanceof PdfDictionary ? $this->predicted($decoded, $options) : $decoded;
            $this->count(\strlen($bytes));
        }

        return [$bytes, null];
    }

    /**
     * @throws LimitExceeded
     */
    private function count(int $bytes): void
    {
        if ($bytes > $this->entryBytes) {
            throw LimitExceeded::because(\sprintf('A stream of the PDF is larger than %d bytes once decoded.', $this->entryBytes));
        }

        $this->total += $bytes;

        if ($this->total > $this->totalBytes) {
            throw LimitExceeded::because(\sprintf('The streams of the PDF are larger than %d bytes once decoded.', $this->totalBytes));
        }
    }

    private function inflate(string $bytes): string
    {
        set_error_handler(static fn (): bool => true);

        try {
            $inflated = zlib_decode($bytes, $this->entryBytes + 1);
        } finally {
            restore_error_handler();
        }

        return \is_string($inflated) ? $inflated : $this->partialInflate($bytes, $this->entryBytes);
    }

    /**
     * What a damaged Flate stream gives before its damage, as readers do.
     */
    private function partialInflate(string $bytes, int $limit): string
    {
        $context = inflate_init(\ZLIB_ENCODING_DEFLATE);

        if (false === $context) {
            return '';
        }

        $output = '';
        set_error_handler(static fn (): bool => true);

        try {
            // After a damage zlib refuses the rest; past the limit the stream is refused, and inflating more would
            // only fill the memory.
            $chunks = str_split($bytes, 1024);

            for ($index = 0, $part = ''; false !== $part && $index < \count($chunks) && \strlen($output) <= $limit; ++$index) {
                $part = inflate_add($context, $chunks[$index], \ZLIB_SYNC_FLUSH);
                $output .= false === $part ? '' : $part;
            }
        } finally {
            restore_error_handler();
        }

        return substr($output, 0, $limit + 1);
    }

    /**
     * The rows of predicted data restored: the PNG predictors, one per row, and the TIFF predictor 2 for 8-bit
     * samples.
     */
    private function predicted(string $bytes, PdfDictionary $options): string
    {
        $predictor = $options->get('Predictor');

        if (!\is_int($predictor) || $predictor < 2) {
            return $bytes;
        }

        $colors = max(1, min(32, \is_int($options->get('Colors')) ? $options->get('Colors') : 1));
        $bits = max(1, min(16, \is_int($options->get('BitsPerComponent')) ? $options->get('BitsPerComponent') : 8));
        $columns = max(1, min(1 << 20, \is_int($options->get('Columns')) ? $options->get('Columns') : 1));
        $pixel = intdiv($colors * $bits + 7, 8);
        // A row is never longer than the data: a declared width far beyond it would pad a few bytes into megabytes.
        $row = min(\strlen($bytes), intdiv($colors * $bits * $columns + 7, 8));

        return 2 === $predictor ? $this->tiff($bytes, $row, $pixel) : $this->png($bytes, $row, $pixel);
    }

    private function tiff(string $bytes, int $row, int $pixel): string
    {
        $output = '';

        foreach (str_split($bytes, max(1, $row)) as $line) {
            for ($index = $pixel, $length = \strlen($line); $index < $length; ++$index) {
                $line[$index] = \chr((\ord($line[$index]) + \ord($line[$index - $pixel])) & 0xFF);
            }

            $output .= $line;
        }

        return $output;
    }

    /**
     * @param int<0, max> $row
     */
    private function png(string $bytes, int $row, int $pixel): string
    {
        $output = '';
        $previous = str_repeat("\0", $row);

        foreach (str_split($bytes, $row + 1) as $line) {
            $type = \ord($line[0]);
            $line = str_pad(substr($line, 1), $row, "\0");

            for ($index = 0; $index < $row; ++$index) {
                $left = $index >= $pixel ? \ord($line[$index - $pixel]) : 0;
                $up = \ord($previous[$index]);
                $corner = $index >= $pixel ? \ord($previous[$index - $pixel]) : 0;
                $line[$index] = \chr((\ord($line[$index]) + match ($type) {
                    1 => $left,
                    2 => $up,
                    3 => intdiv($left + $up, 2),
                    4 => $this->paeth($left, $up, $corner),
                    default => 0,
                }) & 0xFF);
            }

            $output .= $line;
            $previous = $line;
        }

        return $output;
    }

    private function paeth(int $left, int $up, int $corner): int
    {
        $estimate = $left + $up - $corner;
        $toLeft = abs($estimate - $left);
        $toUp = abs($estimate - $up);
        $toCorner = abs($estimate - $corner);

        return match (true) {
            $toLeft <= min($toUp, $toCorner) => $left,
            $toUp <= $toCorner => $up,
            default => $corner,
        };
    }

    private function asciiHex(string $bytes): string
    {
        $end = strpos($bytes, '>');
        $digits = (string) preg_replace('/[^0-9a-fA-F]/', '', false === $end ? $bytes : substr($bytes, 0, $end));

        return sodium_hex2bin(1 === \strlen($digits) % 2 ? $digits.'0' : $digits);
    }

    private function ascii85(string $bytes): string
    {
        $end = strpos($bytes, '~>');
        $data = (string) preg_replace('/\s/', '', false === $end ? $bytes : substr($bytes, 0, $end));
        $data = str_starts_with($data, '<~') ? substr($data, 2) : $data;

        $output = '';
        $group = [];

        foreach (str_split($data) as $char) {
            if ('z' === $char && [] === $group) {
                $output .= "\0\0\0\0";

                continue;
            }

            $code = \ord($char[0]) - 33;

            if ($code < 0 || $code > 84) {
                continue;
            }

            $group[] = $code;

            if (5 === \count($group)) {
                $output .= $this->group85($group);
                $group = [];
            }
        }

        if ([] !== $group) {
            // A last group of n characters, padded with the highest digit, gives n - 1 bytes.
            $output .= substr($this->group85(array_pad($group, 5, 84)), 0, \count($group) - 1);
        }

        return $output;
    }

    /**
     * The four bytes of five digits of base 85, the low four of a value past them.
     *
     * @param list<int> $group
     */
    private function group85(array $group): string
    {
        $value = 0;

        foreach ($group as $code) {
            $value = $value * 85 + $code;
        }

        return pack('N', $value & 0xFFFFFFFF);
    }

    private function runLength(string $bytes, int $limit): string
    {
        $output = '';
        $at = 0;
        $length = \strlen($bytes);

        while ($at < $length && \strlen($output) <= $limit) {
            $count = \ord($bytes[$at++]);

            if (128 === $count) {
                break;
            }

            if ($count < 128) {
                $output .= substr($bytes, $at, $count + 1);
                $at += $count + 1;

                continue;
            }

            $output .= str_repeat($bytes[$at] ?? '', 257 - $count);
            ++$at;
        }

        return $output;
    }

    /**
     * LZW as PDF writes it: codes of 9 to 12 bits, 256 clears the table, 257 ends the data, the code width growing
     * one code early.
     */
    private function lzw(string $bytes, int $limit): string
    {
        // The table holds the 256 bytes, then the codes that clear it and end the data.
        $reset = static fn (): array => [[...array_map(\chr(...), range(0, 255)), '', ''], 9];
        [$table, $width] = $reset();
        $buffer = 0;
        $held = 0;
        $previous = null;
        $output = '';

        for ($at = 0, $length = \strlen($bytes); $at < $length && \strlen($output) <= $limit; ++$at) {
            $buffer = (($buffer << 8) | \ord($bytes[$at])) & 0xFFFFFF;
            $held += 8;

            while ($held >= $width) {
                $code = ($buffer >> ($held - $width)) & ((1 << $width) - 1);
                $held -= $width;

                if (257 === $code) {
                    return $output;
                }

                if (256 === $code) {
                    [$table, $width] = $reset();
                    $previous = null;
                } else {
                    $entry = $table[$code] ?? (null === $previous ? '' : $previous.substr($previous, 0, 1));
                    $output .= $entry;

                    if (null !== $previous && '' !== $entry && \count($table) < 4096) {
                        $table[] = $previous.substr($entry, 0, 1);
                    }

                    $previous = $entry;
                    $width = min(12, \count($table) + 1 >= (1 << $width) ? $width + 1 : $width);
                }
            }
        }

        return $output;
    }
}
