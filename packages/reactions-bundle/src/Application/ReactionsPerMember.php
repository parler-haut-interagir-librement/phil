<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Application;

use Phil\ReactionsBundle\Domain\Exception\InvalidReaction;

/**
 * How many reactions a member gives one content, as the site chooses: one by default, which a new reaction replaces,
 * or several up to the site's cap, each code at most once. The cap itself is bounded, since every reaction a member
 * gives is a row read on each page.
 */
final readonly class ReactionsPerMember
{
    /** The highest cap a site can choose. */
    public const int MAX = 20;

    /**
     * @throws InvalidReaction when the cap is below one or above {@see self::MAX}
     */
    public function __construct(
        public int $cap = 1,
    ) {
        if ($cap < 1 || $cap > self::MAX) {
            throw InvalidReaction::because(\sprintf('A member gives 1 to %d reactions to a content, not %d.', self::MAX, $cap));
        }
    }

    /**
     * Whether a new reaction replaces the member's only one.
     */
    public function replaces(): bool
    {
        return 1 === $this->cap;
    }

    /**
     * Whether a member who gave this many reactions to a content can give one more without replacing any.
     */
    public function admitsAnother(int $given): bool
    {
        return $given < $this->cap;
    }
}
