<?php

declare(strict_types=1);

namespace Phil\Avatar;

use Phil\Avatar\Exception\InvalidAvatar;

/**
 * The choices of an avatar, drawn from its seed: a stream of SHA-256 blocks of the seed and a counter, read four bytes
 * at a time. The same seed and the same calls give the same choices on every machine.
 *
 * It is not a source of secrets: it only spreads seeds over shapes and colors.
 */
final class Random
{
    /** The version of the stream: a new one draws other avatars from the same seeds. */
    private const string DOMAIN = 'phil/avatar v1';

    private const int MAX = 0xFFFFFFFF;

    private string $buffer = '';

    private int $block = 0;

    public function __construct(
        private readonly string $seed,
    ) {
    }

    public static function of(Seed $seed, string $style): self
    {
        return new self($style."\0".$seed->value);
    }

    /**
     * A whole number from a minimum to a maximum, both included, every one as likely.
     *
     * @throws InvalidAvatar when the maximum is below the minimum, or the range wider than 2^32 numbers
     */
    public function int(int $min, int $max): int
    {
        $range = $max - $min + 1;

        if ($range < 1 || $range > self::MAX + 1) {
            throw InvalidAvatar::because('A random number is drawn from a range of 1 to 2^32 numbers.');
        }

        // The draws at or past the largest multiple of the range are drawn again, so that no number is more likely.
        $limit = intdiv(self::MAX + 1, $range) * $range;

        do {
            $draw = $this->next();
        } while ($draw >= $limit);

        return $min + $draw % $range;
    }

    /**
     * One of a list's items, every one as likely.
     *
     * @template T
     *
     * @param non-empty-list<T> $items
     *
     * @return T
     */
    public function pick(array $items): mixed
    {
        return $items[$this->int(0, \count($items) - 1)];
    }

    public function chance(int $inHundred): bool
    {
        return $this->int(1, 100) <= $inHundred;
    }

    private function next(): int
    {
        if ('' === $this->buffer) {
            $this->buffer = hash('sha256', self::DOMAIN."\0".$this->seed."\0".$this->block++, true);
        }

        $word = unpack('N', $this->buffer);
        $this->buffer = substr($this->buffer, 4);
        \assert(\is_array($word) && \is_int($word[1]));

        return $word[1];
    }
}
