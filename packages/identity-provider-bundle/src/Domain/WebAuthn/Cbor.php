<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\WebAuthn;

use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;

/**
 * A CBOR reader (RFC 8949) bounded to what WebAuthn sends: unsigned and negative integers, byte and text strings,
 * arrays, maps keyed by integers or texts, `false`, `true` and `null`, all of definite length. Tags, floats,
 * indefinite lengths, duplicate keys, nesting deeper than MAX_DEPTH and more than MAX_ITEMS items are refused, and
 * no length is trusted beyond the bytes left: a hostile authenticator response cannot make it allocate more than it
 * sent.
 */
final class Cbor
{
    public const int MAX_DEPTH = 8;

    public const int MAX_ITEMS = 1024;

    private int $items = 0;

    private function __construct(private readonly string $bytes, private int $offset)
    {
    }

    /**
     * The item that fills the bytes.
     *
     * @throws MalformedInput when the bytes are not one item, or hold more after it
     */
    public static function decode(string $bytes): mixed
    {
        [$value, $end] = self::decodeAt($bytes, 0);

        if ($end !== \strlen($bytes)) {
            throw MalformedInput::because('The CBOR item is followed by more bytes.');
        }

        return $value;
    }

    /**
     * The item at the offset, and the offset after it: the public key inside the authenticator data is followed by
     * the extensions.
     *
     * @return array{mixed, int}
     */
    public static function decodeAt(string $bytes, int $offset): array
    {
        $reader = new self($bytes, $offset);
        $value = $reader->item(0);

        return [$value, $reader->offset];
    }

    private function item(int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH || ++$this->items > self::MAX_ITEMS) {
            throw MalformedInput::because('The CBOR item is nested too deep or holds too many items.');
        }

        $initial = \ord($this->take(1)[0]);
        $major = $initial >> 5;
        $info = $initial & 0x1F;

        if (7 === $major) {
            return match ($info) {
                20 => false,
                21 => true,
                22 => null,
                default => throw MalformedInput::because('The CBOR simple value or float is not read.'),
            };
        }

        $argument = $this->argument($info);

        return match ($major) {
            0 => $argument,
            1 => -1 - $argument,
            2 => $this->take($argument),
            3 => $this->text($argument),
            4 => $this->array($argument, $depth),
            5 => $this->map($argument, $depth),
            default => throw MalformedInput::because('CBOR tags are not read.'),
        };
    }

    private function argument(int $info): int
    {
        if ($info < 24) {
            return $info;
        }

        $value = match ($info) {
            24 => \ord($this->take(1)[0]),
            25 => $this->unsigned(2),
            26 => $this->unsigned(4),
            27 => $this->unsigned(8),
            default => throw MalformedInput::because('Indefinite CBOR lengths are not read.'),
        };

        if ($value < 0) {
            throw MalformedInput::because('The CBOR integer is too large.');
        }

        return $value;
    }

    /**
     * The big-endian integer of the bytes; eight bytes with the first bit set wrap to a negative number, which the
     * caller refuses.
     */
    private function unsigned(int $length): int
    {
        $value = 0;

        foreach (str_split($this->take($length)) as $byte) {
            $value = ($value << 8) | \ord($byte[0]);
        }

        return $value;
    }

    private function text(int $length): string
    {
        $text = $this->take($length);

        if (!mb_check_encoding($text, 'UTF-8')) {
            throw MalformedInput::because('The CBOR text is not UTF-8.');
        }

        return $text;
    }

    /**
     * @return list<mixed>
     */
    private function array(int $count, int $depth): array
    {
        $this->assertRoom($count, 1);
        $values = [];

        for ($i = 0; $i < $count; ++$i) {
            $values[] = $this->item($depth + 1);
        }

        return $values;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function map(int $count, int $depth): array
    {
        $this->assertRoom($count, 2);
        $values = [];

        for ($i = 0; $i < $count; ++$i) {
            $key = $this->item($depth + 1);

            // A text PHP would turn into an integer key could pass for one: a COSE key reads its members by integer.
            if ((!\is_int($key) && !\is_string($key)) || (\is_string($key) && (string) (int) $key === $key) || \array_key_exists($key, $values)) {
                throw MalformedInput::because('A CBOR map is keyed by distinct integers or texts.');
            }

            $values[$key] = $this->item($depth + 1);
        }

        return $values;
    }

    /**
     * Each item takes at least one byte: a container that announces more than the bytes left is refused before
     * anything is read, its count divided rather than multiplied, so that it cannot overflow.
     */
    private function assertRoom(int $count, int $itemsEach): void
    {
        if ($count > intdiv(\strlen($this->bytes) - $this->offset, $itemsEach)) {
            throw MalformedInput::because('The CBOR container announces more items than bytes left.');
        }
    }

    private function take(int $length): string
    {
        if ($length > \strlen($this->bytes) - $this->offset) {
            throw MalformedInput::because('The CBOR item is cut short.');
        }

        $bytes = substr($this->bytes, $this->offset, $length);
        $this->offset += $length;

        return $bytes;
    }
}
