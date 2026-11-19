<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Application\Code;

use Phil\ShortLinkBundle\Application\Exception\InvalidCodec;

/**
 * Short identifiers made of non-negative integers, in the format of the Sqids specification: the same alphabet, the
 * same minimum length and the same blocklist give the same identifiers as the other implementations of the format.
 *
 * The alphabet is restricted to the characters a URL path carries without escaping — letters, digits, `-`, `.`, `_`
 * and `~` —, at least three of them, each once. A blocklist word is kept only when it has three characters or more,
 * all of them in the alphabet; it is compared in lower case. An identifier that contains a word, or starts or ends with
 * a word that has a digit, or is a word of three characters, is replaced by the next one the format gives.
 *
 * An identifier is no secret: it hides the order of the numbers, nothing more. Decoding is bounded: an identifier
 * longer than {@see self::MAX_LENGTH} characters, with a character out of the alphabet, or whose number would not fit
 * in a PHP integer decodes to nothing.
 */
final readonly class SqidCodec
{
    public const string DEFAULT_ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    public const int MAX_LENGTH = 255;

    private const string CHARACTERS = '/^[A-Za-z0-9._~-]+$/D';

    /** The alphabet in the order the format starts from: the one given, shuffled once. */
    private string $alphabet;

    /** @var list<string> the words kept from the blocklist, in lower case */
    private array $blocklist;

    /**
     * @param iterable<string> $blocklist
     *
     * @throws InvalidCodec when the alphabet or the minimum length is not one the format accepts
     */
    public function __construct(string $alphabet = self::DEFAULT_ALPHABET, private int $minLength = 0, iterable $blocklist = [])
    {
        if (\strlen($alphabet) < 3 || 1 !== preg_match(self::CHARACTERS, $alphabet)) {
            throw InvalidCodec::because('The alphabet has at least 3 characters among letters, digits, "-", ".", "_" and "~".');
        }

        if (\count(array_unique(str_split($alphabet))) !== \strlen($alphabet)) {
            throw InvalidCodec::because('Each character of the alphabet appears once.');
        }

        if ($minLength < 0 || $minLength > self::MAX_LENGTH) {
            throw InvalidCodec::because(\sprintf('The minimum length is between 0 and %d.', self::MAX_LENGTH));
        }

        $lower = strtolower($alphabet);
        $words = [];

        foreach ($blocklist as $word) {
            $word = strtolower($word);

            if (\strlen($word) >= 3 && \strlen($word) === strspn($word, $lower)) {
                $words[$word] = $word;
            }
        }

        $this->blocklist = array_values($words);
        $this->alphabet = $this->shuffle($alphabet);
    }

    /**
     * The identifier of the numbers, the empty string for none.
     *
     * @param list<int> $numbers
     *
     * @throws InvalidCodec when a number is negative, or every identifier of the numbers is blocked
     */
    public function encode(array $numbers): string
    {
        foreach ($numbers as $number) {
            if ($number < 0) {
                throw InvalidCodec::because('A number to encode is 0 or more.');
            }
        }

        return [] === $numbers ? '' : $this->encodeNumbers($numbers, 0);
    }

    /**
     * The numbers of an identifier; none for one this codec could not have written.
     *
     * @return list<int>
     */
    public function decode(string $id): array
    {
        if ('' === $id || \strlen($id) > self::MAX_LENGTH || \strlen($id) !== strspn($id, $this->alphabet)) {
            return [];
        }

        $offset = (int) strpos($this->alphabet, $id[0]);
        $alphabet = strrev(substr($this->alphabet, $offset).substr($this->alphabet, 0, $offset));
        $rest = substr($id, 1);
        $numbers = [];

        while ('' !== $rest) {
            $separator = $alphabet[0];
            $chunks = explode($separator, $rest);

            if ('' === $chunks[0]) {
                return $numbers;
            }

            $number = $this->toNumber($chunks[0], substr($alphabet, 1));

            if (null === $number) {
                return [];
            }

            $numbers[] = $number;

            if (\count($chunks) > 1) {
                $alphabet = $this->shuffle($alphabet);
            }

            $rest = implode($separator, \array_slice($chunks, 1));
        }

        return $numbers;
    }

    /**
     * Whether the identifier is the one this codec writes for the numbers it decodes to: one identifier per list of
     * numbers, the others refused.
     */
    public function isCanonical(string $id): bool
    {
        $numbers = $this->decode($id);

        return [] !== $numbers && $this->encode($numbers) === $id;
    }

    /**
     * Whether a text holds only characters of the alphabet, between `$min` and `$max` of them.
     */
    public function accepts(string $text, int $min = 1, int $max = self::MAX_LENGTH): bool
    {
        return \strlen($text) >= $min && \strlen($text) <= $max && \strlen($text) === strspn($text, $this->alphabet);
    }

    /**
     * Whether a text is one the blocklist refuses.
     */
    public function isBlocked(string $id): bool
    {
        $id = strtolower($id);
        $length = \strlen($id);

        foreach ($this->blocklist as $word) {
            if (\strlen($word) > $length) {
                continue;
            }

            if ($length <= 3 || \strlen($word) <= 3) {
                if ($id === $word) {
                    return true;
                }
            } elseif (1 === preg_match('/\d/', $word)) {
                if (str_starts_with($id, $word) || str_ends_with($id, $word)) {
                    return true;
                }
            } elseif (str_contains($id, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param non-empty-list<int> $numbers
     *
     * @throws InvalidCodec
     */
    private function encodeNumbers(array $numbers, int $increment): string
    {
        $length = \strlen($this->alphabet);

        if ($increment > $length) {
            throw InvalidCodec::because('Every identifier of these numbers is blocked.');
        }

        $offset = \count($numbers);

        foreach ($numbers as $index => $number) {
            $offset += \ord($this->alphabet[$number % $length]) + $index;
        }

        $offset = ($offset % $length + $increment) % $length;
        $alphabet = strrev(substr($this->alphabet, $offset).substr($this->alphabet, 0, $offset));
        $id = $this->alphabet[$offset];
        $last = \count($numbers) - 1;

        foreach ($numbers as $index => $number) {
            $id .= $this->toId($number, substr($alphabet, 1));

            if ($index < $last) {
                $id .= $alphabet[0];
                $alphabet = $this->shuffle($alphabet);
            }
        }

        if ($this->minLength > \strlen($id)) {
            $id .= $alphabet[0];

            while ($this->minLength > \strlen($id)) {
                $alphabet = $this->shuffle($alphabet);
                $id .= substr($alphabet, 0, $this->minLength - \strlen($id));
            }
        }

        return $this->isBlocked($id) ? $this->encodeNumbers($numbers, $increment + 1) : $id;
    }

    private function toId(int $number, string $alphabet): string
    {
        $length = \strlen($alphabet);
        $id = '';

        do {
            $id = $alphabet[$number % $length].$id;
            $number = intdiv($number, $length);
        } while ($number > 0);

        return $id;
    }

    private function toNumber(string $id, string $alphabet): ?int
    {
        $length = \strlen($alphabet);
        $number = 0;

        foreach (str_split($id) as $character) {
            $digit = (int) strpos($alphabet, $character);

            if ($number > intdiv(\PHP_INT_MAX - $digit, $length)) {
                return null;
            }

            $number = $number * $length + $digit;
        }

        return $number;
    }

    /**
     * The shuffle of the format: the same alphabet always gives the same order.
     */
    private function shuffle(string $alphabet): string
    {
        $length = \strlen($alphabet);

        for ($i = 0, $j = $length - 1; $j > 0; ++$i, --$j) {
            $swap = ($i * $j + \ord($alphabet[$i]) + \ord($alphabet[$j])) % $length;
            $character = $alphabet[$i];
            $alphabet[$i] = $alphabet[$swap];
            $alphabet[$swap] = $character;
        }

        return $alphabet;
    }
}
