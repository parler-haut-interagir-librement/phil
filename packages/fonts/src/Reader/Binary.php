<?php

declare(strict_types=1);

namespace Phil\Fonts\Reader;

use Phil\Fonts\Exception\InvalidFont;

/**
 * Big-endian numbers read from a string of bytes, each read checked against its end.
 *
 * @internal
 */
final readonly class Binary
{
    public function __construct(
        public string $bytes,
    ) {
    }

    public function length(): int
    {
        return \strlen($this->bytes);
    }

    public function uint8(int $at): int
    {
        return \ord($this->slice($at, 1)[0]);
    }

    public function uint16(int $at): int
    {
        $bytes = $this->slice($at, 2);

        return \ord($bytes[0]) << 8 | \ord($bytes[1]);
    }

    public function int16(int $at): int
    {
        $value = $this->uint16($at);

        return $value < 0x8000 ? $value : $value - 0x10000;
    }

    public function uint32(int $at): int
    {
        return $this->uint16($at) << 16 | $this->uint16($at + 2);
    }

    /**
     * A signed 16.16 fixed-point number.
     */
    public function fixed(int $at): float
    {
        return $this->int16($at) + $this->uint16($at + 2) / 65536;
    }

    public function tag(int $at): string
    {
        return $this->slice($at, 4);
    }

    /**
     * The bytes from an offset, all of them there or the font is truncated.
     */
    public function slice(int $at, int $length): string
    {
        $this->within($at, $length);

        return substr($this->bytes, $at, $length);
    }

    /**
     * Checks that the bytes from an offset are all there, without copying them.
     */
    public function within(int $at, int $length): void
    {
        if ($at < 0 || $length < 0 || $at + $length > \strlen($this->bytes)) {
            throw InvalidFont::because('The font file is truncated.');
        }
    }

    /**
     * A WOFF2 `UIntBase128`: up to five bytes of seven bits each, the high bit set on every byte but the last, no
     * leading zero byte, and a value that fits in 32 bits.
     *
     * @return array{int, int} the value, and the offset after it
     */
    public function base128(int $at): array
    {
        $value = 0;

        for ($index = 0; $index < 5; ++$index) {
            $byte = $this->uint8($at + $index);

            if (0 === $index && 0x80 === $byte) {
                throw InvalidFont::because('A WOFF2 number starts with a zero byte.');
            }

            $value = $value << 7 | $byte & 0x7F;

            if ($value > 0xFFFFFFFF) {
                throw InvalidFont::because('A WOFF2 number does not fit in 32 bits.');
            }

            if ($byte < 0x80) {
                return [$value, $at + $index + 1];
            }
        }

        throw InvalidFont::because('A WOFF2 number is longer than five bytes.');
    }
}
