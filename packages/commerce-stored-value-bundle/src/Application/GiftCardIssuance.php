<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Application;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Override;
use Phil\CommerceStoredValueBundle\Domain\Model\Instrument;

/**
 * What `GiftCards::issueFor()` did for an order: the cards it issued now, each with its code, the one moment the code
 * is known — and every card of the order, issued now or before, by this request or another.
 *
 * Going through it goes through the cards issued now: an order whose cards were issued already gives none to send.
 *
 * @implements IteratorAggregate<int, IssuedGiftCard>
 */
final readonly class GiftCardIssuance implements IteratorAggregate, Countable
{
    /**
     * @param list<IssuedGiftCard> $issued the cards issued now, with their codes
     * @param list<Instrument>     $cards  every card of the order, in the order they were opened
     */
    public function __construct(
        public array $issued,
        public array $cards,
    ) {
    }

    /**
     * Whether this call issued no card: the order sold none, or its cards were issued already.
     */
    public function isEmpty(): bool
    {
        return [] === $this->issued;
    }

    /**
     * @return ArrayIterator<int, IssuedGiftCard>
     */
    #[Override]
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->issued);
    }

    /**
     * How many cards were issued now.
     */
    #[Override]
    public function count(): int
    {
        return \count($this->issued);
    }
}
