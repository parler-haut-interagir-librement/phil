<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Channel;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * What a channel offers of something: how far it shows it — listed, by direct link only, absent —, whether an order
 * may hold it, and whether its price is shown.
 *
 * - An order never holds what its buyer cannot see the price of: an orderable offer shows its price.
 * - A hidden offer is neither orderable nor priced: `of()` makes it so.
 * - An offer shown without its price is an announcement; shown with its price but not orderable, sold out for good or
 *   sold elsewhere.
 *
 * Several offers make one, the strictest: the stricter visibility, orderable and priced only when all are.
 */
final readonly class Offer
{
    private function __construct(
        public Visibility $visibility,
        public bool $orderable,
        public bool $priceShown,
    ) {
    }

    /**
     * @throws InvalidValue for an orderable offer whose price is not shown
     */
    public static function of(Visibility $visibility, bool $orderable = true, bool $priceShown = true): self
    {
        if (Visibility::Hidden === $visibility) {
            return new self($visibility, false, false);
        }

        if ($orderable && !$priceShown) {
            throw InvalidValue::because('An offer an order may hold shows its price.');
        }

        return new self($visibility, $orderable, $priceShown);
    }

    /**
     * Listed, orderable, its price shown: what a channel offers of what nothing restricts.
     */
    public static function full(): self
    {
        return new self(Visibility::Listed, true, true);
    }

    public static function hidden(): self
    {
        return new self(Visibility::Hidden, false, false);
    }

    public function stricter(self $other): self
    {
        return self::of($this->visibility->stricter($other->visibility), $this->orderable && $other->orderable, $this->priceShown && $other->priceShown);
    }

    public function isListed(): bool
    {
        return $this->visibility->isListed();
    }

    public function isReachable(): bool
    {
        return $this->visibility->isReachable();
    }
}
