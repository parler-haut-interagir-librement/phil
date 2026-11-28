<?php

declare(strict_types=1);

namespace Phil\RelatedContent;

/**
 * Why a neighbour is proposed. A template may show each reason in its own block: « Pairs well with », « Bought
 * together », « Read also ».
 */
enum Reason: string
{
    /** Chosen by hand by an editor. */
    case Pinned = 'pinned';

    /** An accessory of the item, set by the merchant. */
    case Accessory = 'accessory';

    /** A complement of the item, set by the merchant; it holds both ways. */
    case Complement = 'complement';

    /** The better version of the item, set by the merchant. */
    case Upgrade = 'upgrade';

    /** Shared keywords and close dates, weighted by the indices. */
    case Indices = 'indices';

    /** Similar text, as the search engine finds it. */
    case Similar = 'similar';

    /** Often in the same orders, counted by pair and never by person. */
    case BoughtTogether = 'bought-together';

    /** Often viewed right after the item, counted by pair and never by person. */
    case ViewedNext = 'viewed-next';

    /**
     * Whether a person chose the neighbour rather than a score.
     */
    public function isManual(): bool
    {
        return match ($this) {
            self::Pinned, self::Accessory, self::Complement, self::Upgrade => true,
            self::Indices, self::Similar, self::BoughtTogether, self::ViewedNext => false,
        };
    }
}
