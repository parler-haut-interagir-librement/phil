<?php

declare(strict_types=1);

namespace Phil\Listings;

/**
 * Why a member may not respond to a listing, or why a response may not be accepted.
 */
enum Refusal: string
{
    /** The listing is not published, or has expired. */
    case NotOpen = 'not_open';

    /** Its kind takes no responses. */
    case ResponsesClosed = 'responses_closed';

    /** The member wrote the listing. */
    case OwnListing = 'own_listing';

    /** The author or the member blocked the other. */
    case Blocked = 'blocked';

    /** The listing has a minimum age and the member's age is not known. */
    case AgeUnknown = 'age_unknown';

    /** The member is younger than the listing's minimum age. */
    case TooYoung = 'too_young';

    /** The member already has an open response to the listing. */
    case AlreadyResponded = 'already_responded';

    /** The listing has as many open responses as its kind allows. */
    case Full = 'full';

    /** The response is not one of this listing's. */
    case OtherListing = 'other_listing';
}
