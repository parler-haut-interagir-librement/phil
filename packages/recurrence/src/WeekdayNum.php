<?php

declare(strict_types=1);

namespace Phil\Recurrence;

use Override;
use Phil\Recurrence\Exception\InvalidRule;
use Stringable;

/**
 * A day of the week in a `BYDAY` part, with its place in the month or the year when it has one: `MO`, `2TU` — the
 * second Tuesday —, `-1FR` — the last Friday.
 */
final readonly class WeekdayNum implements Stringable
{
    public function __construct(
        public Weekday $weekday,
        public ?int $ordinal = null,
    ) {
        if (null !== $ordinal && (0 === $ordinal || abs($ordinal) > 53)) {
            throw InvalidRule::because(\sprintf('The place of a weekday in its month or year is -53 to 53 and not 0; %d is not.', $ordinal));
        }
    }

    public static function parse(string $input): self
    {
        if (1 !== preg_match('/^(?<ordinal>[+-]?\d{1,2})?(?<day>MO|TU|WE|TH|FR|SA|SU)$/i', trim($input), $match)) {
            throw InvalidRule::unreadable($input, 'a weekday of a rule');
        }

        return new self(Weekday::from(strtoupper($match['day'])), '' === $match['ordinal'] ? null : (int) $match['ordinal']);
    }

    #[Override]
    public function __toString(): string
    {
        return ($this->ordinal ?? '').$this->weekday->value;
    }
}
