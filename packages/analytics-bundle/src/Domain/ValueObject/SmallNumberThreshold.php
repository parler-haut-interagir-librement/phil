<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The count under which a counter is never shown on its own.
 *
 * A counter of one or two designates the person it counts on a small site. The value is the application's to
 * choose; the bundle only refuses a threshold that would suppress nothing.
 */
final readonly class SmallNumberThreshold
{
    private function __construct(
        public int $value,
    ) {
    }

    public static function of(int $value): self
    {
        if ($value < 2) {
            throw InvalidValue::because(\sprintf('A small-number threshold is at least 2, or it suppresses nothing; %d was given.', $value));
        }

        return new self($value);
    }

    public function isMetBy(int $count): bool
    {
        return $count >= $this->value;
    }
}
