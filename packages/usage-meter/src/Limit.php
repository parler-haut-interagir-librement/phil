<?php

declare(strict_types=1);

namespace Phil\UsageMeter;

use Phil\UsageMeter\Exception\InvalidUsage;

/**
 * How much a subject may use of a meter in one window: a whole number from zero, or no limit at all.
 */
final readonly class Limit
{
    private function __construct(
        /** The most a window may count, or null for no limit. */
        public ?int $value,
    ) {
    }

    public static function of(int $value): self
    {
        if ($value < 0) {
            throw InvalidUsage::because(\sprintf('A limit is zero or more; got %d.', $value));
        }

        return new self($value);
    }

    public static function unlimited(): self
    {
        return new self(null);
    }

    /**
     * A limit written as text: `unlimited`, or a whole number in decimal digits without sign or leading zero.
     *
     * @return self|null null when the text is neither
     */
    public static function parse(string $text): ?self
    {
        if ('unlimited' === $text) {
            return self::unlimited();
        }

        if (1 !== preg_match('/^(?:0|[1-9]\d{0,17})$/D', $text)) {
            return null;
        }

        return new self((int) $text);
    }

    public function isUnlimited(): bool
    {
        return null === $this->value;
    }

    /**
     * Whether `$amount` more may be counted on top of `$used`.
     */
    public function allows(int $used, int $amount): bool
    {
        return null === $this->value || $used + $amount <= $this->value;
    }

    /**
     * @return int|null what is left on top of `$used`, never below zero; null for no limit
     */
    public function remaining(int $used): ?int
    {
        return null === $this->value ? null : max(0, $this->value - $used);
    }

    /**
     * The more generous of two limits; no limit beats any number.
     */
    public function orHigher(self $other): self
    {
        if (null === $this->value || null === $other->value) {
            return self::unlimited();
        }

        return new self(max($this->value, $other->value));
    }

    public function toString(): string
    {
        return null === $this->value ? 'unlimited' : (string) $this->value;
    }
}
