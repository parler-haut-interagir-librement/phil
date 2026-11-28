<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Application\Exception;

/**
 * Why a request on a waiting list is refused, for the application to say in its reader's language.
 */
enum RefusalReason: string
{
    /** The list takes no more entries. */
    case Closed = 'closed';

    /** The entry asks for more places than one entry may. */
    case TooManyPlaces = 'too_many_places';

    /** The entry holds no offer to answer. */
    case NoOffer = 'no_offer';

    /** The offer's deadline has come. */
    case OfferExpired = 'offer_expired';
}
