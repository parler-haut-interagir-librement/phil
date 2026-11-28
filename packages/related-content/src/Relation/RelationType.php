<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Relation;

use Phil\RelatedContent\Reason;

/**
 * The kinds of relations a merchant sets between products.
 */
enum RelationType: string
{
    /** The target goes with the source: a case for a game, dice for a rulebook. Shown on the source only. */
    case Accessory = 'accessory';

    /** Each goes with the other: shown on both. */
    case Complement = 'complement';

    /** The target is the better version of the source. Shown on the source only. */
    case Upgrade = 'upgrade';

    public function reason(): Reason
    {
        return match ($this) {
            self::Accessory => Reason::Accessory,
            self::Complement => Reason::Complement,
            self::Upgrade => Reason::Upgrade,
        };
    }
}
