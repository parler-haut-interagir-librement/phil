<?php

declare(strict_types=1);

namespace Phil\Geo\Geocoding\MaxMind;

use Phil\Geo\Exception\InvalidDatabase;

/**
 * The data section of a MaxMind DB file, as its format specification (version 2.0) describes it: each value a control
 * byte giving its type and size, then its bytes; a pointer to a value written once elsewhere.
 *
 * Strings, numbers, maps, arrays and booleans are PHP's own. A 64- or 128-bit unsigned integer too large for PHP's
 * integer is its hexadecimal digits, `0x` first; raw bytes are a string.
 *
 * @internal
 */
final class Decoder
{
    private const int MAX_DEPTH = 64;

    /** The block of the file read last, kept at hand: most values lie next to the one before. */
    private string $window = '';

    private int $windowStart = 0;

    public function __construct(
        private readonly FileBytes $bytes,
        private readonly int $start,
    ) {
    }

    /**
     * The value at an offset of the data section, and the offset of the value after it.
     *
     * @return array{mixed, int}
     */
    public function decode(int $offset, int $depth = 0): array
    {
        if ($depth > self::MAX_DEPTH) {
            throw $this->corrupt(\sprintf('its values nest more than %d deep.', self::MAX_DEPTH));
        }

        $control = $this->byte($offset++);
        $type = $control >> 5;

        if (1 === $type) {
            [$pointer, $offset] = $this->pointer($control, $offset);

            return [$this->decode($pointer, $depth + 1)[0], $offset];
        }

        if (0 === $type) {
            $type = 7 + $this->byte($offset++);
        }

        [$size, $offset] = $this->size($control, $offset);

        return match ($type) {
            2, 4 => [$this->read($offset, $size), $offset + $size],
            3 => [$this->real($offset, $size, 8, 'E'), $offset + 8],
            5, 6, 9, 10 => [$this->unsigned($this->read($offset, $size)), $offset + $size],
            7 => $this->map($offset, $size, $depth),
            8 => [$this->signed($offset, $size), $offset + $size],
            11 => $this->list($offset, $size, $depth),
            14 => [$this->boolean($size), $offset],
            15 => [$this->real($offset, $size, 4, 'G'), $offset + 4],
            default => throw $this->corrupt(\sprintf('it holds a value of unknown type %d.', $type)),
        };
    }

    /**
     * @return array{int, int}
     */
    private function pointer(int $control, int $offset): array
    {
        $extra = ($control >> 3) & 0x3;
        $value = $this->unsignedInt($this->read($offset, $extra + 1));
        $high = $control & 0x7;

        return [match ($extra) {
            0 => ($high << 8) | $value,
            1 => (($high << 16) | $value) + 2_048,
            2 => (($high << 24) | $value) + 526_336,
            default => $value,
        }, $offset + $extra + 1];
    }

    /**
     * @return array{int, int}
     */
    private function size(int $control, int $offset): array
    {
        $size = $control & 0x1F;

        if ($size < 29) {
            return [$size, $offset];
        }

        $extra = $size - 28;
        $value = $this->unsignedInt($this->read($offset, $extra));

        return [match ($extra) {
            1 => 29 + $value,
            2 => 285 + $value,
            default => 65_821 + $value,
        }, $offset + $extra];
    }

    /**
     * @return array{array<string, mixed>, int}
     */
    private function map(int $offset, int $size, int $depth): array
    {
        $map = [];

        for ($i = 0; $i < $size; ++$i) {
            [$key, $offset] = $this->decode($offset, $depth + 1);

            if (!\is_string($key)) {
                throw $this->corrupt('a map has a key that is not a string.');
            }

            [$map[$key], $offset] = $this->decode($offset, $depth + 1);
        }

        return [$map, $offset];
    }

    /**
     * @return array{list<mixed>, int}
     */
    private function list(int $offset, int $size, int $depth): array
    {
        $list = [];

        for ($i = 0; $i < $size; ++$i) {
            [$list[], $offset] = $this->decode($offset, $depth + 1);
        }

        return [$list, $offset];
    }

    private function boolean(int $size): bool
    {
        if ($size > 1) {
            throw $this->corrupt(\sprintf('a boolean is 0 or 1, not %d.', $size));
        }

        return 1 === $size;
    }

    private function real(int $offset, int $size, int $expected, string $format): float
    {
        if ($size !== $expected) {
            throw $this->corrupt(\sprintf('a number of %d bytes should be %d.', $size, $expected));
        }

        $value = unpack($format, $this->read($offset, $size));

        return \is_array($value) && \is_float($value[1]) ? $value[1] : 0.0;
    }

    private function signed(int $offset, int $size): int
    {
        if ($size > 4) {
            throw $this->corrupt(\sprintf('a 32-bit integer of %d bytes.', $size));
        }

        $value = $this->unsignedInt($this->read($offset, $size));

        return 4 === $size && $value >= 0x8000_0000 ? $value - 0x1_0000_0000 : $value;
    }

    private function unsigned(string $bytes): int|string
    {
        $bytes = ltrim($bytes, "\0");

        if (\strlen($bytes) < 8 || (8 === \strlen($bytes) && \ord($bytes[0]) < 0x80)) {
            return $this->unsignedInt($bytes);
        }

        $hex = unpack('H*', $bytes);

        return '0x'.(\is_array($hex) && \is_string($hex[1]) ? $hex[1] : '');
    }

    /**
     * Big-endian bytes whose value PHP's integer holds: at most eight, the first below 0x80 when there are eight.
     */
    private function unsignedInt(string $bytes): int
    {
        $value = 0;
        $length = \strlen($bytes);

        for ($i = 0; $i < $length; ++$i) {
            $value = ($value << 8) | \ord($bytes[$i]);
        }

        return $value;
    }

    private function byte(int $offset): int
    {
        return \ord($this->read($offset, 1)[0]);
    }

    private function read(int $offset, int $length): string
    {
        $at = $this->start + $offset - $this->windowStart;

        if ($at >= 0 && $at + $length <= \strlen($this->window)) {
            return substr($this->window, $at, $length);
        }

        [$this->windowStart, $this->window] = $this->bytes->blockAt($this->start + $offset);

        return $this->bytes->read($this->start + $offset, $length);
    }

    private function corrupt(string $reason): InvalidDatabase
    {
        return InvalidDatabase::corrupt($this->bytes->path, $reason);
    }
}
