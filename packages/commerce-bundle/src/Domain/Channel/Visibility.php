<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Channel;

/**
 * How far a channel shows something: listed, reached by its direct link only, or absent.
 *
 * - `Listed`: in the channel's lists and search, its page shown.
 * - `Unlisted`: out of the lists and search, its page shown to whoever has its direct link — a convention edition,
 *   hidden tickets, a presale.
 * - `Hidden`: neither listed nor shown.
 *
 * Several answers make one, the strictest: hidden by one is hidden, unlisted by one is unlisted. Whether it may be
 * ordered, and whether its price is shown, is the rest of an `Offer`.
 */
enum Visibility: string
{
    case Listed = 'listed';
    case Unlisted = 'unlisted';
    case Hidden = 'hidden';

    /**
     * Whether the channel's lists and search show it.
     */
    public function isListed(): bool
    {
        return self::Listed === $this;
    }

    /**
     * Whether its page is shown, listed or reached by its direct link.
     */
    public function isReachable(): bool
    {
        return self::Hidden !== $this;
    }

    /**
     * The stricter of the two: `Hidden` before `Unlisted`, `Unlisted` before `Listed`.
     */
    public function stricter(self $other): self
    {
        return match (true) {
            self::Hidden === $this || self::Hidden === $other => self::Hidden,
            self::Unlisted === $this || self::Unlisted === $other => self::Unlisted,
            default => self::Listed,
        };
    }
}
