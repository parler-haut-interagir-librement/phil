<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Application;

use Phil\CommerceStoredValueBundle\Domain\Model\Instrument;
use Phil\CommerceStoredValueBundle\Domain\ValueObject\GiftCardCode;

/**
 * A gift card just issued, and its code: the one moment the code is known, to be sent to whoever receives the card.
 */
final readonly class IssuedGiftCard
{
    public function __construct(
        public Instrument $card,
        public GiftCardCode $code,
    ) {
    }
}
