<?php

declare(strict_types=1);

namespace Phil\Slides\Application;

use Phil\Slides\Domain\Exception\InvalidOption;
use Phil\Slides\Domain\ValueObject\Deck;

/**
 * A deck as the presenter route shows it: the deck, the address of its audience page, opened in a window of its own,
 * and the length of the talk in minutes, which adds the time left to the timer; 0 for none.
 */
final readonly class Presentation
{
    /** The longest talk, in minutes: a day. */
    public const int MAX_DURATION = 1_440;

    public function __construct(
        public Deck $deck,
        public ?string $audienceUrl = null,
        public int $duration = 0,
    ) {
        if ($duration < 0 || $duration > self::MAX_DURATION) {
            throw InvalidOption::because(\sprintf('A talk lasts 0 to %d minutes, not %d.', self::MAX_DURATION, $duration));
        }
    }
}
